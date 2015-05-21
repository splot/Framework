<?php
namespace Splot\Framework\Tests\Testing\Stubs;

use Splot\Framework\Testing\Stubs\TestApplication;
use Splot\DependencyInjection\Container;

/**
 * @coversDefaultClass \Splot\Framework\Testing\Stubs\TestApplication
 */
class TestApplicationTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @covers ::loadModules
     */
    public function testLoadModules() {
        $application = new TestApplication();
        $this->assertInternalType('array', $application->loadModules());
    }

    /**
     * @covers ::provideContainerCache
     */
    public function testProvidingContainerCacheWithMemoryStore() {
        $application = new TestApplication();
        $cache = $application->provideContainerCache('test', true);

        $this->assertInstanceOf('Splot\DependencyInjection\ContainerCacheInterface', $cache);
        
        $store = $cache->getStore();
        $this->assertInstanceOf('Splot\Cache\Store\MemoryStore', $store);
    }

    /**
     * @covers ::addTestModule
     */
    public function testAddingTestModule() {
        $application = new TestApplication();
        $application->setContainer(new Container());

        $module = $this->getMockBuilder('Splot\Framework\Modules\AbstractModule')
            ->setMethods(array('getName', 'configure', 'run', 'setContainer'))
            ->getMock();
        $module->expects($this->any())
            ->method('getName')
            ->will($this->returnValue('TestModule'));

        // make sure that container is injected to the module
        $module->expects($this->once())
            ->method('setContainer')
            ->with($this->callback(function($container) use ($application) {
                return $container === $application->getContainer();
            }));

        // make sure that module is allowed to be configured
        $module->expects($this->once())
            ->method('configure');
        
        // make sure that module is allowed to be run
        $module->expects($this->once())
            ->method('run');

        $application->addTestModule($module, array(
            'test_setting' => 'proper value',
            'another_setting' => true
        ));

        $this->assertTrue($application->hasModule('TestModule'));
        $this->assertSame($module, $application->getModule('TestModule'));

        // make sure that config is set in the container and that config contains passed options
        $container = $application->getContainer();
        $this->assertTrue($container->has('config.TestModule'));
        $config = $container->get('config.TestModule');
        $this->assertInstanceOf('Splot\Framework\Config\Config', $config);
        $this->assertEquals('proper value', $config->get('test_setting'));
        $this->assertEquals(true, $config->get('another_setting'));
    }

}