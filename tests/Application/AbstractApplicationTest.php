<?php
namespace Splot\Framework\Tests\Application;

use Splot\Framework\Tests\Application\Fixtures\TestApplication;
use Splot\Framework\Framework;

use Splot\Framework\Tests\MockTrait;

/**
 * @coversDefaultClass Splot\Framework\Application\AbstractApplication
 */
class AbstractApplicationTest extends \PHPUnit_Framework_TestCase
{
    use MockTrait;

    /**
     * @covers ::provideContainerCache
     */
    public function testProvideContainerCache() {
        $application = $this->provideApplication();

        $this->assertInstanceOf('Splot\DependencyInjection\ContainerCacheInterface', $application->provideContainerCache('test', true));
    }

    /**
     * @covers ::loadParameters
     */
    public function testLoadParameters() {
        $application = $this->provideApplication();
        $this->assertInternalType('array', $application->loadParameters('test', true));
    }

    /**
     * @covers ::run
     */
    public function testRun() {
        $application = $this->provideApplication();
        $application->run();
    }

    /**
     * @covers ::addModule
     * @covers ::hasModule
     * @covers ::getModule
     */
    public function testAddModule() {
        $mocks = $this->provideMocks();
        $application = $this->provideApplication($mocks);
        $module = $this->provideModule('TestModule');

        $application->addModule($module);

        // make sure that the module has been added
        $this->assertTrue($application->hasModule('TestModule'));
        $this->assertSame($module, $application->getModule('TestModule'));
    }

    /**
     * @covers ::getModules
     */
    public function testGetModules() {
        $application = $this->provideApplication();
        $modules = array(
            'TestModule' => $this->provideModule('TestModule'),
            'LipsumModule' => $this->provideModule('LipsumModule'),
            'WebModule' => $this->provideModule('WebModule'),
            'CommandsModule' => $this->provideModule('CommandsModule')
        );
        foreach($modules as $module) {
            $application->addModule($module);
        }

        $this->assertEquals($modules, $application->getModules());
    }

    /**
     * @covers ::listModules
     */
    public function testListModules() {
        $application = $this->provideApplication();
        $modules = array(
            'TestModule' => $this->provideModule('TestModule'),
            'LipsumModule' => $this->provideModule('LipsumModule'),
            'WebModule' => $this->provideModule('WebModule'),
            'CommandsModule' => $this->provideModule('CommandsModule')
        );
        foreach($modules as $module) {
            $application->addModule($module);
        }

        $this->assertEquals(array_keys($modules), $application->listModules());
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\NotUniqueException
     * @covers ::addModule
     */
    public function testAddingDuplicateModule() {
        $application = $this->provideApplication();
        $module = $this->provideModule('DupeModule');
        $application->addModule($module);

        $module2 = $this->provideModule('DupeModule');
        $application->addModule($module2);
    }

    /**
     * @expectedException \RuntimeException
     * @covers ::addModule
     */
    public function testAddModuleCalledWrong() {
        $application = $this->provideApplication();
        $application->setPhase(Framework::PHASE_CONFIGURE);
        $module = $this->getMockForAbstractClass('Splot\Framework\Modules\AbstractModule');
        $application->addModule($module);
    }

    /**
     * @covers ::setContainer
     * @covers ::getContainer
     */
    public function testSettingAndGettingContainer() {
        $container = $this->getMock('Splot\DependencyInjection\ContainerInterface');
        $application = $this->getMockForAbstractClass('Splot\Framework\Application\AbstractApplication');
        $application->setContainer($container);

        $this->assertSame($container, $application->getContainer());
    }

    /**
     * @covers ::setContainer
     * @expectedException \RuntimeException
     */
    public function testSettingContainerTwice() {
        $container = $this->getMock('Splot\DependencyInjection\ContainerInterface');
        $application = $this->getMockForAbstractClass('Splot\Framework\Application\AbstractApplication');
        $application->setContainer($container);
        $application->setContainer($container);
    }

    /**
     * @covers ::getConfig
     */
    public function testGetConfig() {
        $mocks = $this->provideMocks();
        $application = $this->provideApplication($mocks);

        $config = $this->getMock('Splot\Framework\Config\Config');

        $mocks['container']->expects($this->once())
            ->method('get')
            ->with($this->equalTo('config'))
            ->will($this->returnValue($config));

        $this->assertSame($config, $application->getConfig());
    }

    /**
     * @covers ::getName
     */
    public function testGetName() {
        $app = new TestApplication();
        $this->assertAttributeEquals($app->getName(), 'name', $app);
    }

    /**
     * @covers ::getVersion
     */
    public function testGetVersion() {
        $app = new TestApplication();
        $this->assertAttributeEquals($app->getVersion(), 'version', $app);
    }

    /**
     * @covers ::getApplicationDir
     */
    public function testGetApplicationDir() {
        $mocks = $this->provideMocks();
        $application = $this->provideApplication($mocks);
        $mocks['container']->expects($this->once())
            ->method('getParameter')
            ->with($this->equalTo('application_dir'))
            ->will($this->returnValue(__DIR__));

        $this->assertEquals(__DIR__, $application->getApplicationDir());
    }

    /**
     * @covers ::getEnv
     */
    public function testGetEnv() {
        $mocks = $this->provideMocks();
        $application = $this->provideApplication($mocks);
        $mocks['container']->expects($this->once())
            ->method('getParameter')
            ->with($this->equalTo('env'))
            ->will($this->returnValue('dev'));

        $this->assertEquals('dev', $application->getEnv());
    }

    /**
     * @covers ::isDebug
     */
    public function testIsDebug() {
        $mocks = $this->provideMocks();
        $application = $this->provideApplication($mocks);
        $mocks['container']->expects($this->once())
            ->method('getParameter')
            ->with($this->equalTo('debug'))
            ->will($this->returnValue(true));

        $this->assertTrue($application->isDebug());
    }

    /**
     * @covers ::setLogger
     * @covers ::getLogger
     */
    public function testSettingAndGettingLogger() {
        $application = $this->provideApplication();
        $logger = $this->getMock('Psr\Log\LoggerInterface');
        $application->setLogger($logger);
        $this->assertSame($logger, $application->getLogger());
    }

    /**
     * @covers ::setPhase
     * @covers ::getPhase
     */
    public function testSettingAndGettingPhase() {
        $application = $this->provideApplication();
        $application->setPhase(Framework::PHASE_RUN);
        $this->assertEquals(Framework::PHASE_RUN, $application->getPhase());
    }

    /**
     * @expectedException \RuntimeException
     * @covers ::setPhase
     */
    public function testSettingEarlierPhase() {
        $application = $this->provideApplication();
        $application->setPhase(Framework::PHASE_RUN);
        $this->assertEquals(Framework::PHASE_RUN, $application->getPhase());
        $application->setPhase(Framework::PHASE_BOOTSTRAP);
    }

    /**
     * @covers ::__class
     */
    public function testGettingClass() {
        $this->assertEquals('Splot\\Framework\\Tests\\Application\\Fixtures\\TestApplication', TestApplication::__class());
    }

    protected function provideMocks() {
        $mocks = array();
        $mocks['container'] = $this->getMock('Splot\DependencyInjection\ContainerInterface');
        return $mocks;
    }

    protected function provideModule($name) {
        $module = $this->getMockForAbstractClass('Splot\Framework\Modules\AbstractModule', array(), '', true, true, true, array('getName'));
        $module->expects($this->any())
            ->method('getName')
            ->will($this->returnValue($name));
        return $module;
    }

    protected function provideApplication(array $mocks = array()) {
        $mocks = empty($mocks) ? $this->provideMocks() : $mocks;
        $application = $this->getMockForAbstractClass('Splot\Framework\Application\AbstractApplication');
        $application->setContainer($mocks['container']);
        return $application;
    }

}
