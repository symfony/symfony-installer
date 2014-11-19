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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        preg_match('/\((.*?)\)$/', $this->getApplication()->getLongVersion(), $match);
        $localVersion = isset($match[1]) ? $match[1] : '';

        if (false !== $remoteVersion = @file_get_contents('http://get.sensiolabs.org/symfony-installer.version')) {
            if ($localVersion === $remoteVersion) {
                $output->writeln('<info>Symfony Installer is already up to date.</info>');

                return;
            }
        }

        $remoteFilename = 'http://symfony.com/installer';
        $localFilename = $_SERVER['argv'][0];
        $tempFilename = basename($localFilename, '.phar').'-tmp.phar';
        if (false === @file_get_contents($remoteFilename)) {
            $output->writeln('<error>The new version of the Symfony Installer couldn\'t be downloaded from the server.</error>');

            return 1;
        }

        try {
            copy($remoteFilename, $tempFilename);
            chmod($tempFilename, 0777 & ~umask());

            // test the phar validity
            $phar = new \Phar($tempFilename);
            // free the variable to unlock the file
            unset($phar);
            rename($tempFilename, $localFilename);

            $output->writeln('<info>Symfony Installer was successfully updated.</info>');
        } catch (\Exception $e) {
            if (!$e instanceof \UnexpectedValueException && !$e instanceof \PharException) {
                throw $e;
            }
            unlink($tempFilename);
            $output->writeln(sprintf('<error>The downloaded file is corrupted (%s).</error>', $e->getMessage()));
            $output->writeln('<error>Please re-run the self-update command to try again.</error>');

            return 1;
        }
    }
}
