<?php
namespace Splot\Framework\Tests\ErrorHandlers;

use Whoops\Handler\Handler;

use Splot\Framework\ErrorHandlers\EventErrorHandler;
use Splot\Framework\Events\ExceptionDidOccur;

/**
 * @coversDefaultClass \Splot\Framework\ErrorHandlers\EventErrorHandler
 */
class EventErrorHandlerTest extends \PHPUnit_Framework_TestCase
{
    use \Splot\Framework\Tests\MockTrait;

    /**
     * @covers ::__construct
     * @covers ::handle
     */
    public function testTriggeringEvent() {
        $mocks = $this->provideMocks();
        $handler = $this->provideHandler($mocks);

        $mocks['event_manager']->expects($this->once())
            ->method('trigger')
            ->with($this->callback(function($event) {
                return $event instanceof ExceptionDidOccur;
            }));

        // event wasn't really handled so it should return DONE.
        $this->assertEquals(Handler::DONE, $handler->handle());
    }

    /**
     * @covers ::__construct
     * @covers ::handle
     */
    public function testSendingAResponseToError() {
        $mocks = $this->provideMocks();
        $handler = $this->provideHandler($mocks);
        $response = $this->getMock('Splot\Framework\HTTP\Response');

        $response->expects($this->once())
            ->method('send');

        $mocks['event_manager']->expects($this->once())
            ->method('trigger')
            ->with($this->callback(function($event) use ($response) {
                $event->setResponse($response);
                return true;
            }));

        $handler->handle();
    }

    /**
     * @covers ::__construct
     * @covers ::handle
     */
    public function testUsingPreventDefault() {
        $mocks = $this->provideMocks();
        $handler = $this->provideHandler($mocks);

        $mocks['event_manager']->expects($this->once())
            ->method('trigger')
            ->with($this->callback(function($event) {
                $event->preventDefault();
                return true;
            }));

        $this->assertEquals(Handler::QUIT, $handler->handle());
    }

    /**
     * @covers ::__construct
     * @covers ::handle
     */
    public function testUsingStopPropagation() {
        $mocks = $this->provideMocks();
        $handler = $this->provideHandler($mocks);

        $mocks['event_manager']->expects($this->once())
            ->method('trigger')
            ->with($this->callback(function($event) {
                $event->stopPropagation();
                return true;
            }));

        $this->assertEquals(Handler::LAST_HANDLER, $handler->handle());
    }

    protected function provideMocks() {
        $mocks = array();
        $mocks['event_manager'] = $this->getMock('Splot\EventManager\EventManager');
        $mocks['container'] = $this->getMock('Splot\DependencyInjection\ContainerInterface');
        $mocks['container']->expects($this->any())
            ->method('getParameter')
            ->with('mode')
            ->will($this->returnValue('web'));
        return $mocks;
    }

    protected function provideHandler(array $mocks = array()) {
        $mocks = $mocks ? $mocks : $this->provideMocks();
        $handler = $this->getMockBuilder('Splot\Framework\ErrorHandlers\EventErrorHandler')
            ->setConstructorArgs(array($mocks['event_manager'], $mocks['container']))
            ->setMethods(array('getException'))
            ->getMock();
        $handler->expects($this->any())
            ->method('getException')
            ->will($this->returnValue(new \Exception));
        return $handler;
    }

}
