<?php
namespace Splot\Framework\Tests\Application;

use Splot\Framework\Application\AbstractApplication;

use Splot\Framework\Tests\Application\Fixtures\TestApplication;

use Psr\Log\NullLogger;
use Splot\Log\Provider\LogProvider;
use MD\Foundation\Debug\Timer;
use Splot\Framework\Config\Config;
use Splot\Framework\DependencyInjection\ServiceContainer;
use Splot\Framework\Routes\Router;
use Splot\EventManager\EventManager;
use Splot\Framework\Resources\Finder;
use Splot\Framework\Process\Process;
use Splot\Framework\Console\Console;
use Splot\Framework\DataBridge\DataBridge;
use Splot\Cache\Store\FileStore;
use Splot\Cache\CacheProvider;
use Splot\Cache\CacheInterface;


class AbstractApplicationTest extends \PHPUnit_Framework_TestCase
{

    public function tearDown() {
        \Splot\Log\LogContainer::clear();
    }

    protected function initApplication(AbstractApplication $app, $env = 'test', array $configArray = array()) {
        $configArray = (!empty($configArray)) ? $configArray : array(
            'cache' => array(
                'stores' => array(),
                'caches' => array()
            )
        );
        $config = new Config($configArray);
        $container = new ServiceContainer();
        $timer = new Timer();
        $logger = new NullLogger();
        $logProvider = new LogProvider();

        // container has to have few things defined
        $container->setParameter('cache_dir', realpath(dirname(__FILE__) .'/../../../..') .'/tmp/cache');

        $app->init($config, $container, 'test', $timer, $logger, $logProvider);
        
        return $app;
    }

    public function testInitialization() {
        $app = new TestApplication();

        $config = new Config(array(
            'cache' => array(
                'stores' => array(
                    'memory' => array(
                        'class' => 'Splot\\Cache\\Store\\MemoryStore'
                    )
                ),
                'caches' => array(
                    'lipsum' => 'memory'
                )
            )
        ));
        $container = new ServiceContainer();
        $timer = new Timer();
        $logger = new NullLogger();
        $logProvider = new LogProvider();

        // container has to have few things defined
        $container->setParameter('cache_dir', realpath(dirname(__FILE__) .'/../../../..') .'/tmp/cache');

        $app->init($config, $container, 'test', $timer, $logger, $logProvider);

        // make sure the injected objects are properly available
        $this->assertSame($container, $app->getContainer());
        $this->assertEquals('test', $app->getEnv());
        $this->assertEquals('test', $container->getParameter('env'));
        $this->assertFalse($app->isDevEnv());
        $this->assertSame($config, $app->getConfig());
        $this->assertSame($config, $container->get('config'));
        $this->assertTrue($app->getRouter() instanceof Router);
        $this->assertSame($app->getRouter(), $container->get('router'));
        $this->assertTrue($app->getEventManager() instanceof EventManager);
        $this->assertSame($app->getEventManager(), $container->get('event_manager'));
        $this->assertTrue($app->getResourceFinder() instanceof Finder);
        $this->assertSame($app->getResourceFinder(), $container->get('resource_finder'));
        $this->assertSame($logger, $app->getLogger());
        $this->assertTrue($container->get('process') instanceof Process);
        $this->assertTrue($container->get('console') instanceof Console);
        $this->assertTrue($container->get('databridge') instanceof DataBridge);

        // make sure that caches from the config are also properly registered
        $this->assertTrue($container->get('cache.store.file') instanceof FileStore);
        $this->assertTrue($container->get('cache.store.memory') instanceof \Splot\Cache\Store\MemoryStore);
        $this->assertTrue($container->get('cache') instanceof CacheInterface);
        $this->assertTrue($container->get('cache.lipsum') instanceof CacheInterface);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testDoubleInitializationFailing() {
        $app = new TestApplication();
        $this->initApplication($app);
        $this->initApplication($app);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testInvalidCacheStoreInitialization() {
        $app = new TestApplication();
        $this->initApplication($app, 'test', array(
            'cache' => array(
                'stores' => array(
                    'memory' => array()
                )
            ),
            'caches' => array()
        ));
    }

    public function testGetName() {
        $app = new TestApplication();
        $this->assertAttributeEquals($app->getName(), 'name', $app);
    }

    public function testGetVersion() {
        $app = new TestApplication();
        $this->assertAttributeEquals($app->getVersion(), 'version', $app);
    }

    public function testGetClass() {
        $this->assertEquals('Splot\\Framework\\Tests\\Application\\Fixtures\\TestApplication', TestApplication::getClass());
    }

}
