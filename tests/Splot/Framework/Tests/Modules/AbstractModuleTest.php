<?php
namespace Splot\Framework\Tests\Application;

use Splot\Framework\Modules\AbstractModule;

use Splot\Framework\Config\Config;
use Splot\Framework\DependencyInjection\ServiceContainer;

use Splot\Framework\Tests\Application\Fixtures\TestApplication;
use Splot\Framework\Tests\Modules\Fixtures\TestModule;
use Splot\Framework\Tests\Modules\Fixtures\NamedModule;


class AbstractModuleTest extends \PHPUnit_Framework_TestCase
{

    public function testBoot() {
        $module = new TestModule();
        $module->boot();
    }

    public function testInit() {
        $module = new TestModule();
        $module->init();
    }

    public function testGetName() {
        $module = new TestModule();
        $this->assertEquals('TestModule', $module->getName());

        $namedModule = new NamedModule();
        $this->assertEquals('SplotTestNamedModule', $namedModule->getName());
    }

    public function testSettingAndGettingConfig() {
        $config = new Config(array());
        $module = new TestModule();

        $module->setConfig($config);
        $this->assertSame($config, $module->getConfig());
    }

    public function testSettingAndGettingApplication() {
        $app = new TestApplication();
        $module = new TestModule();

        $module->setApplication($app);
        $this->assertSame($app, $module->getApplication());
    }

    public function testSettingAndGettingContainer() {
        $container = new ServiceContainer();
        $module = new TestModule();

        $module->setContainer($container);
        $this->assertSame($container, $module->getContainer());
    }

    public function testGetUrlPrefix() {
        $module = new TestModule();
        $this->assertEmpty($module->getUrlPrefix());

        $module2 = new NamedModule();
        $this->assertNotEmpty($module2->getUrlPrefix());
    }

    public function testGetCommandNamespace() {
        $module = new TestModule();
        $this->assertEmpty($module->getCommandNamespace());

        $module2 = new NamedModule();
        $this->assertNotEmpty($module2->getCommandNamespace());
    }

    public function testGetClass() {
        $module = new TestModule();
        $this->assertEquals('Splot\\Framework\\Tests\\Modules\\Fixtures\\TestModule', $module->getClass());
        $this->assertEquals('Splot\\Framework\\Tests\\Modules\\Fixtures\\TestModule', $module->getClass());
    }

    public function testGetNamespace() {
        $module = new TestModule();
        $this->assertEquals('Splot\\Framework\\Tests\\Modules\\Fixtures', $module->getNamespace());
        $this->assertEquals('Splot\\Framework\\Tests\\Modules\\Fixtures', $module->getNamespace());
    }

    public function testGetModuleDir() {
        $module = new TestModule();
        $moduleDir = realpath(dirname(__FILE__) .'/Fixtures') .'/';
        $this->assertEquals($moduleDir, $module->getModuleDir());
        $this->assertEquals($moduleDir, $module->getModuleDir());
    }

}
