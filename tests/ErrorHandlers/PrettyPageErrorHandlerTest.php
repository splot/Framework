<?php
namespace Splot\Framework\Tests\ErrorHandlers;

use Whoops\Handler\Handler;

use Splot\Framework\ErrorHandlers\PrettyPageErrorHandler;

/**
 * @coversDefaultClass \Splot\Framework\ErrorHandlers\PrettyPageErrorHandler
 */
class PrettyPageErrorHandlerTest extends \PHPUnit_Framework_TestCase
{
    use \Splot\Framework\Tests\MockTrait;

    /**
     * @covers ::__construct
     * @covers ::handle
     */
    public function testNotHandlingWhenDisabled() {
        $handler = $this->getMockBuilder('Splot\Framework\ErrorHandlers\PrettyPageErrorHandler')
            ->setConstructorArgs(array(false))
            ->setMethods(array('parentHandle'))
            ->getMock();

        $handler->expects($this->never())
            ->method('parentHandle');

        $this->assertEquals(Handler::DONE, $handler->handle());
    }

    /**
     * @covers ::__construct
     * @covers ::handle
     */
    public function testHandlingWhenEnabled() {
        $handler = $this->getMockBuilder('Splot\Framework\ErrorHandlers\PrettyPageErrorHandler')
            ->setConstructorArgs(array(true))
            ->setMethods(array('parentHandle'))
            ->getMock();

        $handler->expects($this->once())
            ->method('parentHandle')
            ->will($this->returnValue(Handler::DONE));

        $this->assertEquals(Handler::DONE, $handler->handle());
    }

}
