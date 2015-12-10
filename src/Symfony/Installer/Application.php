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

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Application as ConsoleApplication;

/**
 * This is main Symfony Installer console application class.
 *
 * @author Jerzy Zawadzki <zawadzki.jerzy@gmail.com>
 */
class Application extends ConsoleApplication
{
    const VERSIONS_URL = 'https://get.symfony.com/symfony.version';

    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $commandName = $this->getCommandName($input);

        if ($this->isInPharMode() && in_array($commandName, array('new', 'demo'), true)) {
            if (!$this->checkIfInstallerIsUpdated()) {
                $output->writeln(sprintf(
                    " <comment>[WARNING]</comment> Your Symfony Installer version is outdated.\n".
                    ' Execute the command "%s selfupdate" to get the latest version.',
                    $_SERVER['PHP_SELF']
                ));
            }
        }

        return parent::doRun($input, $output);
    }

    public function isInPharMode()
    {
        return 'phar://' === substr(__DIR__, 0, 7);
    }

    private function checkIfInstallerIsUpdated()
    {
        $localVersion = $this->getVersion();

        if (false === $remoteVersion = @file_get_contents(self::VERSIONS_URL)) {
            // as this is simple checking - we don't care here if versions file is unavailable
            return true;
        }

        if (version_compare($localVersion, $remoteVersion, '>=')) {
            return true;
        }

        return false;
    }
}
