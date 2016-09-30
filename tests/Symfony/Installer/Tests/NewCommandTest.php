<?php

namespace Symfony\Installer\Tests;

use Symfony\Installer\NewCommand;

class NewCommandTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getProjectNames
     */
    public function testFixComposerPackageName($originalName, $expectedName)
    {
        $command = new NewCommand();
        $method = new \ReflectionMethod($command, 'fixComposerPackageName');
        $method->setAccessible(true);

        $fixedName = $method->invoke($command, $originalName);
        $this->assertSame($expectedName, $fixedName);
    }

    public function getProjectNames()
    {
        return [
            ['foobar', 'foobar'],
            ['áèîøūñ', 'aeioun'],
            ['çįßłŵž', 'cislwz'],
        ];
    }
}