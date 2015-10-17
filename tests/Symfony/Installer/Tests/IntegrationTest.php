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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ProcessUtils;

class IntegrationTest extends \PHPUnit_Framework_TestCase
{
    private $rootDir;
    private $fs;

    public function setUp()
    {
        $this->rootDir = realpath(__DIR__.'/../../../../');
        $this->fs = new Filesystem();

        if (!$this->fs->exists($this->rootDir.'/symfony.phar')) {
            throw new \RuntimeException(sprintf("Before running the tests, make sure that the Symfony Installer is available as a 'symfony.phar' file in the '%s' directory.", $this->rootDir));
        }
    }

    public function testDemoApplicationInstallation()
    {
        $projectDir = sprintf('%s/my_test_project', sys_get_temp_dir());
        $this->fs->remove($projectDir);

        $output = $this->runCommand(sprintf('php symfony.phar demo %s', ProcessUtils::escapeArgument($projectDir)));
        $this->assertContains('Downloading the Symfony Demo Application', $output);
        $this->assertContains('Symfony Demo Application was successfully installed.', $output);

        $output = $this->runCommand('php app/console --version', $projectDir);
        $this->assertRegExp('/Symfony version 2\.\d+\.\d+ - app\/dev\/debug/', $output);
    }

    /**
     * @dataProvider provideSymfonyInstallationData
     */
    public function testSymfonyInstallation($commandArguments, $messageRegexp, $versionRegexp)
    {
        $projectDir = sprintf('%s/my_test_project', sys_get_temp_dir());
        $this->fs->remove($projectDir);

        $output = $this->runCommand(sprintf('php symfony.phar new %s %s', ProcessUtils::escapeArgument($projectDir), $commandArguments));
        $this->assertContains('Downloading Symfony...', $output);
        $this->assertRegExp($messageRegexp, $output);

        $output = $this->runCommand('php app/console --version', $projectDir);
        $this->assertRegExp($versionRegexp, $output);
    }

    public function testSymfonyInstallationInCurrentDirectory()
    {
        $projectDir = sprintf('%s/my_test_project', sys_get_temp_dir());
        $this->fs->remove($projectDir);

        $this->runCommand(sprintf('cd %s', ProcessUtils::escapeArgument($projectDir)));

        $output = $this->runCommand('php symfony.phar new .');
        $this->assertContains('Downloading Symfony...', $output);
        $this->assertRegExp($messageRegexp, $output);

        $output = $this->runCommand('php app/console --version', $projectDir);
        $this->assertRegExp($versionRegexp, $output);
    }

    /**
     * Runs the given string as a command and returns the resulting output.
     * The CWD is set to the root project directory to simplify command paths.
     *
     * @param string $command
     *
     * @return string
     *
     * @throws ProcessFailedException in case the command execution is not successful
     */
    private function runCommand($command, $workingDirectory = null)
    {
        $process = new Process($command);
        $process->setWorkingDirectory($workingDirectory ?: $this->rootDir);
        $process->mustRun();

        return $process->getOutput();
    }

    public function provideSymfonyInstallationData()
    {
        return array(
            array(
                '',
                '/.*Symfony 2\.7\.\d+ was successfully installed.*/',
                '/Symfony version 2\.7\.\d+ - app\/dev\/debug/',
            ),

            array(
                'lts',
                '/.*Symfony 2\.7\.\d+ was successfully installed.*/',
                '/Symfony version 2\.7\.\d+ - app\/dev\/debug/',
            ),

            array(
                '2.3',
                '/.*Symfony 2\.3\.\d+ was successfully installed.*/',
                '/Symfony version 2\.3\.\d+ - app\/dev\/debug/',
            ),

            array(
                '2.5.6',
                '/.*Symfony 2\.5\.6 was successfully installed.*/',
                '/Symfony version 2\.5\.6 - app\/dev\/debug/',
            ),

            array(
                '2.7.0-BETA1',
                '/.*Symfony 2\.7\.0\-BETA1 was successfully installed.*/',
                '/Symfony version 2\.7\.0\-BETA1 - app\/dev\/debug/',
            ),
        );
    }
}
