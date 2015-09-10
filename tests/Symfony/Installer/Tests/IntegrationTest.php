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

class IntegrationTest extends \PHPUnit_Framework_TestCase
{
    private static $rootDir = __DIR__.'/../../../../';
    private $fs;

    public function setUp()
    {
        $this->fs = new Filesystem();

        $this->fs->remove(self::$rootDir.'/symfony.phar');
        $this->runCommand('php box build');
    }

    public static function tearDownAfterClass()
    {
        $fs = new Filesystem();
        $fs->remove(self::$rootDir.'/symfony.phar');
    }

    public function testDemoApplicationInstallation()
    {
        $projectDir = sprintf('%s/my_test_project', sys_get_temp_dir());
        $this->fs->remove($projectDir);

        $output = $this->runCommand(sprintf('php symfony.phar demo %s', $projectDir));
        $this->assertContains('Downloading the Symfony Demo Application', $output);
        $this->assertContains('Symfony Demo Application was successfully installed.', $output);

        $output = $this->runCommand(sprintf('cd %s && php app/console --version', $projectDir));
        $this->assertRegExp('/Symfony version 2\.\d+\.\d+ - app\/dev\/debug/', $output);
    }

    /**
     * @dataProvider provideSymfonyInstallationData
     */
    public function testSymfonyInstallation($additionalArguments, $messageRegexp, $versionRegexp)
    {
        $projectDir = sprintf('%s/my_test_project', sys_get_temp_dir());
        $this->fs->remove($projectDir);

        $commonArguments = sprintf('new %s', $projectDir);
        $output = $this->runCommand(sprintf('php symfony.phar %s %s', $commonArguments, $additionalArguments));
        $this->assertContains('Downloading Symfony...', $output);
        $this->assertRegExp($messageRegexp, $output);

        $output = $this->runCommand(sprintf('cd %s && php app/console --version', $projectDir));
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
    private function runCommand($command)
    {
        $process = new Process($command);
        $process->setWorkingDirectory(self::$rootDir);
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
        );
    }
}
