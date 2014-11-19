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
        $commandHelp = <<<COMMAND_HELP

 Symfony Installer (%s)
 =======================

 This is the official installer to start new projects based on the
 Symfony full-stack framework.

 To create a new project called <info>blog</info> in the current directory using
 the <info>latest stable version</info> of Symfony, execute the following command:

   <comment>$ %s new blog</comment>

 To base your project on a <info>specific Symfony version</info>, append the version
 number at the end of the command:

   <comment>$ %s new blog 2.5.6</comment>

COMMAND_HELP;

        $output->writeln(sprintf($commandHelp, $this->appVersion, $_SERVER['PHP_SELF'], $_SERVER['PHP_SELF']));
    }
}
