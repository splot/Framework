<?php
namespace Splot\Framework\Tests\Application;

use Splot\Framework\Testing\ApplicationTestCase;

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
use MD\Foundation\Debug\Timer;
use MD\Clog\Clog;
use Splot\Framework\Config\Config;
use Splot\DependencyInjection\ContainerInterface;
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

/**
 * @coversDefaultClass Splot\Framework\Application\AbstractApplication
 */
class ApplicationResponseFlowTest extends ApplicationTestCase
{

    public static $applicationClass = 'Splot\Framework\Tests\Application\Fixtures\TestApplication';

    /**
     * @covers ::handleRequest
     */
    public function testHandlingRequest() {
        $this->application->addTestModule(new SplotResponseTestModule());

        $request = Request::create('/');
        $didReceiveRequestCalled = false;
        $didFindRouteForRequestCalled = false;

        $eventManager = $this->application->getContainer()->get('event_manager');

        $eventManager->subscribe(DidReceiveRequest::getName(), function() use (&$didReceiveRequestCalled) {
            $didReceiveRequestCalled = true;
        });
        $eventManager->subscribe(DidFindRouteForRequest::getName(), function() use (&$didFindRouteForRequestCalled) {
            $didFindRouteForRequestCalled = true;
        });

        $response = $this->application->handleRequest($request);

        $this->assertSame($request, $this->application->getContainer()->get('request'));
        $this->assertTrue($response instanceof Response);
        $this->assertEquals('INDEX', $response->getContent());
        $this->assertTrue($didReceiveRequestCalled);
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\NotFoundException
     * @covers ::handleRequest
     */
    public function testHandlingRequestWithNotFoundRoute() {
        $this->application->handleRequest(Request::create('/some/undefined/route.html'));
    }

    /**
     * @covers ::handleRequest
     */
    public function testHandlingNotFoundRouteEvent() {
        $didNotFoundRouteForRequestCalled = false;
        $handledResponse = new Response('Handled 404');
        $this->application->getContainer()->get('event_manager')->subscribe(DidNotFindRouteForRequest::getName(), function($ev) use ($handledResponse, &$didNotFoundRouteForRequestCalled) {
            $didNotFoundRouteForRequestCalled = true;

            $ev->setResponse($handledResponse);

            return false;
        });

        $response = $this->application->handleRequest(Request::create('/some/undefined/route.html'));

        $this->assertTrue($didNotFoundRouteForRequestCalled);
        $this->assertSame($response, $handledResponse);
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\NotFoundException
     * @covers ::handleRequest
     */
    public function testHandlingRequestAndPreventingRenderingOfTheFoundRoute() {
        $this->application->addTestModule(new SplotResponseTestModule());

        $this->application->getContainer()->get('event_manager')->subscribe(DidFindRouteForRequest::getName(), function($ev) {
            return false;
        });

        $response = $this->application->handleRequest(Request::create('/'));
    }

    /**
     * @covers ::handleRequest
     */
    public function testHandlingRequestAndPreventingRenderingOfTheFoundRouteAndHandlingThat() {
        $this->application->addTestModule(new SplotResponseTestModule());

        $this->application->getContainer()->get('event_manager')->subscribe(DidFindRouteForRequest::getName(), function($ev) {
            return false;
        });

        $didNotFoundRouteForRequestCalled = false;
        $handledResponse = new Response('Handled 404');
        $this->application->getContainer()->get('event_manager')->subscribe(DidNotFindRouteForRequest::getName(), function($ev) use ($handledResponse, &$didNotFoundRouteForRequestCalled) {
            $didNotFoundRouteForRequestCalled = true;

            $ev->setResponse($handledResponse);

            return false;
        });

        $response = $this->application->handleRequest(Request::create('/'));

        $this->assertTrue($didNotFoundRouteForRequestCalled);
        $this->assertSame($response, $handledResponse);
    }

    /**
     * @covers ::sendResponse
     */
    public function testSendingResponse() {
        $willSendResponseCalled = false;
        $this->application->getContainer()->get('event_manager')->subscribe(WillSendResponse::getName(), function() use (&$willSendResponseCalled) {
            $willSendResponseCalled = true;
        });

        $request = Request::create('/');
        $response = new Response('This is some valid response.');

        ob_start();
        $this->application->sendResponse($response, $request);
        $content = ob_get_contents();
        ob_end_clean();

        $this->assertEquals('This is some valid response.', $content);
        $this->assertTrue($willSendResponseCalled);
    }

    /**
     * @covers ::render
     * @covers ::renderController
     */
    public function testRenderingControllers() {
        $routesModule = new SplotRoutesTestModule();
        $this->application->addTestModule($routesModule);

        $controllerWillRespondCalled = false;
        $controllerDidRespondCalled = false;

        $this->application->getContainer()->get('event_manager')->subscribe(ControllerWillRespond::getName(), function() use (&$controllerWillRespondCalled) {
            $controllerWillRespondCalled = true;
        });
        $this->application->getContainer()->get('event_manager')->subscribe(ControllerDidRespond::getName(), function() use (&$controllerDidRespondCalled) {
            $controllerDidRespondCalled = true;
        });

        $response = $this->application->render('SplotRoutesTestModule:Item', array(
            'id' => 123
        ));
        $this->assertTrue($response instanceof Response);
        $this->assertEquals('Received Item ID: 123', $response->getContent());

        $this->assertTrue($controllerWillRespondCalled);
        $this->assertTrue($controllerDidRespondCalled);
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\InvalidReturnValueException
     * @covers ::render
     * @covers ::renderController
     */
    public function testRenderingControllerWithInvalidReturnValue() {
        $this->application->getContainer()->get('router')->addRoute('invalid', InvalidReturnValueController::__class());

        $response = $this->application->render('invalid');
    }

}