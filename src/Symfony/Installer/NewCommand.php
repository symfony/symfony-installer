<?php

/*
 * This file is part of the Symfony Installer package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Installer;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Installer\Exception\AbortException;

/**
 * This command creates new Symfony projects for the given Symfony version.
 *
 * @author Christophe Coevoet <stof@notk.org>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class NewCommand extends DownloadCommand
{
    protected function configure()
    {
        $this
            ->setName('new')
            ->setDescription('Creates a new Symfony project.')
            ->addArgument('directory', InputArgument::REQUIRED, 'Directory where the new project will be created.')
            ->addArgument('version', InputArgument::OPTIONAL, 'The Symfony version to be installed (defaults to the latest stable version).', 'latest')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $directory = rtrim(trim($input->getArgument('directory')), DIRECTORY_SEPARATOR);
        $this->version = trim($input->getArgument('version'));
        $this->projectDir = $this->fs->isAbsolutePath($directory) ? $directory : getcwd().DIRECTORY_SEPARATOR.$directory;
        $this->projectName = basename($directory);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this
                ->checkProjectName()
                ->checkSymfonyVersionIsInstallable()
                ->checkPermissions()
                ->download()
                ->extract()
                ->cleanUp()
                ->dumpReadmeFile()
                ->updateParameters()
                ->updateComposerJson()
                ->createGitIgnore()
                ->checkSymfonyRequirements()
                ->displayInstallationResult()
            ;
        } catch (AbortException $e) {
            aborted:

            $output->writeln('');
            $output->writeln('<error>Aborting download and cleaning up temporary directories.</>');

            $this->cleanUp();

            return 1;
        } catch (\Exception $e) {
            // Guzzle can wrap the AbortException in a GuzzleException
            if ($e->getPrevious() instanceof AbortException) {
                goto aborted;
            }

            $this->cleanUp();
            throw $e;
        }
    }

    /**
     * Checks whether the given Symfony version is installable by the installer.
     * Due to the changes introduced in the Icu/Intl components
     * (see http://symfony.com/blog/new-in-symfony-2-6-farewell-to-icu-component)
     * not all the previous Symfony versions are installable by the installer.
     *
     * The rules to decide if the version is installable are as follows:
     *
     *   - 2.0, 2.1, 2.2 and 2.4 cannot be installed because they are unmaintained.
     *   - 2.3 can be installed starting from version 2.3.21 (inclusive)
     *   - 2.5 can be installed starting from version 2.5.6 (inclusive)
     *   - 2.6, 2.7, 2.8 and 2.9 can be installed regardless the version.
     *
     * @return NewCommand
     *
     * @throws \RuntimeException If the given Symfony version is not compatible with this installer.
     */
    protected function checkSymfonyVersionIsInstallable()
    {
        // 'latest' is a special version name that refers to the latest stable version
        // 'lts' is a special version name that refers to the current long term support version
        if (in_array($this->version, array('latest', 'lts'))) {
            return $this;
        }

        // validate semver syntax
        if (!preg_match('/^[23]\.\d(?:\.\d{1,2})?(?:-(?:dev|BETA\d*|RC\d*))?$/i', $this->version)) {
            throw new \RuntimeException('The Symfony version must be 2.N, 2.N.M, 3.N or 3.N.M (where N and M are positive integers). The special "-dev", "-BETA" and "-RC" versions are also supported.');
        }

        if (preg_match('/^[23]\.\d$/', $this->version)) {
            // Check if we have a minor version in order to retrieve the last patch from symfony.com

            $client = $this->getGuzzleClient();
            $versionsList = $client->get('http://symfony.com/versions.json')->json();

            if ($versionsList && isset($versionsList[$this->version])) {
                // Get the latest patch of the minor version the user asked
                $this->version = $versionsList[$this->version];
            } elseif ($versionsList && !isset($versionsList[$this->version])) {
                throw new \RuntimeException(sprintf(
                    "The selected branch (%s) does not exist, or is not maintained.\n".
                    "To solve this issue, install Symfony with the latest stable release:\n\n".
                    '%s %s %s',
                    $this->version,
                    $_SERVER['PHP_SELF'],
                    $this->getName(),
                    $this->projectDir
                ));
            }
        }

        // 2.0, 2.1, 2.2 and 2.4 cannot be installed because they are unmaintained
        if (preg_match('/^2\.[0124]\.\d{1,2}$/', $this->version)) {
            throw new \RuntimeException(sprintf(
                "The selected version (%s) cannot be installed because it belongs\n".
                "to an unmaintained Symfony branch which is not compatible with this installer.\n".
                "To solve this issue install Symfony manually executing the following command:\n\n".
                'composer create-project symfony/framework-standard-edition %s %s',
                $this->version, $this->projectDir, $this->version
            ));
        }

        // 2.3 can be installed starting from version 2.3.21 (inclusive)
        if (preg_match('/^2\.3\.\d{1,2}$/', $this->version) && version_compare($this->version, '2.3.21', '<')) {
            throw new \RuntimeException(sprintf(
                "The selected version (%s) cannot be installed because this installer\n".
                "is compatible with Symfony 2.3 versions starting from 2.3.21.\n".
                "To solve this issue install Symfony manually executing the following command:\n\n".
                'composer create-project symfony/framework-standard-edition %s %s',
                $this->version, $this->projectDir, $this->version
            ));
        }

        // 2.5 can be installed starting from version 2.5.6 (inclusive)
        if (preg_match('/^2\.5\.\d{1,2}$/', $this->version) && version_compare($this->version, '2.5.6', '<')) {
            throw new \RuntimeException(sprintf(
                "The selected version (%s) cannot be installed because this installer\n".
                "is compatible with Symfony 2.5 versions starting from 2.5.6.\n".
                "To solve this issue install Symfony manually executing the following command:\n\n".
                'composer create-project symfony/framework-standard-edition %s %s',
                $this->version, $this->projectDir, $this->version
            ));
        }

        // "-dev" versions are not supported because Symfony doesn't provide packages for them
        if (preg_match('/^.*\-dev$/i', $this->version)) {
            throw new \RuntimeException(sprintf(
                "The selected version (%s) cannot be installed because it hasn't\n".
                "been published as a package yet. Read the following article for\n".
                "an alternative installation method:\n\n".
                "> How to Install or Upgrade to the Latest, Unreleased Symfony Version\n".
                '> http://symfony.com/doc/current/cookbook/install/unstable_versions.html',
                $this->version
            ));
        }

        // warn the user when downloading an unstable version
        if (preg_match('/^.*\-(BETA|RC)\d*$/i', $this->version)) {
            $this->output->writeln("\n <bg=red> WARNING </> You are downloading an unstable Symfony version.");
            // versions provided by the download server are case sensitive
            $this->version = strtoupper($this->version);
        }

        return $this;
    }

    /**
     * Removes all the temporary files and directories created to
     * download the project and removes Symfony-related files that don't make
     * sense in a proprietary project.
     *
     * @return NewCommand
     */
    protected function cleanUp()
    {
        $this->fs->remove(dirname($this->downloadedFilePath));

        try {
            $licenseFile = array($this->projectDir.'/LICENSE');
            $upgradeFiles = glob($this->projectDir.'/UPGRADE*.md');
            $changelogFiles = glob($this->projectDir.'/CHANGELOG*.md');

            $filesToRemove = array_merge($licenseFile, $upgradeFiles, $changelogFiles);
            $this->fs->remove($filesToRemove);
        } catch (\Exception $e) {
            // don't throw an exception in case any of the Symfony-related files cannot
            // be removed, because this is just an enhancement, not something mandatory
            // for the project
        }

        return $this;
    }

    /**
     * It displays the message with the result of installing Symfony
     * and provides some pointers to the user.
     *
     * @return NewCommand
     */
    protected function displayInstallationResult()
    {
        if (empty($this->requirementsErrors)) {
            $this->output->writeln(sprintf(
                " <info>%s</info>  Symfony %s was <info>successfully installed</info>. Now you can:\n",
                defined('PHP_WINDOWS_VERSION_BUILD') ? 'OK' : '✔',
                $this->getInstalledSymfonyVersion()
            ));
        } else {
            $this->output->writeln(sprintf(
                " <comment>%s</comment>  Symfony %s was <info>successfully installed</info> but your system doesn't meet its\n".
                "     technical requirements! Fix the following issues before executing\n".
                "     your Symfony application:\n",
                defined('PHP_WINDOWS_VERSION_BUILD') ? 'FAILED' : '✕',
                $this->getInstalledSymfonyVersion()
            ));

            foreach ($this->requirementsErrors as $helpText) {
                $this->output->writeln(' * '.$helpText);
            }

            $checkFile = $this->isSymfony3() ? 'bin/symfony_requirements' : 'app/check.php';

            $this->output->writeln(sprintf(
                " After fixing these issues, re-check Symfony requirements executing this command:\n\n".
                "   <comment>php %s/%s</comment>\n\n".
                " Then, you can:\n",
                $this->projectName, $checkFile
            ));
        }

        if ('.' !== $this->projectDir) {
            $this->output->writeln(sprintf(
                "    * Change your current directory to <comment>%s</comment>\n", $this->projectDir
            ));
        }

        $consoleDir = ($this->isSymfony3() ? 'bin' : 'app');

        $this->output->writeln(sprintf(
            "    * Configure your application in <comment>app/config/parameters.yml</comment> file.\n\n".
            "    * Run your application:\n".
            "        1. Execute the <comment>php %s/console server:run</comment> command.\n".
            "        2. Browse to the <comment>http://localhost:8000</comment> URL.\n\n".
            "    * Read the documentation at <comment>http://symfony.com/doc</comment>\n",
            $consoleDir
        ));

        return $this;
    }

    /**
     * Dump a basic README.md file.
     *
     * @return NewCommand
     */
    protected function dumpReadmeFile()
    {
        $readmeContents = sprintf("%s\n%s\n\nA Symfony project created on %s.\n", $this->projectName, str_repeat('=', strlen($this->projectName)), date('F j, Y, g:i a'));
        try {
            $this->fs->dumpFile($this->projectDir.'/README.md', $readmeContents);
        } catch (\Exception $e) {
            // don't throw an exception in case the file could not be created,
            // because this is just an enhancement, not something mandatory
            // for the project
        }

        return $this;
    }

    /**
     * Updates the Symfony parameters.yml file to replace default configuration
     * values with better generated values.
     *
     * @return NewCommand
     */
    protected function updateParameters()
    {
        $filename = $this->projectDir.'/app/config/parameters.yml';

        if (!is_writable($filename)) {
            if ($this->output->isVerbose()) {
                $this->output->writeln(sprintf(
                    " <comment>[WARNING]</comment> The value of the <info>secret</info> configuration option cannot be updated because\n".
                    " the <comment>%s</comment> file is not writable.\n",
                    $filename
                ));
            }

            return $this;
        }

        $ret = str_replace('ThisTokenIsNotSoSecretChangeIt', $this->generateRandomSecret(), file_get_contents($filename));
        file_put_contents($filename, $ret);

        return $this;
    }

    /**
     * Updates the composer.json file to provide better values for some of the
     * default configuration values.
     *
     * @return NewCommand
     */
    protected function updateComposerJson()
    {
        $filename = $this->projectDir.'/composer.json';

        if (!is_writable($filename)) {
            if ($this->output->isVerbose()) {
                $this->output->writeln(sprintf(
                    " <comment>[WARNING]</comment> Project name cannot be configured because\n".
                    " the <comment>%s</comment> file is not writable.\n",
                    $filename
                ));
            }

            return $this;
        }

        $contents = json_decode(file_get_contents($filename), true);

        $contents['name'] = $this->generateComposerProjectName();
        $contents['license'] = 'proprietary';

        if (isset($contents['description'])) {
            unset($contents['description']);
        }

        if (isset($contents['extra']['branch-alias'])) {
            unset($contents['extra']['branch-alias']);
        }

        file_put_contents($filename, json_encode($contents, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->syncComposerLockFile();

        return $this;
    }

    /**
     * Generates a good Composer project name based on the application name
     * and on the user name.
     *
     * @return string
     */
    protected function generateComposerProjectName()
    {
        $name = $this->projectName;

        if (!empty($_SERVER['USERNAME'])) {
            $name = $_SERVER['USERNAME'].'/'.$name;
        } elseif (true === extension_loaded('posix') && $user = posix_getpwuid(posix_getuid())) {
            $name = $user['name'].'/'.$name;
        } elseif (get_current_user()) {
            $name = get_current_user().'/'.$name;
        } else {
            // package names must be in the format foo/bar
            $name = $name.'/'.$name;
        }

        return $this->fixComposerPackageName($name);
    }

    /**
     * Transforms uppercase strings into dash-separated strings
     * (e.g. FooBar -> foo-bar) to comply with Composer rules for package names.
     *
     * @param string $name The project name to transform
     *
     * @return string
     */
    private function fixComposerPackageName($name)
    {
        return strtolower(
            preg_replace(
                array('/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'),
                array('\\1-\\2', '\\1-\\2'),
                strtr($name, '-', '.')
            )
        );
    }

    protected function getDownloadedApplicationType()
    {
        return 'Symfony';
    }

    protected function getRemoteFileUrl()
    {
        return 'http://symfony.com/download?v=Symfony_Standard_Vendors_'.$this->version;
    }

    /**
     * Updates the hash values stored in composer.lock to avoid out-of-sync
     * problems when the composer.json file contents are changed.
     */
    private function syncComposerLockFile()
    {
        $composerJsonFileContents = file_get_contents($this->projectDir.'/composer.json');
        $composerLockFileContents = json_decode(file_get_contents($this->projectDir.'/composer.lock'), true);

        if (array_key_exists('hash', $composerLockFileContents)) {
            $composerLockFileContents['hash'] = md5($composerJsonFileContents);
        }

        if (array_key_exists('content-hash', $composerLockFileContents)) {
            $composerLockFileContents['content-hash'] = $this->getComposerContentHash($composerJsonFileContents);
        }

        file_put_contents($this->projectDir.'/composer.lock', json_encode($composerLockFileContents, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    /**
     * Returns the md5 hash of the sorted content of the composer file.
     *
     * @see https://github.com/composer/composer/blob/master/src/Composer/Package/Locker.php (getContentHash() method)
     *
     * @param string $composerJsonFileContents The contents of the composer.json file.
     *
     * @return string
     */
    private function getComposerContentHash($composerJsonFileContents)
    {
        $content = json_decode($composerJsonFileContents, true);

        $relevantKeys = array(
            'name',
            'version',
            'require',
            'require-dev',
            'conflict',
            'replace',
            'provide',
            'minimum-stability',
            'prefer-stable',
            'repositories',
            'extra',
        );

        $relevantContent = array();

        foreach (array_intersect($relevantKeys, array_keys($content)) as $key) {
            $relevantContent[$key] = $content[$key];
        }

        if (isset($content['config']['platform'])) {
            $relevantContent['config']['platform'] = $content['config']['platform'];
        }

        ksort($relevantContent);

        return md5(json_encode($relevantContent));
    }
}
