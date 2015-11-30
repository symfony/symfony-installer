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
use GuzzleHttp\Event\ProgressEvent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException;
use Symfony\Installer\Exception\AbortException;

/**
 * Abstract command used by commands which download and extract compressed Symfony files.
 *
 * @author Christophe Coevoet <stof@notk.org>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
abstract class DownloadCommand extends Command
{
    /**
     * @var Filesystem
     */
    protected $fs;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var string
     */
    protected $projectName;

    /**
     * @var string
     */
    protected $projectDir;

    /**
     * @var string
     */
    protected $version;

    /**
     * @var string
     */
    protected $downloadedFilePath;

    /**
     * @var array
     */
    protected $requirementsErrors = array();

    /**
     * Returns the type of the downloaded application in a human readable format.
     * It's mainly used to display readable error messages.
     *
     * @return string
     */
    abstract protected function getDownloadedApplicationType();

    /**
     * Returns the absolute URL of the remote file downloaded by the command.
     */
    abstract protected function getRemoteFileUrl();

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->fs = new Filesystem();

        $this->enableSignalHandler();

        $this->version = $input->hasArgument('version') ? trim($input->getArgument('version')) : 'latest';
    }

    /**
     * Chooses the best compressed file format to download (ZIP or TGZ) depending upon the
     * available operating system uncompressing commands and the enabled PHP extensions
     * and it downloads the file.
     *
     * @return $this
     *
     * @throws \RuntimeException if the Symfony archive could not be downloaded
     */
    protected function download()
    {
        $this->output->writeln(sprintf("\n Downloading %s...\n", $this->getDownloadedApplicationType()));

        // decide which is the best compressed version to download
        $distill = new Distill();
        $symfonyArchiveFile = $distill
            ->getChooser()
            ->setStrategy(new MinimumSize())
            ->addFilesWithDifferentExtensions($this->getRemoteFileUrl(), ['tgz', 'zip'])
            ->getPreferredFile()
        ;

        /** @var ProgressBar|null $progressBar */
        $progressBar = null;
        $downloadCallback = function (ProgressEvent $event) use (&$progressBar) {
            $downloadSize = $event->downloadSize;
            $downloaded = $event->downloaded;

            // progress bar is only displayed for files larger than 1MB
            if ($downloadSize < 1 * 1024 * 1024) {
                return;
            }

            if (null === $progressBar) {
                ProgressBar::setPlaceholderFormatterDefinition('max', function (ProgressBar $bar) {
                    return $this->formatSize($bar->getMaxSteps());
                });
                ProgressBar::setPlaceholderFormatterDefinition('current', function (ProgressBar $bar) {
                    return str_pad($this->formatSize($bar->getProgress()), 11, ' ', STR_PAD_LEFT);
                });

                $progressBar = new ProgressBar($this->output, $downloadSize);
                $progressBar->setFormat('%current%/%max% %bar%  %percent:3s%%');
                $progressBar->setRedrawFrequency(max(1, floor($downloadSize / 1000)));
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

        $client = $this->getGuzzleClient();

        // store the file in a temporary hidden directory with a random name
        $this->downloadedFilePath = rtrim(getcwd(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.'.uniqid(time()).DIRECTORY_SEPARATOR.'symfony.'.pathinfo($symfonyArchiveFile, PATHINFO_EXTENSION);

        try {
            $request = $client->createRequest('GET', $symfonyArchiveFile);
            $request->getEmitter()->on('progress', $downloadCallback);
            $response = $client->send($request);
        } catch (ClientException $e) {
            if ('new' === $this->getName() && ($e->getCode() === 403 || $e->getCode() === 404)) {
                throw new \RuntimeException(sprintf(
                    "The selected version (%s) cannot be installed because it does not exist.\n".
                    "Execute the following command to install the latest stable Symfony release:\n".
                    '%s new %s',
                    $this->version,
                    $_SERVER['PHP_SELF'],
                    str_replace(getcwd().DIRECTORY_SEPARATOR, '', $this->projectDir)
                ));
            } else {
                throw new \RuntimeException(sprintf(
                    "There was an error downloading %s from symfony.com server:\n%s",
                    $this->getDownloadedApplicationType(),
                    $e->getMessage()
                ), null, $e);
            }
        }

        $this->fs->dumpFile($this->downloadedFilePath, $response->getBody());

        if (null !== $progressBar) {
            $progressBar->finish();
            $this->output->writeln("\n");
        }

        return $this;
    }

    protected function checkProjectName()
    {
        if (is_dir($this->projectDir) && !$this->isEmptyDirectory($this->projectDir)) {
            throw new \RuntimeException(sprintf(
                "There is already a '%s' project in this directory (%s).\n".
                'Change your project name or create it in another directory.',
                $this->projectName, $this->projectDir
            ));
        }

        return $this;
    }

    /**
     * Returns the Guzzle client configured according to the system environment
     * (e.g. it takes into account whether it should use a proxy server or not).
     *
     * @return Client
     */
    protected function getGuzzleClient()
    {
        $defaults = array();

        // check if the client must use a proxy server
        if (!empty($_SERVER['HTTP_PROXY']) || !empty($_SERVER['http_proxy'])) {
            $proxy = !empty($_SERVER['http_proxy']) ? $_SERVER['http_proxy'] : $_SERVER['HTTP_PROXY'];
            $defaults['proxy'] = $proxy;
        }

        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
            $defaults['debug'] = true;
        }

        return new Client(array('defaults' => $defaults));
    }

    /**
     * Extracts the compressed Symfony file (ZIP or TGZ) using the
     * native operating system commands if available or PHP code otherwise.
     *
     * @return DownloadCommand
     *
     * @throws \RuntimeException if the downloaded archive could not be extracted
     */
    protected function extract()
    {
        $this->output->writeln(" Preparing project...\n");

        try {
            $distill = new Distill();
            $extractionSucceeded = $distill->extractWithoutRootDirectory($this->downloadedFilePath, $this->projectDir);
        } catch (FileCorruptedException $e) {
            throw new \RuntimeException(sprintf(
                "%s can't be installed because the downloaded package is corrupted.\n".
                "To solve this issue, try executing this command again:\n%s",
                ucfirst($this->getDownloadedApplicationType()), $this->getExecutedCommand()
            ));
        } catch (FileEmptyException $e) {
            throw new \RuntimeException(sprintf(
                "%s can't be installed because the downloaded package is empty.\n".
                "To solve this issue, try executing this command again:\n%s",
                ucfirst($this->getDownloadedApplicationType()), $this->getExecutedCommand()
            ));
        } catch (TargetDirectoryNotWritableException $e) {
            throw new \RuntimeException(sprintf(
                "%s can't be installed because the installer doesn't have enough\n".
                "permissions to uncompress and rename the package contents.\n".
                "To solve this issue, check the permissions of the %s directory and\n".
                "try executing this command again:\n%s",
                ucfirst($this->getDownloadedApplicationType()), getcwd(), $this->getExecutedCommand()
            ));
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf(
                "%s can't be installed because the downloaded package is corrupted\n".
                "or because the installer doesn't have enough permissions to uncompress and\n".
                "rename the package contents.\n".
                "To solve this issue, check the permissions of the %s directory and\n".
                "try executing this command again:\n%s",
                ucfirst($this->getDownloadedApplicationType()), getcwd(), $this->getExecutedCommand()
            ), null, $e);
        }

        if (!$extractionSucceeded) {
            throw new \RuntimeException(sprintf(
                "%s can't be installed because the downloaded package is corrupted\n".
                "or because the uncompress commands of your operating system didn't work.",
                ucfirst($this->getDownloadedApplicationType())
            ));
        }

        return $this;
    }

    /**
     * Checks if environment meets symfony requirements.
     *
     * @return $this
     */
    protected function checkSymfonyRequirements()
    {
        try {
            $requirementsDir = $this->isSymfony3() ? 'var' : 'app';
            require $this->projectDir.'/'.$requirementsDir.'/SymfonyRequirements.php';
            $symfonyRequirements = new \SymfonyRequirements();
            $this->requirementsErrors = array();
            foreach ($symfonyRequirements->getRequirements() as $req) {
                if ($helpText = $this->getErrorMessage($req)) {
                    $this->requirementsErrors[] = $helpText;
                }
            }
        } catch (MethodArgumentValueNotImplementedException $e) {
            // workaround https://github.com/symfony/symfony-installer/issues/163
        }

        return $this;
    }

    /**
     * Creates the appropriate .gitignore file for a Symfony project.
     *
     * @return $this
     */
    protected function createGitIgnore()
    {
        $gitIgnoreEntries = array(
            '/app/config/parameters.yml',
            '/bin/',
            '/build/',
            '/composer.phar',
            '/vendor/',
            '/web/bundles/',
        );
        if ($this->isSymfony3()) {
            $gitIgnoreEntries = array_merge($gitIgnoreEntries, array(
                '/var/',
                '!var/cache/.gitkeep',
                '!var/logs/.gitkeep',
                '!var/sessions/.gitkeep',
                '/phpunit.xml',
            ));
        } else {
            $gitIgnoreEntries = array_merge($gitIgnoreEntries, array(
                '/app/bootstrap.php.cache',
                '/app/cache/*',
                '!app/cache/.gitkeep',
                '/app/config/parameters.yml',
                '/app/logs/*',
                '!app/logs/.gitkeep',
                '/app/phpunit.xml',
            ));
        }

        try {
            $this->fs->dumpFile($this->projectDir.'/.gitignore', implode("\n", $gitIgnoreEntries)."\n");
        } catch (\Exception $e) {
            // don't throw an exception in case the .gitignore file cannot be created,
            // because this is just an enhancement, not something mandatory for the project
        }

        return $this;
    }

    /**
     * Checks if the installer has enough permissions to create the project.
     */
    protected function checkPermissions()
    {
        $projectParentDirectory = dirname($this->projectDir);

        if (!is_writable($projectParentDirectory)) {
            throw new IOException(sprintf('Installer does not have enough permissions to write to the "%s" directory.', $projectParentDirectory));
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
    protected function formatSize($bytes)
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
    protected function getErrorMessage(\Requirement $requirement, $lineSize = 70)
    {
        if ($requirement->isFulfilled()) {
            return;
        }

        $errorMessage = wordwrap($requirement->getTestMessage(), $lineSize - 3, PHP_EOL.'   ').PHP_EOL;
        $errorMessage .= '   > '.wordwrap($requirement->getHelpText(), $lineSize - 5, PHP_EOL.'   > ').PHP_EOL;

        return $errorMessage;
    }

    /**
     * Generates a good random value for Symfony's 'secret' option.
     *
     * @return string
     */
    protected function generateRandomSecret()
    {
        if (function_exists('openssl_random_pseudo_bytes')) {
            return hash('sha1', openssl_random_pseudo_bytes(23));
        }

        return hash('sha1', uniqid(mt_rand(), true));
    }

    /**
     * Returns the executed command with all its arguments
     * (e.g. "symfony new blog 2.3.6").
     *
     * @return string
     */
    protected function getExecutedCommand()
    {
        $commandBinary = $_SERVER['PHP_SELF'];
        $commandBinaryDir = dirname($commandBinary);
        $pathDirs = explode(PATH_SEPARATOR, $_SERVER['PATH']);
        if (in_array($commandBinaryDir, $pathDirs)) {
            $commandBinary = basename($commandBinary);
        }

        $commandName = $this->getName();

        if ('new' === $commandName) {
            $commandArguments = sprintf('%s %s', $this->projectName, ('latest' !== $this->version) ? $this->version : '');
        } elseif ('demo' === $commandName) {
            $commandArguments = '';
        }

        return sprintf('%s %s %s', $commandBinary, $commandName, $commandArguments);
    }

    /**
     * Checks whether the given directory is empty or not.
     *
     * @param string $dir the path of the directory to check
     *
     * @return bool
     */
    protected function isEmptyDirectory($dir)
    {
        // glob() cannot be used because it doesn't take into account hidden files
        // scandir() returns '.'  and '..'  for an empty dir
        return 2 === count(scandir($dir.'/'));
    }

    /**
     * Checks that the asked version is in the 3.x branch.
     *
     * @return bool
     */
    protected function isSymfony3()
    {
        return $this->version && '3' === $this->version[0];
    }

    private function enableSignalHandler()
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        declare(ticks = 1);

        pcntl_signal(SIGINT, function () {
            error_reporting(0);

            throw new AbortException();
        });
    }
}
