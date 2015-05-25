<?php
namespace Splot\Framework\Tests\Testing\Stubs;

use Splot\DependencyInjection\Container;

use Splot\Framework\Testing\Stubs\TestApplication;
use Splot\Framework\Framework;

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
        $this->assertInternalType('array', $application->loadModules('test', true));
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
     * @covers ::setPhase
     */
    public function testSettingEarlierPhase() {
        $application = new TestApplication();

        $application->setPhase(Framework::PHASE_RUN);
        $this->assertEquals(Framework::PHASE_RUN, $application->getPhase());

        $application->setPhase(Framework::PHASE_BOOTSTRAP);
        $this->assertEquals(Framework::PHASE_BOOTSTRAP, $application->getPhase());
    }

    /**
     * @covers ::addTestModule
     */
    public function testAddingTestModule() {
        $framework = new Framework();
        $application = new TestApplication();
        $framework->configureApplication($application);

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