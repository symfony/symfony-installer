<?php

namespace Symfony\Installer;

use Distill\Distill;
use Distill\Strategy\MinimumSize;
use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Subscriber\Progress\Progress;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * This command creates new Symfony projects for the given Symfony version.
 *
 * @author Christophe Coevoet <stof@notk.org>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class NewCommand extends Command
{
    /**
     * @var Filesystem
     */
    private $fs;
    private $projectName;
    private $projectDir;
    private $version;
    private $compressedFilePath;

    protected function configure()
    {
        $this
            ->setName('new')
            ->setDescription('Creates a new Symfony project.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the directory where the new project will be created')
            ->addArgument('version', InputArgument::OPTIONAL, 'The Symfony version to be installed (defaults to the latest stable version)', 'latest')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->fs = new Filesystem();
        $this->projectName = trim($input->getArgument('name'));
        $this->projectDir = rtrim(getcwd().DIRECTORY_SEPARATOR.$this->projectName, DIRECTORY_SEPARATOR);
        $this->version = trim($input->getArgument('version'));
        $this->output = $output;

        $this
            ->checkInstalledPhpVersion()
            ->checkProjectName()
            ->checkSymfonyVersionIsInstallable()
            ->download()
            ->extract()
            ->cleanUp()
            ->displayInstallationResult()
        ;
    }

    /**
     * Checks if the system has PHP 5.4 or higher installed, which is a requirement
     * to execute the installer.
     */
    private function checkInstalledPhpVersion()
    {
        if (version_compare(PHP_VERSION, '5.4.0', '<')) {
            throw new \RuntimeException(sprintf(
                "Symfony Installer requires PHP 5.4 version or higher and your system has\n".
                "PHP %s version installed.\n\n".
                "To solve this issue, upgrade your PHP installation or install Symfony manually\n".
                "executing the following command:\n\n".
                "composer create-project symfony/framework-standard-edition %s %s",
                PHP_VERSION,
                $this->projectName,
                'latest' !== $this->version ? $this->version : ''
            ));
        }

        return $this;
    }

    /**
     * Checks whether it's safe to create a new project for the given name in the
     * given directory.
     */
    private function checkProjectName()
    {
        if (is_dir($this->projectDir)) {
            throw new \RuntimeException(sprintf(
                "There is already a '%s' project in this directory (%s).\n".
                "Change your project name or create it in another directory.",
                $this->projectName, getcwd()
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
     * @throws \RuntimeException If the given Symfony version is not compatible with this installer.
     */
    private function checkSymfonyVersionIsInstallable()
    {
        // 'latest' is a special version name that refers to the latest stable version
        // available at the moment of installing Symfony
        if ('latest' === $this->version) {
            return $this;
        }

        // validate semver syntax
        if (!preg_match('/^2\.\d\.\d{1,2}$/', $this->version)) {
            throw new \RuntimeException('The Symfony version should be 2.N.M, where N = 0..9 and M = 0..99');
        }

        // 2.0, 2.1, 2.2 and 2.4 cannot be installed because they are unmaintained
        if (preg_match('/^2\.[0124]\.\d{1,2}$/', $this->version)) {
            throw new \RuntimeException(sprintf(
                "The selected version (%s) cannot be installed because it belongs\n".
                "to an unmaintained Symfony branch which is not compatible with this installer.\n".
                "To solve this issue install Symfony manually executing the following command:\n\n".
                "composer create-project symfony/framework-standard-edition %s %s",
                $this->version, $this->projectName, $this->version
            ));
        }

        // 2.3 can be installed starting from version 2.3.21 (inclusive)
        if (preg_match('/^2\.3\.\d{1,2}$/', $this->version) && version_compare($this->version, '2.3.21', '<')) {
            throw new \RuntimeException(sprintf(
                "The selected version (%s) cannot be installed because this installer\n".
                "is compatible with Symfony 2.3 versions starting from 2.3.21.\n".
                "To solve this issue install Symfony manually executing the following command:\n\n".
                "composer create-project symfony/framework-standard-edition %s %s",
                $this->version, $this->projectName, $this->version
            ));
        }

        // 2.5 can be installed starting from version 2.5.6 (inclusive)
        if (preg_match('/^2\.5\.\d{1,2}$/', $this->version) && version_compare($this->version, '2.5.6', '<')) {
            throw new \RuntimeException(sprintf(
                "The selected version (%s) cannot be installed because this installer\n".
                "is compatible with Symfony 2.5 versions starting from 2.5.6.\n".
                "To solve this issue install Symfony manually executing the following command:\n\n".
                "composer create-project symfony/framework-standard-edition %s %s",
                $this->version, $this->projectName, $this->version
            ));
        }

        return $this;
    }

    /**
     * Chooses the best compressed file format to download (ZIP or TGZ) depending upon the
     * available operating system uncompressing commands and the enabled PHP extensions
     * and it downloads the file.
     *
     * @throws \Distill\Exception\FormatGuesserRequiredException
     * @throws \Distill\Exception\StrategyRequiredException
     */
    private function download()
    {
        $this->output->writeln("\n Downloading Symfony...");

        // decide which is the best compressed version to download
        $distill = new Distill();
        $symfonyArchiveFile = $distill
            ->getChooser()
            ->setStrategy(new MinimumSize())
            ->addFile('http://symfony.com/download?v=Symfony_Standard_Vendors_'.$this->version.'.zip')
            ->addFile('http://symfony.com/download?v=Symfony_Standard_Vendors_'.$this->version.'.tgz')
            ->getPreferredFile()
        ;

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

            $progressBar->setCurrent($downloaded);
        };

        $client = new Client();
        $client->getEmitter()->attach(new Progress(null, $downloadCallback));

        // store the file in a temporary hidden directory with a random name
        $this->compressedFilePath = getcwd().DIRECTORY_SEPARATOR.'.'.uniqid(time()).DIRECTORY_SEPARATOR.'symfony.'.pathinfo($symfonyArchiveFile, PATHINFO_EXTENSION);

        $response = $client->get($symfonyArchiveFile);
        $this->fs->dumpFile($this->compressedFilePath, $response->getBody());

        if (null !== $progressBar) {
            $progressBar->finish();
            $this->output->writeln("\n");
        }

        return $this;
    }

    /**
     * Extracts the compressed Symfony file (ZIP or TGZ) using the
     * native operating system commands if available or PHP code otherwise.
     */
    private function extract()
    {
        $this->output->writeln(" Preparing project...\n");

        try {
            $distill = new Distill();
            $extractionSucceeded = $distill->extractWithoutRootDirectory($this->compressedFilePath, $this->projectDir);
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf(
                "Symfony can't be installed because the downloaded package is corrupted\n".
                "or because the installer doesn't have enough permissions to uncompress and\n".
                "rename the package contents.\n\n".
                "To solve this issue, try installing Symfony again and check the permissions of\n".
                "the %s directory",
                getcwd()
            ));
        }

        if (!$extractionSucceeded) {
            throw new \RuntimeException(
                "Symfony can't be installed because the downloaded package is corrupted\n".
                "or because the uncompress commands of your operating system didn't work."
            );
        }

        return $this;
    }

    /**
     * Removes all the temporary files and directories created to
     * download and extract Symfony.
     */
    private function cleanUp()
    {
        $this->fs->remove(dirname($this->compressedFilePath));

        return $this;
    }

    /**
     * It displays the message with the result of installing Symfony
     * and provides some pointers to the user.
     */
    private function displayInstallationResult()
    {
        $this->output->writeln(sprintf(
            " <info>%s</info>  Symfony was <info>successfully installed</info>. Now you can:\n".
            "\n".
            "    * Change your current directory to %s.\n".
            "    * Configure your application in <comment>app/config/parameters.yml</comment> file.\n\n".
            "    * Run your application:\n".
            "        1. Execute the <comment>php app/console server:run</comment> command.\n".
            "        2. Browse to the <comment>http://localhost:8000</comment> URL.\n\n".
            "    * Read the documentation at <comment>http://symfony.com/doc</comment>\n",
            defined('PHP_WINDOWS_VERSION_BUILD') ? 'OK' : '✔', $this->projectName
        ));

        return $this;
    }

    /**
     * Utility method to show the number of bytes in a readable format.
     *
     * @param $bytes The number of bytes to format
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
}
