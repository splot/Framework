<?php
namespace Splot\Framework\Tests\Application;

use Splot\Framework\Tests\Application\Fixtures\TestApplication;

/**
 * @coversDefaultClass Splot\Framework\Application\AbstractApplication
 */
class AbstractApplicationTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @expectedException \RuntimeException
     * @covers ::bootstrap
     * @covers ::finishBootstrap
     */
    public function testBootstrappingAfterBootstrapFinished() {
        $application = $this->getMockForAbstractClass('Splot\Framework\Application\AbstractApplication');
        $application->finishBootstrap();
        
        $application->bootstrap();
    }

    public function testLoadParameters() {

    }

    public function testAddModule() {

    }

    public function testConfigure() {

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
     * @covers ::getConfig
     */
    public function testGetConfig() {

    }

    public function testGetApplicationDir() {

    }

    /**
     * @covers ::getEnv
     */
    public function testGetEnv() {
        $container = $this->getMock('Splot\Framework\DependencyInjection\ServiceContainer');
        $application = $this->getMockForAbstractClass('Splot\Framework\Application\AbstractApplication');
        $application->setContainer($container);
        $container->expects($this->once())
            ->method('getParameter')
            ->with($this->equalTo('env'))
            ->will($this->returnValue('dev'));

        $this->assertEquals('dev', $application->getEnv());
    }

    /**
     * @covers ::isDebug
     */
    public function testIsDebug() {
        $container = $this->getMock('Splot\Framework\DependencyInjection\ServiceContainer');
        $application = $this->getMockForAbstractClass('Splot\Framework\Application\AbstractApplication');
        $application->setContainer($container);
        $container->expects($this->once())
            ->method('getParameter')
            ->with($this->equalTo('debug'))
            ->will($this->returnValue(true));

        $this->assertTrue($application->isDebug());
    }

    public function testListModules() {

    }

    public function testGetModules() {

    }

    public function testHasModule() {

    }

    public function testGetModule() {

    }

    public function testGettingClass() {
        $this->assertEquals('Splot\\Framework\\Tests\\Application\\Fixtures\\TestApplication', TestApplication::__class());
    }

}
