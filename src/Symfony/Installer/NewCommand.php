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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Install\Installer;
use Symfony\Install\InstallerInterface;

/**
 * This command creates new Symfony projects for the given Symfony version.
 *
 * @author Christophe Coevoet <stof@notk.org>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class NewCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('new')
            ->setDescription('Creates a new Symfony project.')
            ->addArgument('directory', InputArgument::REQUIRED, 'Directory where the new project will be created.')
            ->addArgument('version', InputArgument::OPTIONAL, 'The Symfony version to be installed (defaults to the latest stable version).', 'latest')
            ->addOption('installer', 'i', InputOption::VALUE_OPTIONAL, 'The file installer dir')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $installer = $input->getOption('installer');
        if ($installer) {
            $installer = require_once $installer;
        } else {
            $installer = new Installer();
        }
        if (!$installer instanceof InstallerInterface) {
            throw new \InvalidArgumentException('The installer object must be instance of \Symfony\Install\InstallerInterface');
        }
        $installer->initialize($input, $output, $this);
        $methods = $installer->getMethodsSequence();
        try {
            foreach ($methods as $method) {
                $installer->$method();
            }
        } catch (\Exception $e) {
            $installer->cleanUp();
            throw $e;
        }
    }
}
