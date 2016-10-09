<?php

namespace Symfony\Installer\Tests\Manager;

use Symfony\Installer\Manager\ComposerManager;

class ComposerManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getProjectNames
     */
    public function testFixPackageName($originalName, $expectedName)
    {
        $composerManager = new ComposerManager(sys_get_temp_dir());
        $method = new \ReflectionMethod($composerManager, 'fixPackageName');
        $method->setAccessible(true);

        $fixedName = $method->invoke($composerManager, $originalName);
        $this->assertSame($expectedName, $fixedName);
    }

    public function getProjectNames()
    {
        return [
            ['foo/bar', 'foo/bar'],
            ['áèî/øū', 'aei/ou'],
            ['çñß/łŵž', 'cns/lwz'],
            ['foo#bar\foo?bar=foo!bar{foo]bar', 'foobarfoobarfoobarfoobar'],
            ['FOO/bar', 'foo/bar'],
        ];
    }
}
