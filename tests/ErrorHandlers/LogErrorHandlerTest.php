<?php
namespace Splot\Framework\Tests\ErrorHandlers;

use Whoops\Handler\Handler;

use Splot\Framework\ErrorHandlers\LogErrorHandler;
use Splot\Framework\HTTP\Exceptions\BadRequestException;

/**
 * @coversDefaultClass \Splot\Framework\ErrorHandlers\LogErrorHandler
 */
class LogErrorHandlerTest extends \PHPUnit_Framework_TestCase
{
    use \Splot\Framework\Tests\MockTrait;

    /**
     * @covers ::__construct
     * @covers ::handle
     */
    public function testLoggingException() {
        $logger = $this->getMock('Psr\Log\LoggerInterface');
        $handler = $this->getMockBuilder('Splot\Framework\ErrorHandlers\LogErrorHandler')
            ->setConstructorArgs(array($logger))
            ->setMethods(array('getException'))
            ->getMock();
        $handler->expects($this->any())
            ->method('getException')
            ->will($this->returnValue(new \Exception));

        $logger->expects($this->once())
            ->method('critical');

        $this->assertEquals(Handler::DONE, $handler->handle());
    }

    /**
     * @covers ::__construct
     * @covers ::handle
     */
    public function testLoggingHttpException() {
        $logger = $this->getMock('Psr\Log\LoggerInterface');
        $handler = $this->getMockBuilder('Splot\Framework\ErrorHandlers\LogErrorHandler')
            ->setConstructorArgs(array($logger))
            ->setMethods(array('getException'))
            ->getMock();
        $handler->expects($this->any())
            ->method('getException')
            ->will($this->returnValue(new BadRequestException()));

        $logger->expects($this->once())
            ->method('notice');

        $this->assertEquals(Handler::DONE, $handler->handle());
    }

}
