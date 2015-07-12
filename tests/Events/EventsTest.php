<?php
namespace Splot\Framework\Tests\Events;

use Splot\DependencyInjection\ContainerInterface;
use Splot\DependencyInjection\Container;

use Splot\Framework\Events;
use Splot\Framework\Tests\Events\Fixtures\TestController;
use Splot\Framework\Controller\ControllerResponse;
use Splot\Framework\Routes\Route;
use Splot\Framework\HTTP\Request;
use Splot\Framework\HTTP\Response;

class EventsTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @covers \Splot\Framework\Events\ControllerDidRespond::__construct
     * @covers \Splot\Framework\Events\ControllerDidRespond::getControllerResponse
     * @covers \Splot\Framework\Events\ControllerDidRespond::getControllerName
     * @covers \Splot\Framework\Events\ControllerDidRespond::getController
     * @covers \Splot\Framework\Events\ControllerDidRespond::getMethod
     * @covers \Splot\Framework\Events\ControllerDidRespond::getArguments
     * @covers \Splot\Framework\Events\ControllerDidRespond::getRequest
     */
    public function testControllerDidRespond() {
        $controllerResponse = new ControllerResponse('some response');
        $controller = new TestController(new Container());
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

    /**
     * @covers \Splot\Framework\Events\ControllerDidRespond::__construct
     * @covers \Splot\Framework\Events\ControllerDidRespond::getRequest
     */
    public function testControllerDidRespondToRequest() {
        $controllerResponse = new ControllerResponse('some response');
        $controller = new TestController(new Container());

        $request = Request::create('/test/');
        
        $event = new Events\ControllerDidRespond($controllerResponse, 'SplotTestModule:TestController', $controller, 'index', array(), $request);

        $this->assertSame($event->getRequest(), $request);
    }

    /**
     * @covers \Splot\Framework\Events\ControllerWillRespond::__construct
     * @covers \Splot\Framework\Events\ControllerWillRespond::getControllerName
     * @covers \Splot\Framework\Events\ControllerWillRespond::getController
     * @covers \Splot\Framework\Events\ControllerWillRespond::getMethod
     * @covers \Splot\Framework\Events\ControllerWillRespond::getArguments
     * @covers \Splot\Framework\Events\ControllerWillRespond::setArguments
     * @covers \Splot\Framework\Events\ControllerWillRespond::setMethod
     */
    public function testControllerWillRespond() {
        $controller = new TestController(new Container());
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

    /**
     * @covers \Splot\Framework\Events\DidFindRouteForRequest::__construct
     * @covers \Splot\Framework\Events\DidFindRouteForRequest::getRoute
     * @covers \Splot\Framework\Events\DidFindRouteForRequest::getRequest
     */
    public function testDidFindRouteForRequest() {
        $route = new Route('test_route', TestController::class, '/test/', array(
            'get' => 'index'
        ));
        $request = Request::create('/test/');

        $event = new Events\DidFindRouteForRequest($route, $request);

        $this->assertSame($route, $event->getRoute());
        $this->assertSame($request, $event->getRequest());
    }

    /**
     * @covers \Splot\Framework\Events\DidNotFindRouteForRequest::__construct
     * @covers \Splot\Framework\Events\DidNotFindRouteForRequest::getRequest
     * @covers \Splot\Framework\Events\DidNotFindRouteForRequest::isHandled
     * @covers \Splot\Framework\Events\DidNotFindRouteForRequest::setResponse
     * @covers \Splot\Framework\Events\DidNotFindRouteForRequest::getResponse
     */
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

    /**
     * @covers \Splot\Framework\Events\DidReceiveRequest::__construct
     * @covers \Splot\Framework\Events\DidReceiveRequest::getRequest
     */
    public function testDidReceiveRequest() {
        $request = Request::create('/test/');

        $event = new Events\DidReceiveRequest($request);

        $this->assertSame($request, $event->getRequest());
    }

    /**
     * @covers \Splot\Framework\Events\ErrorDidOccur::__construct
     * @covers \Splot\Framework\Events\ErrorDidOccur::getCode
     * @covers \Splot\Framework\Events\ErrorDidOccur::getMessage
     * @covers \Splot\Framework\Events\ErrorDidOccur::getFile
     * @covers \Splot\Framework\Events\ErrorDidOccur::getLine
     * @covers \Splot\Framework\Events\ErrorDidOccur::getContext
     * @covers \Splot\Framework\Events\ErrorDidOccur::isHandled
     * @covers \Splot\Framework\Events\ErrorDidOccur::getHandled
     * @covers \Splot\Framework\Events\ErrorDidOccur::setHandled
     */
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

    /**
     * @covers \Splot\Framework\Events\ExceptionDidOccur::__construct
     * @covers \Splot\Framework\Events\ExceptionDidOccur::getException
     * @covers \Splot\Framework\Events\ExceptionDidOccur::isHandled
     * @covers \Splot\Framework\Events\ExceptionDidOccur::getResponse
     * @covers \Splot\Framework\Events\ExceptionDidOccur::getHandled
     * @covers \Splot\Framework\Events\ExceptionDidOccur::setResponse
     */
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

    /**
     * @covers \Splot\Framework\Events\WillSendResponse::__construct
     * @covers \Splot\Framework\Events\WillSendResponse::getRequest
     * @covers \Splot\Framework\Events\WillSendResponse::getResponse
     */
    public function testWillSendResponse() {
        $request = Request::create('/test/');
        $response = new Response('some response');

        $event = new Events\WillSendResponse($response, $request);

        $this->assertSame($request, $event->getRequest());
        $this->assertSame($response, $event->getResponse());
    }

}
