<?php
namespace Splot\Framework\Tests\ErrorHandlers;

use Whoops\Handler\Handler;

use Splot\Framework\ErrorHandlers\HTTPStatusErrorHandler;

/**
 * @coversDefaultClass \Splot\Framework\ErrorHandlers\HTTPStatusErrorHandler
 */
class HTTPStatusErrorHandlerTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider provideHttpCodes
     * 
     * @covers ::handle
     */
    public function testSettingHttpCodeFromHttpException($code) {
        $run = $this->getMock('Whoops\Run');
        $run->expects($this->once())
            ->method('sendHttpCode')
            ->with($this->equalTo($code));

        $exception = new \Splot\Framework\HTTP\Exceptions\NotFoundException('', $code);

        $handler = new HTTPStatusErrorHandler();
        $handler->setException($exception);
        $handler->setRun($run);

        $handler->handle();
    }

    public function provideHttpCodes() {
        return array(
            array(500),
            array(404),
            array(400),
            array(403),
            array(409)
        );
    }

    /**
     * @covers ::handle
     */
    public function testNotSettingHttpCodeFromNonHttpException() {
        $run = $this->getMock('Whoops\Run');
        $run->expects($this->never())
            ->method('sendHttpCode');

        $handler = new HTTPStatusErrorHandler();
        $handler->setException(new \Exception());
        $handler->setRun($run);

        $handler->handle();
    }

}
