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

use Distill\Chooser;
use Distill\Distill;
use Distill\Exception\IO\Input\FileCorruptedException;
use Distill\Exception\IO\Input\FileEmptyException;
use Distill\Exception\IO\Output\TargetDirectoryNotWritableException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Event\EmitterInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Installer\NewCommand;

/**
 * @runTestsInSeparateProcesses
 */
class NewCommandTest extends CommandTest
{
    /**
     * @var Distill|\PHPUnit_Framework_MockObject_MockObject
     */
    private $distill;

    /**
     * @var Chooser|\PHPUnit_Framework_MockObject_MockObject
     */
    private $chooser;

    /**
     * @var ClientInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $client;

    /**
     * @var EmitterInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $emitter;

    private $projectsDir;

    protected function setUp()
    {
        $this->chooser = $this->getMockBuilder('Distill\Chooser')
            ->disableOriginalConstructor()
            ->getMock();
        $this->chooser
            ->expects($this->any())
            ->method('setStrategy')
            ->willReturnSelf();
        $this->chooser
            ->expects($this->any())
            ->method('addFilesWithDifferentExtensions')
            ->willReturnSelf();
        $this->distill = $this->getMock('Distill\Distill');
        $this->distill
            ->expects($this->any())
            ->method('getChooser')
            ->willReturn($this->chooser);
        $this->emitter = $this->getMock('GuzzleHttp\Event\EmitterInterface');
        $this->client = $this->getMockBuilder('GuzzleHttp\ClientInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $this->client
            ->expects($this->any())
            ->method('getEmitter')
            ->willReturn($this->emitter);
        $this->client
            ->expects($this->any())
            ->method('get')
            ->willReturn($this->createDefaultResponse());

        $this->projectsDir = __DIR__.'/projects';

        parent::setUp();

        $this->cleanUpProjectDirectory();
    }

    protected function tearDown()
    {
        $this->cleanUpProjectDirectory();
    }

    public function testInstallLatest()
    {
        $this->distill
            ->expects($this->once())
            ->method('extractWithoutRootDirectory')
            ->willReturnCallback(function () {
                $fs = new Filesystem();
                $fs->mirror($this->projectsDir.'/skeleton', $this->projectsDir.'/bar');

                return true;
            });
        $this->executeCommand();
        $this->assertRegExp('/Symfony .+ was successfully installed/', $this->commandTester->getDisplay());
    }

    public function testInstallLatestPatchVersionForMinorVersion()
    {
        $this->distill
            ->expects($this->once())
            ->method('extractWithoutRootDirectory')
            ->willReturnCallback(function () {
                $fs = new Filesystem();
                $fs->mirror($this->projectsDir.'/skeleton', $this->projectsDir.'/bar');

                return true;
            });
        $this->executeCommand(array('version' => '2.3'));
        $this->assertRegExp('/Symfony .+ was successfully installed/', $this->commandTester->getDisplay());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage There is already a 'foo' project in this directory
     */
    public function testWithExistingDirectoryFails()
    {
        $this->executeCommand(array('directory' => $this->projectsDir.'/foo'));
    }

    /**
     * @dataProvider invalidVersionProvider
     */
    public function testInstallationFailsForInvalidVersion($version, $expectedMessageRegExp)
    {
        $this->setExpectedExceptionRegExp('\RuntimeException', '/'.$expectedMessageRegExp.'/s');
        $this->executeCommand(array('version' => $version));
    }

    public function invalidVersionProvider()
    {
        return array(
            'patch-version-too-high' => array('2.3.100', 'Symfony version should be 2\.N or 2\.N\.M, where N = 0\.\.9 and M = 0\.\.99'),
            'unmaintained-version' => array('2.2.0', 'The selected version \(2\.2\.0\) cannot be installed.*unmaintained Symfony branch'),
            '2.3-version-too-low' => array('2.3.20', 'The selected version \(2\.3\.20\) cannot be installed.*compatible with Symfony 2\.3 versions starting from 2\.3\.21'),
            '2.5-version-too-low' => array('2.5.5', 'The selected version \(2\.5\.5\) cannot be installed.*compatible with Symfony 2\.5 versions starting from 2\.5\.6'),
        );
    }

    /**
     * @dataProvider extractionExceptionProvider
     */
    public function testInstallationFailsOnExtractionException(\Exception $e, $expectedMessageRegExp)
    {
        $this->setExpectedExceptionRegExp('\RuntimeException', '/'.$expectedMessageRegExp.'/s');
        $this->distill
            ->expects($this->once())
            ->method('extractWithoutRootDirectory')
            ->will($this->throwException($e));
        $this->executeCommand();
    }

    public function extractionExceptionProvider()
    {
        return array(
            'file-corrupted' => array(new FileCorruptedException('foo'), 'downloaded package is corrupted'),
            'file-empty' => array(new FileEmptyException('foo'), 'downloaded package is empty'),
            'target-directory-not-writable' => array(new TargetDirectoryNotWritableException('foo'), 'permissions to uncompress and rename.*check the permissions'),
            'generic-exception' => array(new \Exception(), 'downloaded package is corrupted.+enough permissions to uncompress.+check the permissions'),
        );
    }

    public function testInstallationFailsWhenExtractionFails()
    {
        $this->setExpectedExceptionRegExp('\RuntimeException', '/the downloaded package is corrupted.+uncompress commands of your operating system didn\'t work/s');
        $this->distill
            ->expects($this->once())
            ->method('extractWithoutRootDirectory')
            ->will($this->returnValue(false));
        $this->executeCommand();
    }

    protected function createCommand()
    {
        return new NewCommand($this->distill, $this->client);
    }

    protected function executeCommand(array $optionsAndArguments = array())
    {
        parent::executeCommand(array_merge(array('directory' => $this->projectsDir.'/bar'), $optionsAndArguments));
    }

    private function createDefaultResponse()
    {
        return $this->getMock('GuzzleHttp\Message\ResponseInterface');
    }

    private function cleanUpProjectDirectory()
    {
        $fs = new Filesystem();

        if ($fs->exists($this->projectsDir.'/bar')) {
            $fs->remove($this->projectsDir.'/bar');
        }
    }
}
