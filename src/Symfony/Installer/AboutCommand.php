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
 * This command provides information about the Symfony installer.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class AboutCommand extends Command
{
    private $appVersion;

    public function __construct($appVersion)
    {
        parent::__construct();

        $this->appVersion = $appVersion;
    }

    protected function configure()
    {
        $this
            ->setName('about')
            ->setDescription('Symfony Installer Help.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(sprintf("\n<info>Symfony Installer</info> <comment>%s</comment>", $this->appVersion));
        $output->writeln("=====================");

        $output->writeln(" The official Symfony installer to start new projects based on Symfony full-stack framework.\n");

        $output->writeln("Available commands");
        $output->writeln("------------------");

        $output->writeln("   <info>new <dir-name></info>  Creates a new Symfony project in the given directory.");
        $output->writeln("                   Example: <comment>$ symfony new blog/</comment>\n");
    }
}
