<?php
namespace Splot\Framework\Tests;

use Splot\Cache\Store\MemoryStore;
use Splot\Framework\DependencyInjection\ContainerCache;
use Splot\Framework\Framework;

/**
 * @coversDefaultClass \Splot\Framework\Framework
 */
class FrameworkTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @covers ::warmup
     */
    public function testWarmup() {
        $application = $this->getMockBuilder('Splot\Framework\Application\AbstractApplication')
            ->setMethods(array('getName', 'loadModules'))
            ->getMock();
        $application->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('WarmupApplication'));            

        $framework = $this->getMockBuilder('Splot\Framework\Framework')
            ->setMethods(array('bootstrapApplication', 'configureApplication'))
            ->getMock();

        // assert that bootstrap is called
        $framework->expects($this->once())
            ->method('bootstrapApplication')
            ->with($this->equalTo($application));

        $container = $this->getMock('Splot\DependencyInjection\ContainerInterface');
        
        // assert that configure is called
        $framework->expects($this->once())
            ->method('configureApplication')
            ->with($this->equalTo($application), $this->equalTo('test'), $this->equalTo(true))
            ->will($this->returnValue($container));
        
        // assert that set splot.timer is called on the container
        $container->expects($this->once())
            ->method('set')
            ->with($this->equalTo('splot.timer'), $this->callback(function($timer) {
                return $timer instanceof \MD\Foundation\Debug\Timer;
            }));
        
        // assert that container is returned
        $result = $framework->warmup($application, 'test', true);
        $this->assertSame($container, $result);
    }

    /**
     * @expectedException \RuntimeException
     * @covers ::warmup
     */
    public function testWarmupValidatingEmptyName() {
        $application = $this->getMockBuilder('Splot\Framework\Application\AbstractApplication')
            ->setMethods(array('getName', 'loadModules'))
            ->getMock();
        $application->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue(5));

        $framework = new Framework();
        $framework->warmup($application);
    }

    /**
     * @expectedException \RuntimeException
     * @covers ::warmup
     */
    public function testWarmupValidatingInvalidName() {
        $application = $this->getMockBuilder('Splot\Framework\Application\AbstractApplication')
            ->setMethods(array('getName', 'loadModules'))
            ->getMock();
        $application->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('Definetely not a class!'));

        $framework = new Framework();
        $framework->warmup($application);
    }

    /**
     * covers ::bootstrapApplication
     */
    public function testBootstrapApplication() {
        $application = $this->getMockForAbstractClass('Splot\Framework\Application\AbstractApplication');
        $application->expects($this->once())
            ->method('loadModules')
            ->will($this->returnValue(array(
                $this->provideModule('HobbitModule', array(
                    $this->provideModule('FrodoModule', array(
                        $this->provideModule('RingModule'),
                        $this->provideModule('SamModule', array(
                            $this->provideModule('FrodoModule', array(), true)
                        ))
                    )),
                    $this->provideModule('MerryModule', array(
                        $this->provideModule('PippinModule')
                    ))
                )),
                $this->provideModule('GandalfModule'),
                $this->provideModule('AragornModule', array(
                    $this->provideModule('GandalfModule', array(), true)
                )),
                $this->provideModule('LegolasModule', array(
                    $this->provideModule('GimliModule')
                )),
                $this->provideModule('GimliModule', array(), true),
                $this->provideModule('BoromirModule', array(
                    $this->provideModule('RingModule', array(), true)
                ))
            )));

        $framework = new Framework();
        $framework->bootstrapApplication($application);
        $this->assertEquals(Framework::PHASE_BOOTSTRAP, $application->getPhase());
        $this->assertEquals(array(
            'RingModule',
            'SamModule',
            'FrodoModule',
            'PippinModule',
            'MerryModule',
            'HobbitModule',
            'GandalfModule',
            'AragornModule',
            'GimliModule',
            'LegolasModule',
            'BoromirModule'
        ), $application->listModules());
    }

    /**
     * @covers ::configureApplication
     * @covers ::doConfigureApplication
     * @covers ::configureModule
     */
    public function testConfigureApplication($debug = true) {
        $application = $this->getMockBuilder('Splot\Framework\Application\AbstractApplication')
            ->setMethods(array('loadModules', 'loadParameters', 'provideContainerCache', 'getModules', 'configure'))
            ->getMock();

        $application->expects($this->once())
            ->method('configure');

        $application->expects($this->once())
            ->method('loadParameters')
            ->with($this->equalTo('test'), $this->equalTo($debug))
            ->will($this->returnValue(array(
                'root_param' => '/var/www',
                'config_dir' => '%application_dir%/conf',
                'application_dir' => __DIR__ .'/app'
            )));

        $containerCache = new ContainerCache(new MemoryStore());
        $application->expects($this->once())
            ->method('provideContainerCache')
            ->will($this->returnValue($containerCache));

        $modules = array(
            'one' => $this->provideModule('ModuleOne', array(), true),
            'two' => $this->provideModule('ModuleTwo', array(), true),
            'three' => $this->provideModule('ModuleThree', array(), true)
        );

        foreach($modules as $module) {
            $module->expects($this->once())
                ->method('configure');
            $module->expects($this->atLeastOnce())
                ->method('getConfigDir')
                ->will($this->returnValue(__DIR__ .'/app/conf/module'));
        }

        $application->expects($this->atLeastOnce())
            ->method('getModules')
            ->will($this->returnValue($modules));

        $framework = new Framework();
        $container = $framework->configureApplication($application, 'test', $debug);

        // cleanup after the container
        $container->get('whoops')->unregister();

        // assert the configuration
        $this->assertEquals(Framework::PHASE_CONFIGURE, $application->getPhase());
        $this->assertInstanceOf('Splot\DependencyInjection\ContainerInterface', $container);
        $this->assertSame($container, $application->getContainer());
        $this->assertSame($application, $container->get('application'));
        $this->assertSame($containerCache, $container->get('container.cache'));
        $this->assertInstanceOf('Psr\Log\LoggerInterface', $application->getLogger());
        $this->assertSame($container->get('logger'), $application->getLogger());

        // assert what was read from framework.yml
        $this->assertEquals('localhost', $container->getParameter('router.host'));
        $this->assertEquals('http://', $container->getParameter('router.protocol'));
        $this->assertEquals(80, $container->getParameter('router.port'));
        $this->assertEquals('debug', $container->getParameter('log_level'));
        $this->assertEquals('test', $container->getParameter('env'));
        $this->assertEquals('/var/www', $container->getParameter('root_param'));
        $this->assertEquals(__DIR__ .'/app/conf', $container->getParameter('config_dir'));
        $this->assertEquals(__DIR__ .'/app', $container->getParameter('application_dir'));
        $this->assertEquals($debug, $container->getParameter('debug'));

        // assert application config was loaded from files properly
        $this->assertSame($application->getConfig(), $container->get('config'));
        $config = $container->get('config');
        $this->assertEquals(array(
            realpath(__DIR__ .'/../src/config.yml'),
            __DIR__ .'/app/conf/config.yml',
            __DIR__ .'/app/conf/config.test.yml'
        ), $config->getLoadedFiles());
        $this->assertEquals('testing', $config->get('secret'));
        $this->assertEquals('working', $config->get('access'));
        $this->assertEquals(__DIR__ .'/app/log.test.txt', $container->getParameter('log_file'));

        foreach($modules as $module) {
            $this->assertSame($container, $module->getContainer());
            $this->assertTrue($container->has('config.'. $module->getName()));
            $this->assertInstanceOf('Splot\Framework\Config\Config', $container->get('config.'. $module->getName()));
            $moduleConfig = $container->get('config.'. $module->getName());
            $this->assertSame($moduleConfig, $module->getConfig());
            $this->assertEquals(array(
                __DIR__ .'/app/conf/module/config.yml',
                __DIR__ .'/app/conf/module/config.test.yml'
            ), $moduleConfig->getLoadedFiles());
            $this->assertEquals('keep-alive', $moduleConfig->get('connection'));
            $this->assertFalse($moduleConfig->get('module_enabled'));

            // module two has some things overwritten and added in app config
            if ($module->getName() === 'ModuleTwo') {
                $this->assertTrue($moduleConfig->get('module_hooked'));
                $this->assertEquals('agent_smith', $moduleConfig->get('additional_data'));
            }
        }

        return $application;
    }

    /**
     * @covers ::configureApplication
     */
    public function testConfigureApplicationStoringInCache() {
        $application = $this->getMockBuilder('Splot\Framework\Application\AbstractApplication')
            ->setMethods(array('loadModules', 'provideContainerCache', 'getModules', 'configure'))
            ->getMock();

        $application->expects($this->atLeastOnce())
            ->method('getModules')
            ->will($this->returnValue(array()));

        $application->expects($this->once())
            ->method('configure');

        $containerCache = $this->getMock('Splot\DependencyInjection\ContainerCacheInterface');

        // assert that data is attempted to be loaded but can't be       
        $containerCache->expects($this->once())
            ->method('load')
            ->will($this->returnValue(null));

        // assert that data is attempted to be saved
        $containerCache->expects($this->once())
            ->method('save')
            ->with($this->callback(function($data) {
                return !is_null($data) && !empty($data);
            }));

        $application->expects($this->once())
            ->method('provideContainerCache')
            ->will($this->returnValue($containerCache));

        $framework = new Framework();
        $framework->configureApplication($application, 'test', false);
    }

    /**
     * @covers ::configureApplication
     */
    public function testConfigureApplicationReadingFromCache() {
        // reuse another test to verify that stuff has been read and stored in cache in memory
        $application = $this->testConfigureApplication(false);
        $containerCache = $application->getContainer()->get('container.cache');

        // now try to again configure an application of the same name (but diff instance)
        // and make sure that `::configure` on that application isn't called
        // (which may be a pretty good indicator that no configuration is happening)
        $cachedApplication = $this->getMockBuilder('Splot\Framework\Application\AbstractApplication')
            ->setMethods(array('loadModules', 'provideContainerCache', 'configure'))
            ->getMock();

        $cachedApplication->expects($this->never())
            ->method('configure');

        $cachedApplication->expects($this->once())
            ->method('provideContainerCache')
            ->will($this->returnValue($containerCache));

        $framework = new Framework();
        $framework->configureApplication($cachedApplication, 'test', false);
    }

    /**
     * @covers ::runWebRequest
     * @covers ::doRunApplication
     */
    public function testRunWebRequest() {
        $request = $this->getMock('Splot\Framework\HTTP\Request');
        $response = $this->getMock('Splot\Framework\HTTP\Response');
        $application = $this->getMockBuilder('Splot\Framework\Application\AbstractApplication')
            ->setMethods(array('loadModules', 'run', 'handleRequest', 'sendResponse'))
            ->getMock();

        $application->expects($this->once())
            ->method('handleRequest')
            ->with($this->equalTo($request))
            ->will($this->returnValue($response));

        $application->expects($this->once())
            ->method('run');

        $application->expects($this->once())
            ->method('sendResponse')
            ->with($this->equalTo($response), $this->equalTo($request));

        $framework = new Framework();
        $framework->configureApplication($application);
        $result = $framework->runWebRequest($application, $request);

        $this->assertEquals(Framework::MODE_WEB, $application->getContainer()->getParameter('mode'));
        $this->assertSame($response, $result);
    }

    /**
     * @covers ::runCommand
     * @covers ::doRunApplication
     */
    public function testRunTest() {
        $application = $this->getMockBuilder('Splot\Framework\Application\AbstractApplication')
            ->setMethods(array('loadModules', 'getModules', 'run'))
            ->getMock();

        $modules = array(
            'one' => $this->provideModule('ModuleOne', array(), true),
            'two' => $this->provideModule('ModuleTwo', array(), true),
            'three' => $this->provideModule('ModuleThree', array(), true)
        );

        foreach($modules as $module) {
            $module->expects($this->once())
                ->method('run');
        }

        $application->expects($this->atLeastOnce())
            ->method('getModules')
            ->will($this->returnValue($modules));

        $framework = new Framework();
        $framework->configureApplication($application);
        $framework->runTest($application);

        $this->assertEquals(Framework::MODE_TEST, $application->getContainer()->getParameter('mode'));
    }

    /**
     * @covers ::phaseName
     */
    public function testPhaseName() {
        $this->assertEquals('bootstrap', Framework::phaseName(Framework::PHASE_BOOTSTRAP));
        $this->assertEquals('configure', Framework::phaseName(Framework::PHASE_CONFIGURE));
        $this->assertEquals('run', Framework::phaseName(Framework::PHASE_RUN));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @covers ::phaseName
     */
    public function testInvalidPhaseName() {
        Framework::phaseName(345);
    }

    /**
     * @covers ::modeName
     */
    public function testModeName() {
        $this->assertEquals('indeterminate', Framework::modeName(Framework::MODE_INDETERMINATE));
        $this->assertEquals('web', Framework::modeName(Framework::MODE_WEB));
        $this->assertEquals('console', Framework::modeName(Framework::MODE_CONSOLE));
        $this->assertEquals('command', Framework::modeName(Framework::MODE_COMMAND));
        $this->assertEquals('test', Framework::modeName(Framework::MODE_TEST));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @covers ::modeName
     */
    public function testInvalidModeName() {
        Framework::modeName(345);
    }

    protected function provideModule($name, array $depends = array(), $alreadyLoaded = false) {
        $module = $this->getMockBuilder('Splot\Framework\Modules\AbstractModule')
            ->setMethods(array('loadModules', 'getName', 'configure', 'getConfigDir', 'run'))
            ->getMock();

        if ($alreadyLoaded) {

            $module->expects($this->any())
                ->method('getName')
                ->will($this->returnValue($name));
            $module->expects($this->never())
                ->method('loadModules');

        } else {

            $module->expects($this->atLeastOnce())
                ->method('getName')
                ->will($this->returnValue($name));
            $module->expects($this->atLeastOnce())
                ->method('loadModules')
                ->will($this->returnValue($depends));
        
        }

        return $module;
    }

}
