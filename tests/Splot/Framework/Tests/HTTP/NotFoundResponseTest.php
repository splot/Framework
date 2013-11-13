<?php
namespace Splot\Framework\Tests\HTTP;

use Splot\Framework\HTTP\NotFoundResponse;


class NotFoundResponseTest extends \PHPUnit_Framework_TestCase
{

    public function testResponse() {
        $response = NotFoundResponse::create('http://www.lipsum.com');

        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\InvalidArgumentException
     */
    public function testInvalidHeaders() {
        $response = NotFoundResponse::create('http://www.lipsum.com', 404, 'invalid header');
    }

}
