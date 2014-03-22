<?php
namespace Splot\Framework\Tests\Application;

use Splot\Framework\Modules\AbstractModule;

use Splot\Framework\Tests\Modules\Fixtures\TestModule;
use Splot\Framework\Tests\Modules\Fixtures\NamedModule;

/**
 * @coversDefaultClass Splot\Framework\Modules\AbstractModule
 */
class AbstractModuleTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @covers ::loadModules
     */
    public function testLoadModules() {
        $module = $this->getMockForAbstractClass('Splot\Framework\Modules\AbstractModule');
        $this->assertInternalType('array', $module->loadModules());
    }

    /**
     * @covers ::configure
     */
    public function testConfigure() {
        $container = $this->getMock('Splot\Framework\DependencyInjection\ServiceContainer');
        $module = $this->getMockForAbstractClass('Splot\Framework\Modules\AbstractModule');
        $module->setContainer($container);

        $router = $this->getMockBuilder('Splot\Framework\Routes\Router')
            ->disableOriginalConstructor()
            ->getMock();
        $router->expects($this->once())
            ->method('readModuleRoutes')
            ->with($this->equalTo($module));

        $container->expects($this->atLeastOnce())
            ->method('get')
            ->with($this->equalTo('router'))
            ->will($this->returnValue($router));

        $module->configure();
    }

    /**
     * @covers ::run
     */
    public function testRun() {
        $container = $this->getMock('Splot\Framework\DependencyInjection\ServiceContainer');
        $module = $this->getMockForAbstractClass('Splot\Framework\Modules\AbstractModule');
        $module->setContainer($container);

        $module->run();
    }

    /**
     * @covers ::getName
     */
    public function testGetName() {
        $module = new TestModule();
        $this->assertEquals('TestModule', $module->getName());

        $namedModule = new NamedModule();
        $this->assertEquals('SplotTestNamedModule', $namedModule->getName());
    }

    /**
     * @covers ::setConfig
     * @covers ::getConfig
     */
    public function testSettingAndGettingConfig() {
        $config = $this->getMockBuilder('Splot\Framework\Config\Config')
            ->disableOriginalConstructor()
            ->getMock();

        $module = $this->getMockForAbstractClass('Splot\Framework\Modules\AbstractModule');

        $module->setConfig($config);
        $this->assertSame($config, $module->getConfig());
    }

    /**
     * @covers ::setContainer
     * @covers ::getContainer
     */
    public function testSettingAndGettingContainer() {
        $container = $this->getMock('Splot\Framework\DependencyInjection\ServiceContainer');
        $module = $this->getMockForAbstractClass('Splot\Framework\Modules\AbstractModule');
        $module->setContainer($container);

        $this->assertSame($container, $module->getContainer());
    }

    /**
     * @covers ::getUrlPrefix
     */
    public function testGetUrlPrefix() {
        $module = new TestModule();
        $this->assertEmpty($module->getUrlPrefix());

        $module2 = new NamedModule();
        $this->assertNotEmpty($module2->getUrlPrefix());
    }

    /**
     * @covers ::getCommandNamespace
     */
    public function testGetCommandNamespace() {
        $module = new TestModule();
        $this->assertEmpty($module->getCommandNamespace());

        $module2 = new NamedModule();
        $this->assertNotEmpty($module2->getCommandNamespace());
    }

    /**
     * @covers ::getClass
     */
    public function testGetClass() {
        $module = new TestModule();
        $this->assertEquals('Splot\\Framework\\Tests\\Modules\\Fixtures\\TestModule', $module->getClass());
        $this->assertEquals('Splot\\Framework\\Tests\\Modules\\Fixtures\\TestModule', $module->getClass());
    }

    /**
     * @covers ::getNamespace
     */
    public function testGetNamespace() {
        $module = new TestModule();
        $this->assertEquals('Splot\\Framework\\Tests\\Modules\\Fixtures', $module->getNamespace());
        $this->assertEquals('Splot\\Framework\\Tests\\Modules\\Fixtures', $module->getNamespace());
    }

    /**
     * @covers ::getConfigDir
     */
    public function testGetConfigDir() {
        $module = new TestModule();
        $configDir = realpath(dirname(__FILE__) .'/Fixtures') .'/Resources/config/';
        $this->assertEquals($configDir, $module->getConfigDir());
    }

    /**
     * @covers ::getModuleDir
     */
    public function testGetModuleDir() {
        $module = new TestModule();
        $moduleDir = realpath(dirname(__FILE__) .'/Fixtures') .'/';
        $this->assertEquals($moduleDir, $module->getModuleDir());
        $this->assertEquals($moduleDir, $module->getModuleDir());
    }

}
