<?php
namespace Splot\Framework\Tests\Application;

use Splot\Framework\Application\AbstractApplication;

use Splot\Framework\Tests\Application\Fixtures\TestApplication;
use Splot\Framework\Tests\Modules\Fixtures\NamedModule;
use Splot\Framework\Tests\Modules\Fixtures\TestModule;
use Splot\Framework\Tests\Application\Fixtures\Controllers\InjectedRequestController;
use Splot\Framework\Tests\Application\Fixtures\Controllers\InvalidReturnValueController;
use Splot\Framework\Tests\Application\Fixtures\Modules\ConfiguredTestModule\SplotConfiguredTestModule;
use Splot\Framework\Tests\Application\Fixtures\Modules\DuplicatedTestModule\SplotDuplicatedTestModule;
use Splot\Framework\Tests\Application\Fixtures\Modules\EmptyTestModule\SplotEmptyTestModule;
use Splot\Framework\Tests\Application\Fixtures\Modules\RoutesTestModule\SplotRoutesTestModule;
use Splot\Framework\Tests\Application\Fixtures\Modules\ResponseTestModule\SplotResponseTestModule;

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
use Splot\Cache\Store\FileStore;
use Splot\Cache\CacheProvider;
use Splot\Cache\CacheInterface;
use Splot\Framework\HTTP\Request;
use Splot\Framework\HTTP\Response;
use Splot\Framework\Events\ControllerWillRespond;
use Splot\Framework\Events\ControllerDidRespond;
use Splot\Framework\Events\DidReceiveRequest;
use Splot\Framework\Events\DidFindRouteForRequest;
use Splot\Framework\Events\DidNotFindRouteForRequest;
use Splot\Framework\Events\ExceptionDidOccur;
use Splot\Framework\Events\WillSendResponse;


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
        $applicationDir = realpath(dirname(__FILE__) .'/Fixtures');

        $app->init($config, $container, 'test', $applicationDir, $timer, $logger, $logProvider);
        
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
        $applicationDir = realpath(dirname(__FILE__) .'/Fixtures');

        $app->init($config, $container, 'test', $applicationDir, $timer, $logger, $logProvider);

        // make sure the injected objects are properly available
        $this->assertSame($container, $app->getContainer());
        $this->assertEquals('test', $app->getEnv());
        $this->assertEquals('test', $container->getParameter('env'));
        $this->assertEquals($applicationDir, $app->getApplicationDir());
        $this->assertEquals($applicationDir, $container->getParameter('application_dir'));
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

    public function testHandlingRequest() {
        $app = new TestApplication();
        $this->initApplication($app);

        $app->bootModule(new SplotResponseTestModule());

        $request = Request::create('/');
        $didReceiveRequestCalled = false;
        $didFindRouteForRequestCalled = false;

        $app->getEventManager()->subscribe(DidReceiveRequest::getName(), function() use (&$didReceiveRequestCalled) {
            $didReceiveRequestCalled = true;
        });
        $app->getEventManager()->subscribe(DidFindRouteForRequest::getName(), function() use (&$didFindRouteForRequestCalled) {
            $didFindRouteForRequestCalled = true;
        });

        $response = $app->handleRequest($request);

        $this->assertSame($request, $app->getContainer()->get('request'));
        $this->assertTrue($response instanceof Response);
        $this->assertEquals('INDEX', $response->getContent());
        $this->assertTrue($didReceiveRequestCalled);
    }

    public function testCatchingExceptionsDuringHandlingOfRequests() {
        $app = new TestApplication();
        $this->initApplication($app);

        $exceptionDidOccurCalled = false;
        $handledResponse = new Response('Handled exception');
        $app->getEventManager()->subscribe(ExceptionDidOccur::getName(), function($ev) use (&$exceptionDidOccurCalled, $handledResponse) {
            $exceptionDidOccurCalled = true;
            $ev->setResponse($handledResponse);
        });

        $response = $app->handleRequest(Request::create('/some/undefined/route'));

        $this->assertTrue($exceptionDidOccurCalled);
        $this->assertSame($handledResponse, $response);
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\NotFoundException
     */
    public function testHandlingRequestWithNotFoundRoute() {
        $app = new TestApplication();
        $this->initApplication($app);

        $app->handleRequest(Request::create('/some/undefined/route.html'));
    }

    public function testHandlingNotFoundRoute() {
        $app = new TestApplication();
        $this->initApplication($app);

        $didNotFoundRouteForRequestCalled = false;
        $handledResponse = new Response('Handled 404');
        $app->getEventManager()->subscribe(DidNotFindRouteForRequest::getName(), function($ev) use ($handledResponse, &$didNotFoundRouteForRequestCalled) {
            $didNotFoundRouteForRequestCalled = true;

            $ev->setResponse($handledResponse);

            return false;
        });

        $response = $app->handleRequest(Request::create('/some/undefined/route.html'));

        $this->assertTrue($didNotFoundRouteForRequestCalled);
        $this->assertSame($response, $handledResponse);
    }

    public function testSendingResponse() {
        $app = new TestApplication();
        $this->initApplication($app);

        $willSendResponseCalled = false;
        $app->getEventManager()->subscribe(WillSendResponse::getName(), function() use (&$willSendResponseCalled) {
            $willSendResponseCalled = true;
        });

        $request = Request::create('/');
        $response = new Response('This is some valid response.');

        ob_start();
        $app->sendResponse($response, $request);
        $content = ob_get_contents();
        ob_end_clean();

        $this->assertEquals('This is some valid response.', $content);
        $this->assertTrue($willSendResponseCalled);
    }

    public function testBootingAndInitializingModules() {
        $app = new TestApplication();
        $this->initApplication($app, 'test', array(
            'cache' => array(
                'stores' => array(
                    'memory' => array(
                        'class' => 'Splot\\Cache\\Store\\MemoryStore'
                    )
                ),
                'caches' => array()
            ),
            'SplotConfiguredTestModule' => array(
                'setting2' => true,
                'subsettings' => array(
                    'sub' => 1234,
                    'notexistent' => 'is now set'
                )
            )
        ));

        $configuredModule = new SplotConfiguredTestModule();
        $emptyModule = new SplotEmptyTestModule();
        $routesModule = new SplotRoutesTestModule();

        $modules = array(
            'SplotConfiguredTestModule' => $configuredModule,
            'SplotEmptyTestModule' => $emptyModule,
            'SplotRoutesTestModule' => $routesModule
        );

        foreach($modules as $name => $module) {
            $app->bootModule($module);
            $this->assertTrue($module->isBooted());
            $this->assertTrue($app->hasModule($name));
            $this->assertSame($module, $app->getModule($name));

            $this->assertSame($app, $module->getApplication());
            $this->assertNotNull($module->getContainer());

            $app->initModule($module);
            $this->assertTrue($module->isInitialized());
        }

        $this->assertEquals(array_keys($modules), $app->listModules());
        $this->assertFalse($app->hasModule('SplotUndefinedTestModule'));

        // check the config on the configured module
        $config = $configuredModule->getConfig();
        $this->assertEquals(true, $config->get('setting2'));
        $this->assertEquals(1234, $config->get('subsettings.sub'));
        $this->assertEquals('is now set', $config->get('subsettings.notexistent'));

        // check routes from the routes module
        $router = $app->getRouter();
        $routes = $router->getRoutes();
        $this->assertEquals(2, count($routes));
        $this->assertArrayHasKey('SplotRoutesTestModule:Index', $routes);
        $this->assertArrayHasKey('SplotRoutesTestModule:Item', $routes);
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\NotUniqueException
     */
    public function testBootingModulesWithSameNames() {
        $app = new TestApplication();
        $this->initApplication($app);

        $emptyModule = new SplotEmptyTestModule();
        $duplicatedModule = new SplotDuplicatedTestModule();

        $app->bootModule($emptyModule);
        $app->bootModule($duplicatedModule);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testInitializingModuleWithoutBootingFirst() {
        $app = new TestApplication();
        $this->initApplication($app);

        $module = new SplotEmptyTestModule();
        $app->initModule($module);
    }

    public function testRenderingControllers() {
        $app = new TestApplication();
        $this->initApplication($app);

        $routesModule = new SplotRoutesTestModule();
        $app->bootModule($routesModule);

        $controllerWillRespondCalled = false;
        $controllerDidRespondCalled = false;

        $app->getEventManager()->subscribe(ControllerWillRespond::getName(), function() use (&$controllerWillRespondCalled) {
            $controllerWillRespondCalled = true;
        });
        $app->getEventManager()->subscribe(ControllerDidRespond::getName(), function() use (&$controllerDidRespondCalled) {
            $controllerDidRespondCalled = true;
        });

        $response = $app->render('SplotRoutesTestModule:Item', array(
            'id' => 123
        ));
        $this->assertTrue($response instanceof Response);
        $this->assertEquals('Received Item ID: 123', $response->getContent());

        $this->assertTrue($controllerWillRespondCalled);
        $this->assertTrue($controllerDidRespondCalled);
    }

    public function testInjectingRequestObjectIntoRenderedController() {
        $app = new TestApplication();
        $this->initApplication($app);

        $router = $app->getRouter();
        $router->addRoute('injector', InjectedRequestController::__class());

        $response = $app->render('injector', array(
            'id' => 123,
            'request' => Request::create('/something')
        ));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testInjectingRequestObjectWhenNotAvailable() {
        $app = new TestApplication();
        $this->initApplication($app);

        $router = $app->getRouter();
        $router->addRoute('injector', InjectedRequestController::__class());

        $response = $app->render('injector', array(
            'id' => 123
        ));
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\InvalidReturnValueException
     */
    public function testRenderingControllerWithInvalidReturnValue() {
        $app = new TestApplication();
        $this->initApplication($app);

        $router = $app->getRouter();
        $router->addRoute('invalid', InvalidReturnValueController::__class());

        $response = $app->render('invalid');
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
