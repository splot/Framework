<?php
namespace Splot\Framework\Tests\ErrorHandlers;

use Whoops\Handler\Handler;

use Splot\Framework\ErrorHandlers\NullErrorHandler;

/**
 * @coversDefaultClass \Splot\Framework\ErrorHandlers\NullErrorHandler
 */
class NullErrorHandlerTest extends \PHPUnit_Framework_TestCase
{
    use \Splot\Framework\Tests\MockTrait;

    /**
     * @covers ::handle
     */
    public function testHandling() {
        $handler = new NullErrorHandler();
        $this->assertEquals(Handler::DONE, $handler->handle());
    }

}
