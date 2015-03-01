<?php
/**
 * Created by PhpStorm.
 * User: renier
 * Date: 28/02/15
 * Time: 22:31
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