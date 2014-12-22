<?php

/*
 * This file is part of the Symfony Installer package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Installer\Tests;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

abstract class CommandTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Application
     */
    protected $application;

    /**
     * @var Command
     */
    protected $command;

    /**
     * @var CommandTester
     */
    protected $commandTester;

    protected function setUp()
    {
        $this->command = $this->createCommand();
        $this->application = new Application();
        $this->application->add($this->command);
        $this->commandTester = new CommandTester($this->command);
    }

    /**
     * Creates the command to be tested.
     *
     * @return Command
     */
    abstract protected function createCommand();

    protected function executeCommand(array $optionsAndArguments = array())
    {
        $this->commandTester->execute(array_merge(array('command' => $this->command->getName()), $optionsAndArguments));
    }
}
