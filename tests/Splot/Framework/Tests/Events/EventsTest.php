<?php
namespace Splot\Framework\Tests\Events;

use Splot\Framework\Events;

use Splot\Framework\Tests\Events\Fixtures\TestController;

use Splot\Framework\Controller\ControllerResponse;
use Splot\Framework\DependencyInjection\ServiceContainer;
use Splot\Framework\Routes\Route;
use Splot\Framework\HTTP\Request;
use Splot\Framework\HTTP\Response;

class EventsTest extends \PHPUnit_Framework_TestCase
{

    public function testControllerDidRespond() {
        $controllerResponse = new ControllerResponse('some response');
        $controller = new TestController(new ServiceContainer());
        $arguments = array(
            'id' => 123
        );

        $event = new Events\ControllerDidRespond($controllerResponse, 'SplotTestModule:TestController', $controller, 'index', $arguments);

        $this->assertSame($controllerResponse, $event->getControllerResponse());
        $this->assertEquals('SplotTestModule:TestController', $event->getControllerName());
        $this->assertSame($controller, $event->getController());
        $this->assertEquals('index', $event->getMethod());
        $this->assertEquals($arguments, $event->getArguments());
        $this->assertNull($event->getRequest());
    }

    public function testControllerDidRespondToRequest() {
        $controllerResponse = new ControllerResponse('some response');
        $controller = new TestController(new ServiceContainer());

        $request = Request::create('/test/');
        
        $event = new Events\ControllerDidRespond($controllerResponse, 'SplotTestModule:TestController', $controller, 'index', array(), $request);

        $this->assertSame($event->getRequest(), $request);
    }

    public function testControllerWillRespond() {
        $controller = new TestController(new ServiceContainer());
        $arguments = array(
            'id' => 123
        );

        $event = new Events\ControllerWillRespond('SplotTestModule:TestController', $controller, 'index', $arguments);

        $this->assertEquals('SplotTestModule:TestController', $event->getControllerName());
        $this->assertSame($controller, $event->getController());
        $this->assertEquals('index', $event->getMethod());
        $this->assertEquals($arguments, $event->getArguments());

        $event->setMethod('customMethod');
        $this->assertEquals('customMethod', $event->getMethod());
        $newArguments = array(
            'id' => 15
        );
        $event->setArguments($newArguments);
        $this->assertEquals($newArguments, $event->getArguments());
    }

    public function testDidFindRouteForRequest() {
        $route = new Route('test_route', TestController::__class(), '/test/', array(
            'get' => 'index'
        ));
        $request = Request::create('/test/');

        $event = new Events\DidFindRouteForRequest($route, $request);

        $this->assertSame($route, $event->getRoute());
        $this->assertSame($request, $event->getRequest());
    }

    public function testDidNotFindRouteForRequest() {
        $request = Request::create('/test/');

        $event = new Events\DidNotFindRouteForRequest($request);

        $this->assertSame($request, $event->getRequest());
        $this->assertFalse($event->isHandled());

        $response = new Response('some response');
        $event->setResponse($response);
        $this->assertSame($response, $event->getResponse());
        $this->assertTrue($event->isHandled());
    }

    public function testDidReceiveRequest() {
        $request = Request::create('/test/');

        $event = new Events\DidReceiveRequest($request);

        $this->assertSame($request, $event->getRequest());
    }

    public function testErrorDidOccur() {
        $line = __LINE__;
        $context = array(
            'line' => $line
        );
        $event = new Events\ErrorDidOccur(0, 'Notice', __FILE__, $line, $context);

        $this->assertEquals(0, $event->getCode());
        $this->assertEquals('Notice', $event->getMessage());
        $this->assertEquals(__FILE__, $event->getFile());
        $this->assertEquals($line, $event->getLine());
        $this->assertEquals($context, $event->getContext());
        $this->assertFalse($event->isHandled());

        $event->setHandled(true);
        $this->assertTrue($event->isHandled());
        $this->assertTrue($event->getHandled());
    }

    public function testExceptionDidOccur() {
        $exception = new \Exception('Some exception', 500);
        $event = new Events\ExceptionDidOccur($exception);

        $this->assertSame($exception, $event->getException());

        $this->assertFalse($event->isHandled());

        $response = new Response('some response');

        $event->setResponse($response);
        $this->assertSame($response, $event->getResponse());
        $this->assertTrue($event->isHandled());
        $this->assertTrue($event->getHandled());
    }

    public function testWillSendResponse() {
        $request = Request::create('/test/');
        $response = new Response('some response');

        $event = new Events\WillSendResponse($response, $request);

        $this->assertSame($request, $event->getRequest());
        $this->assertSame($response, $event->getResponse());
    }

}
