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
    /**
     * @var string The root directory
     */
    private $rootDir;

    /**
     * @var Filesystem The Filesystem component
     */
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
        if (PHP_VERSION_ID < 50500) {
            $this->markTestSkipped('Symfony 3 requires PHP 5.5.9 or higher.');
        }

        $projectDir = sprintf('%s/my_test_project', sys_get_temp_dir());
        $this->fs->remove($projectDir);

        $output = $this->runCommand(sprintf('php symfony.phar demo %s', ProcessUtils::escapeArgument($projectDir)));
        $this->assertContains('Downloading the Symfony Demo Application', $output);
        $this->assertContains('Symfony Demo Application was successfully installed.', $output);

        $composerConfig = json_decode(file_get_contents($projectDir.'/composer.json'), true);
        $this->assertArrayNotHasKey('platform', $composerConfig['config'], 'The composer.json file does not define any platform configuration.');
    }

    /**
     * @dataProvider provideSymfonyInstallationData
     */
    public function testSymfonyInstallation($versionToInstall, $messageRegexp, $versionRegexp, $requiredPhpVersion)
    {
        if (version_compare(PHP_VERSION, $requiredPhpVersion, '<')) {
            $this->markTestSkipped(sprintf('This test requires PHP %s or higher.', $requiredPhpVersion));
        }

        $projectDir = sprintf('%s/my_test_project', sys_get_temp_dir());
        $this->fs->remove($projectDir);

        $output = $this->runCommand(sprintf('php symfony.phar new %s %s', ProcessUtils::escapeArgument($projectDir), $versionToInstall));
        $this->assertContains('Downloading Symfony...', $output);
        $this->assertRegExp($messageRegexp, $output);

        if (file_exists($projectDir.'/app/console')) {
            $output = $this->runCommand('php app/console --version', $projectDir);
        } else {
            $output = $this->runCommand('php bin/console --version', $projectDir);
        }

        $this->assertRegExp($versionRegexp, $output);

        $composerConfig = json_decode(file_get_contents($projectDir.'/composer.json'), true);
        $this->assertArrayNotHasKey(
            isset($composerConfig['config']) ? 'platform' : 'config',
            isset($composerConfig['config']) ? $composerConfig['config'] : $composerConfig,
            'The composer.json file does not define any platform configuration.'
        );
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessageRegExp /.+The selected version \(3.0.0\) cannot be installed because it requires.+PHP 5.5.9 or higher and your system has PHP 5.4.* installed.+/s
     */
    public function testSymfonyRequiresNewerPhpVersion()
    {
        if (PHP_VERSION_ID >= 50500) {
            $this->markTestSkipped('This test requires PHP 5.4 or lower.');
        }

        $this->runCommand(sprintf('php %s/symfony.phar new my_test_project 3.0.0', $this->rootDir));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessageRegExp /.+The Symfony Installer is not compatible with Symfony 4\.x or newer versions.*Run this other command to install Symfony using Composer instead:.*composer create-project symfony\/skeleton .+/s
     */
    public function testUseComposerToInstallSymfony4()
    {
        if (PHP_VERSION_ID < 50500) {
            $this->markTestSkipped('This test requires PHP 5.5 or newer.');
        }

        $this->runCommand(sprintf('php %s/symfony.phar new my_test_project', $this->rootDir));
    }

    public function testSymfonyInstallationInCurrentDirectory()
    {
        $projectDir = sprintf('%s/my_test_project', sys_get_temp_dir());
        $this->fs->remove($projectDir);
        $this->fs->mkdir($projectDir);

        $output = $this->runCommand(sprintf('php %s/symfony.phar new . 2.7.5', $this->rootDir), $projectDir);
        $this->assertContains('Downloading Symfony...', $output);

        $output = $this->runCommand('php app/console --version', $projectDir);
        $this->assertContains('Symfony version 2.7.5 - app/dev/debug', $output);
    }

    public function testSymfonyDemoInstallationWithNewCommand()
    {
        if (PHP_VERSION_ID < 50500) {
            $this->markTestSkipped('This test requires PHP 5.5 or higher.');
        }

        $output = $this->runCommand(sprintf('php %s/symfony.phar new demo 3.4', $this->rootDir));
        $this->assertContains("If you want to download the Symfony Demo app, execute 'symfony demo' instead of 'symfony new demo'", $output);
        $this->fs->remove('demo');
    }

    /**
     * Runs the given string as a command and returns the resulting output.
     * The CWD is set to the root project directory to simplify command paths.
     *
     * @param string      $command          The name of the command to execute
     * @param null|string $workingDirectory The working directory
     *
     * @return string The output of the command
     *
     * @throws ProcessFailedException If the command execution is not successful
     */
    private function runCommand($command, $workingDirectory = null)
    {
        $process = new Process($command);
        $process->setWorkingDirectory($workingDirectory ?: $this->rootDir);
        $process->mustRun();

        return $process->getOutput();
    }

    /**
     * Provides Symfony installation data.
     *
     * @return array
     */
    public function provideSymfonyInstallationData()
    {
        return array(
            array(
                '3.0',
                '/.*Symfony 3\.0\.\d+ was successfully installed.*/',
                '/Symfony version 3\.0\.\d+(-DEV)? - app\/dev\/debug/',
                '5.5.9',
            ),

            array(
                'lts',
                '/.*Symfony 3\.4\.\d+ was successfully installed.*/',
                '/Symfony 3\.4\.\d+ \(kernel: app, env: dev, debug: true\)/',
                '5.5.9',
            ),

            array(
                '2.3',
                '/.*Symfony 2\.3\.\d+ was successfully installed.*/',
                '/Symfony version 2\.3\.\d+ - app\/dev\/debug/',
                '5.3.9',
            ),

            array(
                '2.5.6',
                '/.*Symfony 2\.5\.6 was successfully installed.*/',
                '/Symfony version 2\.5\.6 - app\/dev\/debug/',
                '5.3.9',
            ),

            array(
                '2.7.0-BETA1',
                '/.*Symfony 2\.7\.0\-BETA1 was successfully installed.*/',
                '/Symfony version 2\.7\.0\-BETA1 - app\/dev\/debug/',
                '5.3.9',
            ),

            array(
                '3.0.0-BETA1',
                '/.*Symfony dev\-master was successfully installed.*/',
                '/Symfony version 3\.0\.0\-BETA1 - app\/dev\/debug/',
                '5.5.9',
            ),
        );
    }
}
