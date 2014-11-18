<?php

namespace Symfony\Installer;

use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Subscriber\Progress\Progress;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use ZipArchive;

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

    protected function configure()
    {
        $this
            ->setName('new')
            ->setDescription('Creates a new Symfony project.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the directory where the new project will be created')
            // TODO: symfony.com/download should provide a latest.zip version to simplify things
            ->addArgument('version', InputArgument::OPTIONAL, 'The Symfony version to be installed (defaults to the latest stable version)', '2.5.3')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (version_compare(PHP_VERSION, '5.4.0', '<')) {
            $message = <<<MESSAGE
Symfony Installer requires PHP 5.4 version or higher and your system has
PHP %s version installed.

To solve this issue, upgrade your PHP installation or install Symfony manually.
To do so, make sure that your system has Composer installed and execute the
following command:

$ composer create-project symfony/framework-standard-edition %s
MESSAGE;
            $output->writeln(sprintf($message, PHP_VERSION, $input->getArgument('name')));

            return 1;
        }

        $this->fs = new Filesystem();

        if (is_dir($dir = rtrim(getcwd().DIRECTORY_SEPARATOR.$input->getArgument('name'), DIRECTORY_SEPARATOR))) {
            throw new \RuntimeException(sprintf("Project directory already exists:\n%s", $dir));
        }

        $symfonyVersion = $input->getArgument('version');

        $this->isSymfonyVersionInstallable($symfonyVersion, $input->getArgument('name'));

        $this->fs->mkdir($dir);

        $output->writeln("\n Downloading Symfony...");

        $zipFilePath = $dir.DIRECTORY_SEPARATOR.'.symfony_'.uniqid(time()).'.zip';

        $this->download($zipFilePath, $symfonyVersion, $output);

        $output->writeln(' Preparing project...');

        $this->extract($zipFilePath, $dir);

        $this->cleanUp($zipFilePath, $dir);

        $message = <<<MESSAGE

 <info>✔</info>  Symfony was <info>successfully installed</info>. Now you can:

    * Configure your application in <comment>app/config/parameters.yml</comment> file.

    * Run your application:
        1. Execute the <comment>php app/console server:run</comment> command.
        2. Browse to the <comment>http://localhost:8000</comment> URL.

    * Read the documentation at <comment>http://symfony.com/doc</comment>

MESSAGE;

        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $message = str_replace('✔', 'OK', $message);
        }

        $output->writeln($message);
    }

    /**
     * Checks whether the given Symfony version is installable by the installer.
     * The rules to decide if a version is installable depend on the changes
     * introduced for the ICU/Intl components, and are as follows:
     *
     *   - 2.0, 2.1, 2.2 and 2.4 cannot be installed because they are unmaintained.
     *   - 2.3 can be installed starting from version 2.3.21 (inclusive)
     *   - 2.5 can be installed starting from version 2.5.6 (inclusive)
     *   - 2.6, 2.7, 2.8 and 2.9 can be installed regardless the version.
     *
     * @param  string $version     The symfony version to install
     * @param  string $projectName The name of the new Symfony project to create
     *
     * @return  bool               True if the given version can be installed with the installer.
     *                             False otherwise.
     *
     * @throws \RuntimeException   If the given Symfony version is not compatible with this installer.
     */
    private function isSymfonyVersionInstallable($version, $projectName)
    {
        // 'latest' is a special version name that refers to the latest stable version
        // available at the moment of installing Symfony
        if ('latest' === $version) {
            return true;
        }

        // validate semver syntax
        if (!preg_match('/^2\.\d\.\d{1,2}$/', $version)) {
            throw new \RuntimeException('The Symfony version should be 2.N.M, where N = 0..9 and M = 0..99');
        }

        // 2.0, 2.1, 2.2 and 2.4 cannot be installed because they are unmaintained
        if (preg_match('/^2\.[0124]\.\d{1,2}$/', $version)) {
            throw new \RuntimeException(sprintf(
                "The selected version (%s) cannot be installed because it belongs\n".
                "to an unmaintained Symfony branch which is not compatible with this installer.\n".
                "To solve this issue install Symfony manually executing the following command:\n\n".
                "composer create-project symfony/framework-standard-edition %s %s",
                $version, $projectName, $version
            ));
        }

        // 2.3 can be installed starting from version 2.3.21 (inclusive)
        if (preg_match('/^2\.3\.\d{1,2}$/', $version) && version_compare($version, '2.3.21', '<')) {
            throw new \RuntimeException(sprintf(
                "The selected version (%s) cannot be installed because this installer\n".
                "is compatible with Symfony 2.3 versions starting from 2.3.21.\n".
                "To solve this issue install Symfony manually executing the following command:\n\n".
                "composer create-project symfony/framework-standard-edition %s %s",
                $version, $projectName, $version
            ));
        }

        // 2.5 can be installed starting from version 2.5.6 (inclusive)
        if (preg_match('/^2\.5\.\d{1,2}$/', $version) && version_compare($version, '2.5.6', '<')) {
            throw new \RuntimeException(sprintf(
                "The selected version (%s) cannot be installed because this installer\n".
                "is compatible with Symfony 2.5 versions starting from 2.5.6.\n".
                "To solve this issue install Symfony manually executing the following command:\n\n".
                "composer create-project symfony/framework-standard-edition %s %s",
                $version, $projectName, $version
            ));
        }

        return true;
    }

    private function download($targetPath, $symfonyVersion, OutputInterface $output)
    {
        $progressBar = null;
        $downloadCallback = function ($size, $downloaded, $client, $request, Response $response) use ($output, &$progressBar) {
            // Don't initialize the progress bar for redirects as the size is much smaller
            if ($response->getStatusCode() >= 300) {
                return;
            }

            if (null === $progressBar) {
                ProgressBar::setPlaceholderFormatterDefinition('max', function (ProgressBar $bar) {
                    return $this->formatSize($bar->getMaxSteps());
                });
                ProgressBar::setPlaceholderFormatterDefinition('current', function (ProgressBar $bar) {
                    return str_pad($this->formatSize($bar->getStep()), 10, ' ', STR_PAD_LEFT);
                });
                $progressBar = new ProgressBar($output, $size);
                $progressBar->setRedrawFrequency(max(1, floor($size / 1000)));

                if (!defined('PHP_WINDOWS_VERSION_BUILD')) {
                    $progressBar->setEmptyBarCharacter('░'); // light shade character \u2591
                    $progressBar->setProgressCharacter('');
                    $progressBar->setBarCharacter('▓'); // dark shade character \u2593
                }

                $progressBar->setBarWidth(60);

                $progressBar->start();
            }

            $progressBar->setCurrent($downloaded);
        };

        $client = new Client();
        $client->getEmitter()->attach(new Progress(null, $downloadCallback));

        $response = $client->get('http://symfony.com/download?v=Symfony_Standard_Vendors_'.$symfonyVersion.'.zip');
        $this->fs->dumpFile($targetPath, $response->getBody());

        if (null !== $progressBar) {
            $progressBar->finish();
            $output->writeln("\n");
        }

        return $this;
    }

    private function extract($zipFilePath, $projectDir)
    {
        $archive = new ZipArchive();

        $archive->open($zipFilePath);
        $archive->extractTo($projectDir);
        $archive->close();

        $extractionDir = $projectDir.DIRECTORY_SEPARATOR.'Symfony';

        $iterator = new \FilesystemIterator($extractionDir);

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            $subPath = $this->fs->makePathRelative($file->getRealPath(), $extractionDir);
            if (!is_dir($file)) {
                $subPath = rtrim($subPath, '/');
            }

            $this->fs->rename($file->getRealPath(), $projectDir.DIRECTORY_SEPARATOR.$subPath);
        }

        return $this;
    }

    private function cleanUp($zipFile, $projectDir)
    {
        $this->fs->remove($zipFile);
        $this->fs->remove($projectDir.DIRECTORY_SEPARATOR.'Symfony');

        return $this;
    }

    private function formatSize($bytes)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = $bytes ? floor(log($bytes, 1024)) : 0;
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2).' '.$units[$pow];
    }
}
