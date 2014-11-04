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
 * This command provides information about the Symfony installer.
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
        $this->fs = new Filesystem();

        if (is_dir($dir = getcwd().DIRECTORY_SEPARATOR.$input->getArgument('name'))) {
            throw new \RuntimeException(sprintf("Project directory already exists:\n%s", $dir));
        }

        $symfonyVersion = $input->getArgument('version');
        if (!preg_match('/^2\.[0-7]\.\d{1,2}$/', $symfonyVersion)) {
            throw new \RuntimeException('The Symfony version should be 2.N.M, where N = 0..7 and M = 0..99');
        }

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
                    $progressBar->setEmptyBarCharacter('░');
                    $progressBar->setProgressCharacter('▏');
                    $progressBar->setBarCharacter('▋');
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
        $archive = new ZipArchive;

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

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
