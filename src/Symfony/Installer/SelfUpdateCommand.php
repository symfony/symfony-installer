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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * This command is a direct port of the self-update command included
 * in the PHP-CS-Fixer library. See:
 * https://github.com/fabpot/PHP-CS-Fixer/blob/master/Symfony/CS/Console/Command/SelfUpdateCommand.php
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 * @author Stephane PY <py.stephane1@gmail.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class SelfUpdateCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('self-update')
            ->setAliases(array('selfupdate'))
            ->setDescription('Update the installer to the latest version.')
            ->setHelp('The <info>%command.name%</info> command updates the installer to the latest available version.')
        ;
    }

    /**
     * The self-update command is only available when using the installer via the PHAR file.
     */
    public function isEnabled()
    {
        return 'phar://' === substr(__DIR__, 0, 7);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fs = new Filesystem();

        $localVersion = $this->getApplication()->getVersion();

        if (false === $remoteVersion = @file_get_contents('http://get.sensiolabs.org/symfony.version')) {
            $output->writeln('<error>The new version of the Symfony Installer couldn\'t be downloaded from the server.</error>');

            return 1;
        }

        if ($localVersion === $remoteVersion) {
            $output->writeln('<info>Symfony Installer is already up to date.</info>');

            return 0;
        }

        $remoteFilename = 'http://symfony.com/installer';
        $localFilename = realpath($_SERVER['argv'][0]) ?: $_SERVER['argv'][0];
        $tempDir = is_writable(dirname($localFilename)) ? dirname($localFilename) : sys_get_temp_dir();

        // check for permissions in local filesystem before start downloading files
        if (!is_writable($localFilename)) {
            throw new \RuntimeException('Symfony Installer update failed: the "'.$localFilename.'" file could not be written');
        }
        if (!is_writable($tempDir)) {
            throw new \RuntimeException('Symfony Installer update failed: the "'.$tempDir.'" directory used to download the temporary file could not be written');
        }

        if (false === @file_get_contents($remoteFilename)) {
            $output->writeln('<error>The new version of the Symfony Installer couldn\'t be downloaded from the server.</error>');

            return 1;
        }

        try {
            $tempFilename = $tempDir.'/'.basename($localFilename, '.phar').'-temp.phar';

            $fs->copy($remoteFilename, $tempFilename);
            $fs->chmod($tempFilename, 0777 & ~umask());

            // creating a Phar instance for an existing file is not allowed
            // when the Phar extension is in readonly mode
            if (!ini_get('phar.readonly')) {
                // test the phar validity
                $phar = new \Phar($tempFilename);
                // free the variable to unlock the file
                unset($phar);
            }

            $fs->rename($tempFilename, $localFilename, true);

            $output->writeln('<info>Symfony Installer was successfully updated.</info>');

            return 0;
        } catch (\Exception $e) {
            if (!$e instanceof \UnexpectedValueException && !$e instanceof \PharException) {
                throw $e;
            }
            $fs->remove($tempFilename);
            $output->writeln(sprintf('<error>The downloaded file is corrupted (%s).</error>', $e->getMessage()));
            $output->writeln('<error>Please re-run the self-update command to try again.</error>');

            return 1;
        }
    }
}
