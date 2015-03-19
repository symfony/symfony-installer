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

use Distill\Distill;
use Distill\Exception\IO\Input\FileCorruptedException;
use Distill\Exception\IO\Input\FileEmptyException;
use Distill\Exception\IO\Output\TargetDirectoryNotWritableException;
use Distill\Strategy\MinimumSize;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Subscriber\Progress\Progress;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * This command creates new Symfony projects for the given Symfony version.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class DemoCommand extends Command
{
    /**
     * @var Filesystem
     */
    private $fs;
    private $projectName;
    private $projectDir;
    private $version;
    private $compressedFilePath;
    private $requirementsErrors = array();

    /**
     * @var OutputInterface
     */
    private $output;

    protected function configure()
    {
        $this
            ->setName('demo')
            ->setDescription('Creates a demo Symfony project.')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->fs = new Filesystem();
        $this->output = $output;
        $this->projectDir = getcwd();

        $i = 1;
        $projectName = 'symfony_demo';
        while (file_exists($this->projectDir.DIRECTORY_SEPARATOR.$projectName)) {
            $projectName = 'symfony_demo_'.(++$i);
        }

        $this->projectName = $projectName;
        $this->projectDir = $this->projectDir.DIRECTORY_SEPARATOR.$projectName;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this
                ->download()
                ->extract()
                ->cleanUp()
                ->updateParameters()
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
     * Chooses the best compressed file format to download (ZIP or TGZ) depending upon the
     * available operating system uncompressing commands and the enabled PHP extensions
     * and it downloads the file.
     *
     * @return demoCommand
     *
     * @throws \RuntimeException if the Symfony archive could not be downloaded
     */
    private function download()
    {
        $this->output->writeln("\n Downloading Demo Application...");

        // decide which is the best compressed version to download
        $distill = new Distill();
        $demoArchiveFile = $distill
            ->getChooser()
            ->setStrategy(new MinimumSize())
            ->addFilesWithDifferentExtensions('http://symfony.com/download?v=Symfony_Demo', ['zip', 'tgz'])
            ->getPreferredFile()
        ;

        /** @var ProgressBar|null $progressBar */
        $progressBar = null;
        $downloadCallback = function ($size, $downloaded, $client, $request, Response $response) use (&$progressBar) {
            // Don't initialize the progress bar for redirects as the size is much smaller
            if ($response->getStatusCode() >= 300) {
                return;
            }

            if (null === $progressBar) {
                ProgressBar::setPlaceholderFormatterDefinition('max', function (ProgressBar $bar) {
                    return $this->formatSize($bar->getMaxSteps());
                });
                ProgressBar::setPlaceholderFormatterDefinition('current', function (ProgressBar $bar) {
                    return str_pad($this->formatSize($bar->getStep()), 11, ' ', STR_PAD_LEFT);
                });

                $progressBar = new ProgressBar($this->output, $size);
                $progressBar->setFormat('%current%/%max% %bar%  %percent:3s%%');
                $progressBar->setRedrawFrequency(max(1, floor($size / 1000)));
                $progressBar->setBarWidth(60);

                if (!defined('PHP_WINDOWS_VERSION_BUILD')) {
                    $progressBar->setEmptyBarCharacter('░'); // light shade character \u2591
                    $progressBar->setProgressCharacter('');
                    $progressBar->setBarCharacter('▓'); // dark shade character \u2593
                }

                $progressBar->start();
            }

            $progressBar->setProgress($downloaded);
        };

        $client = new Client();
        $client->getEmitter()->attach(new Progress(null, $downloadCallback));

        // store the file in a temporary hidden directory with a random name
        $this->compressedFilePath = getcwd().DIRECTORY_SEPARATOR.'.'.uniqid(time()).DIRECTORY_SEPARATOR.'symfony_demo.'.pathinfo($demoArchiveFile, PATHINFO_EXTENSION);

        try {
            $response = $client->get($demoArchiveFile);
        } catch (ClientException $e) {
            throw new \RuntimeException(sprintf("The Symfony Demo application couldn't be downloaded because of the following error:\n%s", $e->getMessage()));
        }

        $this->fs->dumpFile($this->compressedFilePath, $response->getBody());

        if (null !== $progressBar) {
            $progressBar->finish();
            $this->output->writeln("\n");
        }

        return $this;
    }

    /**
     * Extracts the compressed file (ZIP or TGZ) using the
     * native operating system commands if available or PHP code otherwise.
     *
     * @return DemoCommand
     *
     * @throws \RuntimeException if the downloaded archive could not be extracted
     */
    private function extract()
    {
        $this->output->writeln(" Preparing project...\n");

        try {
            $distill = new Distill();
            $extractionSucceeded = $distill->extractWithoutRootDirectory($this->compressedFilePath, $this->projectDir);
        } catch (FileCorruptedException $e) {
            throw new \RuntimeException(sprintf(
                "Symfony Demo Application can't be installed because the downloaded package is corrupted.\n".
                "To solve this issue, try running this command again.\n%s",
                $this->getExecutedCommand()
            ));
        } catch (FileEmptyException $e) {
            throw new \RuntimeException(sprintf(
                "Symfony Demo Application can't be installed because the downloaded package is empty.\n".
                "To solve this issue, try running this command again.\n%s",
                $this->getExecutedCommand()
            ));
        } catch (TargetDirectoryNotWritableException $e) {
            throw new \RuntimeException(sprintf(
                "Symfony Demo Application can't be installed because the installer doesn't have enough\n".
                "permissions to uncompress and rename the package contents.\n".
                "To solve this issue, check the permissions of the %s directory and\n".
                "try running this command again.\n%s",
                getcwd(), $this->getExecutedCommand()
            ));
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf(
                "Symfony Demo Application can't be installed because the downloaded package is corrupted\n".
                "or because the installer doesn't have enough permissions to uncompress and\n".
                "rename the package contents.\n".
                "To solve this issue, check the permissions of the %s directory and\n".
                "try running this command again.\n%s",
                getcwd(), $this->getExecutedCommand()
            ));
        }

        if (!$extractionSucceeded) {
            throw new \RuntimeException(
                "Symfony Demo Application can't be installed because the downloaded package is corrupted\n".
                "or because the uncompress commands of your operating system didn't work."
            );
        }

        return $this;
    }

    /**
     * Removes all the temporary files and directories created to
     * download the demo application.
     *
     * @return NewCommand
     */
    private function cleanUp()
    {
        $this->fs->remove(dirname($this->compressedFilePath));

        return $this;
    }

    /**
     * It displays the message with the result of installing the Symfony Demo
     * application and provides some pointers to the user.
     *
     * @return DemoCommand
     */
    private function displayInstallationResult()
    {
        if (empty($this->requirementsErrors)) {
            $this->output->writeln(sprintf(
                " <info>%s</info>  Symfony Demo Application was <info>successfully installed</info>. Now you can:\n",
                defined('PHP_WINDOWS_VERSION_BUILD') ? 'OK' : '✔'
            ));
        } else {
            $this->output->writeln(sprintf(
                " <comment>%s</comment>  Symfony Demo Application was <info>successfully installed</info> but your system doesn't meet the\n".
                "     technical requirements to run Symfony applications! Fix the following issues before executing it:\n",
                defined('PHP_WINDOWS_VERSION_BUILD') ? 'FAILED' : '✕'
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
            "    1. Change your current directory to <comment>%s</comment>\n\n".
            "    2. Execute the <comment>php app/console server:run</comment> command to run the demo application.\n\n".
            "    3. Browse to the <comment>http://localhost:8000</comment> URL to see the demo application in action.\n\n",
            $this->projectDir
        ));

        return $this;
    }

    /**
     * Checks if environment meets Symfony requirements
     *
     * @return DemoCommand
     */
    private function checkSymfonyRequirements()
    {
        require $this->projectDir.'/app/SymfonyRequirements.php';
        $symfonyRequirements = new \SymfonyRequirements();
        $this->requirementsErrors = array();
        foreach ($symfonyRequirements->getRequirements() as $req) {
            if ($helpText = $this->getErrorMessage($req)) {
                $this->requirementsErrors[] = $helpText;
            }
        }

        return $this;
    }

    /**
     * Updates the Symfony parameters.yml file to replace default configuration
     * values with better generated values.
     *
     * @return NewCommand
     */
    private function updateParameters()
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
     * Creates the appropriate .gitignore file for a Symfony project.
     *
     * @return NewCommand
     */
    private function createGitIgnore()
    {
        $gitIgnoreEntries = array(
            '/app/bootstrap.php.cache',
            '/app/cache/*',
            '!app/cache/.gitkeep',
            '/app/config/parameters.yml',
            '/app/logs/*',
            '!app/logs/.gitkeep',
            '/app/phpunit.xml',
            '/bin/',
            '/composer.phar',
            '/vendor/',
            '/web/bundles/',
        );

        try {
            $this->fs->dumpFile($this->projectDir.'/.gitignore', implode("\n", $gitIgnoreEntries)."\n");
        } catch (\Exception $e) {
            // don't throw an exception in case the .gitignore file cannot be created,
            // because this is just an enhancement, not something mandatory for the project
        }

        return $this;
    }

    /**
     * Utility method to show the number of bytes in a readable format.
     *
     * @param int $bytes The number of bytes to format
     *
     * @return string The human readable string of bytes (e.g. 4.32MB)
     */
    private function formatSize($bytes)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = $bytes ? floor(log($bytes, 1024)) : 0;
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return number_format($bytes, 2).' '.$units[$pow];
    }

    /**
     * Formats the error message contained in the given Requirement item
     * using the optional line length provided.
     *
     * @param \Requirement $requirement The Symfony requirements
     * @param int          $lineSize    The maximum line length
     *
     * @return string
     */
    private function getErrorMessage(\Requirement $requirement, $lineSize = 70)
    {
        if ($requirement->isFulfilled()) {
            return;
        }

        $errorMessage  = wordwrap($requirement->getTestMessage(), $lineSize - 3, PHP_EOL.'   ').PHP_EOL;
        $errorMessage .= '   > '.wordwrap($requirement->getHelpText(), $lineSize - 5, PHP_EOL.'   > ').PHP_EOL;

        return $errorMessage;
    }

    /**
     * Generates a good random value for Symfony's 'secret' option
     *
     * @return string
     */
    private function generateRandomSecret()
    {
        if (function_exists('openssl_random_pseudo_bytes')) {
            return hash('sha1', openssl_random_pseudo_bytes(23));
        }

        return hash('sha1', uniqid(mt_rand(), true));
    }

    /**
     * Returns the executed command.
     *
     * @return string
     */
    private function getExecutedCommand()
    {
        $version = '';
        if ('latest' !== $this->version) {
            $version = $this->version;
        }

        $pathDirs = explode(PATH_SEPARATOR, $_SERVER['PATH']);
        $executedCommand = $_SERVER['PHP_SELF'];
        $executedCommandDir = dirname($executedCommand);

        if (in_array($executedCommandDir, $pathDirs)) {
            $executedCommand = basename($executedCommand);
        }

        return sprintf('%s new %s %s', $executedCommand, $this->projectName, $version);
    }
}
