<?php
namespace Splot\Framework\Tests\Application;

use Splot\Framework\Tests\Application\Fixtures\TestApplication;
use Splot\Framework\Framework;

/**
 * @coversDefaultClass Splot\Framework\Application\AbstractApplication
 */
class AbstractApplicationTest extends \PHPUnit_Framework_TestCase
{

    public function testLoadParameters() {

    }

    /**
     * @expectedException \RuntimeException
     * @covers ::bootstrap
     */
    public function testBootstrapCalledWrong() {
        $application = $this->provideApplication();
        $application->setPhase(Framework::PHASE_RUN);
        $application->bootstrap();
    }

    public function testConfigure() {

    }

    /**
     * @expectedException \RuntimeException
     * @covers ::configure
     */
    public function testConfigureCalledWrong() {
        $application = $this->provideApplication();
        $application->setPhase(Framework::PHASE_RUN);
        $application->configure();
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

        // check that container has been injected to the module
        $this->assertSame($mocks['container'], $module->getContainer());

        // make sure that the module has been added
        $this->assertTrue($application->hasModule('TestModule'));
        $this->assertSame($module, $application->getModule('TestModule'));
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

    public function testListModules() {

    }

    public function testGetModules() {

    }

    /**
     * @covers ::setContainer
     * @expectedException \RuntimeException
     */
    public function testSettingContainerTwice() {
        $container = $this->getMock('Splot\Framework\DependencyInjection\ServiceContainer');
        $application = $this->getMockForAbstractClass('Splot\Framework\Application\AbstractApplication');
        $application->setContainer($container);
        $application->setContainer($container);
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
     * @covers ::getConfig
     */
    public function testGetConfig() {

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
     * @covers ::__class
     */
    public function testGettingClass() {
        $this->assertEquals('Splot\\Framework\\Tests\\Application\\Fixtures\\TestApplication', TestApplication::__class());
    }

    protected function provideMocks() {
        $mocks = array();
        $mocks['container'] = $this->getMock('Splot\Framework\DependencyInjection\ServiceContainer');
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
