<?php

/**
 * @author: Renier Ricardo Figueredo
 * @mail: aprezcuba24@gmail.com
 */
namespace Symfony\Install;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface InstallerInterface
{
    public function initialize(InputInterface $input, OutputInterface $output, Command $command);
    public function getMethodsSequence();
    public function cleanUp();
}
