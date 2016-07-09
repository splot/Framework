<?php
namespace Splot\Framework\Tests\Log;

use Splot\Framework\Log\Clog;

/**
 * @coversDefaultClass \Splot\Framework\Log\Clog
 */
class ClogTest extends \PHPUnit_Framework_TestCase
{
    use \Splot\Framework\Tests\MockTrait;

    public function testInterface() {
        $log = new Clog();
        $this->assertInstanceOf('Splot\Framework\Log\LoggerProviderInterface', $log);
    }

    /**
     * @covers ::provide
     */
    public function testProvide() {
        $log = new Clog();
        $logger = $log->provide('TestLog');
        $this->assertInstanceOf('Psr\Log\LoggerInterface', $logger);
    }

    /**
     * @covers ::provide
     */
    public function testProvideIntegratingWithClog() {
        $log = $this->getMockBuilder('Splot\Framework\Log\Clog')
            ->setMethods(array('provideLogger'))
            ->getMock();

        $log->expects($this->once())
            ->method('provideLogger')
            ->with($this->equalTo('TestLog'));

        $log->provide('TestLog');
    }

}