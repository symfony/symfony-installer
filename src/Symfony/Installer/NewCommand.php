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

/**
 * This command creates new Symfony projects for the given Symfony version.
 *
 * @author Christophe Coevoet <stof@notk.org>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class NewCommand extends DownloadCommand
{
    protected $projectName;
    protected $projectDir;
    protected $version;
    protected $downloadedFilePath;
    protected $requirementsErrors = array();

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
        $this->projectDir = $this->fs->isAbsolutePath($directory) ? $directory : getcwd().DIRECTORY_SEPARATOR.$directory;
        $this->projectName = basename($directory);
        $this->version = trim($input->getArgument('version'));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this
                ->checkProjectName()
                ->checkSymfonyVersionIsInstallable()
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
        } catch (\Exception $e) {
            $this->cleanUp();
            throw $e;
        }
    }

    /**
     * Checks whether it's safe to create a new project for the given name in the
     * given directory.
     *
     * @return NewCommand
     *
     * @throws \RuntimeException if a project with the same does already exist
     */
    protected function checkProjectName()
    {
        if (is_dir($this->projectDir) && !$this->isEmptyDirectory($this->projectDir)) {
            throw new \RuntimeException(sprintf(
                "There is already a '%s' project in this directory (%s).\n".
                "Change your project name or create it in another directory.",
                $this->projectName, $this->projectDir
            ));
        }

        return $this;
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
        // available at the moment of installing Symfony
        if ('latest' === $this->version) {
            return $this;
        }

        // 'lts' is a special version name that refers to the current long term support version
        if ('lts' === $this->version) {
            return $this;
        }

        // validate semver syntax
        if (!preg_match('/^2\.\d(?:\.\d{1,2})?$/', $this->version)) {
            throw new \RuntimeException('The Symfony version should be 2.N or 2.N.M, where N = 0..9 and M = 0..99');
        }

        if (preg_match('/^2\.\d$/', $this->version)) {
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
                "composer create-project symfony/framework-standard-edition %s %s",
                $this->version, $this->projectDir, $this->version
            ));
        }

        // 2.3 can be installed starting from version 2.3.21 (inclusive)
        if (preg_match('/^2\.3\.\d{1,2}$/', $this->version) && version_compare($this->version, '2.3.21', '<')) {
            throw new \RuntimeException(sprintf(
                "The selected version (%s) cannot be installed because this installer\n".
                "is compatible with Symfony 2.3 versions starting from 2.3.21.\n".
                "To solve this issue install Symfony manually executing the following command:\n\n".
                "composer create-project symfony/framework-standard-edition %s %s",
                $this->version, $this->projectDir, $this->version
            ));
        }

        // 2.5 can be installed starting from version 2.5.6 (inclusive)
        if (preg_match('/^2\.5\.\d{1,2}$/', $this->version) && version_compare($this->version, '2.5.6', '<')) {
            throw new \RuntimeException(sprintf(
                "The selected version (%s) cannot be installed because this installer\n".
                "is compatible with Symfony 2.5 versions starting from 2.5.6.\n".
                "To solve this issue install Symfony manually executing the following command:\n\n".
                "composer create-project symfony/framework-standard-edition %s %s",
                $this->version, $this->projectDir, $this->version
            ));
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

            $this->output->writeln(sprintf(
                " After fixing these issues, re-check Symfony requirements executing this command:\n\n".
                "   <comment>php %s/app/check.php</comment>\n\n".
                " Then, you can:\n",
                $this->projectName
            ));
        }

        $this->output->writeln(sprintf(
            "    * Change your current directory to <comment>%s</comment>\n\n".
            "    * Configure your application in <comment>app/config/parameters.yml</comment> file.\n\n".
            "    * Run your application:\n".
            "        1. Execute the <comment>php app/console server:run</comment> command.\n".
            "        2. Browse to the <comment>http://localhost:8000</comment> URL.\n\n".
            "    * Read the documentation at <comment>http://symfony.com/doc</comment>\n",
            $this->projectDir
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

        return $this;
    }

    /**
     * Returns the full Symfony version number of the project by getting
     * it from the composer.lock file.
     *
     * @return string
     */
    protected function getInstalledSymfonyVersion()
    {
        $composer = json_decode(file_get_contents($this->projectDir.'/composer.lock'), true);

        foreach ($composer['packages'] as $package) {
            if ('symfony/symfony' === $package['name']) {
                if ('v' === substr($package['version'], 0, 1)) {
                    return substr($package['version'], 1);
                };

                return $package['version'];
            }
        }
    }

    /**
     * Generates a good Composer project name based on the application name
     * and on the user name.
     *
     * @return string
     */
    protected function generateComposerProjectName()
    {
        $name = preg_replace('{(?:([a-z])([A-Z])|([A-Z])([A-Z][a-z]))}', '\\1\\3-\\2\\4', $this->projectName);
        $name = strtolower($name);

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

        return $name;
    }

    protected function getDownloadedApplicationType()
    {
        return 'Symfony';
    }

    protected function getRemoteFileUrl()
    {
        return 'http://symfony.com/download?v=Symfony_Standard_Vendors_'.$this->version;
    }
}
