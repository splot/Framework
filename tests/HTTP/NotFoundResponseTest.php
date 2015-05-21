<?php
namespace Splot\Framework\Tests\HTTP;

use Splot\Framework\HTTP\NotFoundResponse;

/**
 * @coversDefaultClass \Splot\Framework\HTTP\NotFoundResponse
 */
class NotFoundResponseTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @covers ::create
     * @covers ::__construct
     */
    public function testResponse() {
        $response = NotFoundResponse::create('http://www.lipsum.com');

        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\InvalidArgumentException
     * @covers ::create
     * @covers ::__construct
     */
    public function testInvalidHeaders() {
        $response = NotFoundResponse::create('http://www.lipsum.com', 404, 'invalid header');
    }

}
