<?php
namespace Splot\Framework\Tests\Application;

use Splot\Framework\Tests\Application\Fixtures\TestApplication;

/**
 * @coversDefaultClass Splot\Framework\Application\AbstractApplication
 */
class AbstractApplicationTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @covers ::bootstrap
     * @covers ::setContainer
     * @covers ::getContainer
     * @covers ::setLogger
     * @covers ::getLogger
     */
    public function testBootstrap() {
        $container = $this->getMock('Splot\Framework\DependencyInjection\ServiceContainer');
        $application = $this->getMockForAbstractClass('Splot\Framework\Application\AbstractApplication');
        $application->setContainer($container);

        $this->assertSame($container, $application->getContainer());

        $parameters = array();
        $services = array();
        $container->expects($this->any())
            ->method('setParameter')
            ->will($this->returnCallback(function($name, $val) use (&$parameters) {
                $parameters[] = $name;
                return null;
            }));
        $container->expects($this->any())
            ->method('set')
            ->will($this->returnCallback(function($name, $val) use (&$services) {
                $services[] = $name;
                return null;
            }));
        $container->expects($this->any())
            ->method('getParameters')
            ->will($this->returnValue(array()));

        $loggerProvider = $this->getMock('Splot\Framework\Log\LoggerProviderInterface');
        $logger = $this->getMock('Psr\Log\LoggerInterface');
        $loggerProvider->expects($this->any())
            ->method('provide')
            ->will($this->returnValue($logger));
        $container->expects($this->any())
            ->method('get')
            ->with($this->equalTo('logger_provider'))
            ->will($this->returnValue($loggerProvider));

        $application->bootstrap();

        // expected parameters
        foreach(array(
            'application_dir',
            'root_dir',
            'config_dir',
            'cache_dir',
            'web_dir'
        ) as $name) {
            $this->assertContains($name, $parameters, 'Bootstrap didnt set "'. $name .'" parameter.');
        }

        // expected services
        foreach(array(
            'clog',
            'logger_provider',
            'logger',
            'event_manager',
            'router',
            'resource_finder',
            'process',
            'console'
        ) as $name) {
            $this->assertContains($name, $services, 'Bootstrap didnt set "'. $name .'" service.');
        }

        // also make sure logger has been injected
        $this->assertInstanceOf('Psr\Log\LoggerInterface', $application->getLogger());
    }

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
