<?php
/**
 * Symfony14ConfiguratorTest class.
 *
 * @author Benjamin Grandfond <benjamin.grandfond@gmail.com>
 * @since 06/11/11
 */

namespace Symfttpd\Tests\Configurator;

use Symfttpd\Tests\Test as BasetestCase;
use Symfttpd\Configurator\Symfony14Configurator;
use Symfttpd\Configuration\SymfttpdConfiguration;
use Symfttpd\Filesystem\Filesystem;

class Symfony14ConfiguratorTest extends BaseTestCase
{
    protected $filesystem;
    protected $projectPath;

    public function setUp()
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->cleanUp();

        $this->projectPath = $this->fixtures.'/symfony-1.4';

        $this->configurator = new Symfony14Configurator();

        $this->configuration = new SymfttpdConfiguration();
    }

    public function tearDown()
    {
        $this->cleanUp();
    }

    public function testConfigure()
    {
        $this->markTestSkipped('I have to find a way to mock a symfony project.');

        $this->configurator->configure($this->projectPath, $this->configuration->all());

        $this->assertTrue(file_exists($this->projectPath.'/cache'), $this->projectPath.'/cache exists');
        $this->assertTrue(file_exists($this->projectPath.'/log'), $this->projectPath.'/log exists');
        $this->assertTrue(is_link($this->projectPath.'/web/sf'), $this->projectPath.'/web/sf exists and is a symlink');
        $this->assertTrue(is_link($this->projectPath.'/web/sfDoctrinePlugin'), $this->projectPath.'/web/sfDoctrinePlugin exists and is a symlink');
        $this->assertTrue(is_link($this->projectPath.'/web/sfFormExtraPlugin'), $this->projectPath.'/web/sfFormExtraPlugin exists and is a symlink');
    }

    public function testConfigureException()
    {
        $this->setExpectedException('Symfttpd\Configurator\Exception\ConfiguratorException');
        $this->configurator->configure(__DIR__, $this->configuration->all());
    }

    protected function cleanUp()
    {
        $this->filesystem->remove(array(
            $this->projectPath.'/cache',
            $this->projectPath.'/log',
            $this->projectPath.'/web/sf',
            $this->projectPath.'/web/sfDoctrinePlugin',
            $this->projectPath.'/web/sfFormExtraPlugin',
        ));
    }
}
