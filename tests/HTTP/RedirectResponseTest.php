<?php
namespace Splot\Framework\Tests\HTTP;

use Splot\Framework\HTTP\RedirectResponse;


class RedirectResponseTest extends \PHPUnit_Framework_TestCase
{

    public function testResponse() {
        $response = RedirectResponse::create('http://www.lipsum.com');

        $this->assertEquals('http://www.lipsum.com', $response->getUrl());
        $this->assertEquals('http://www.lipsum.com', $response->headers->get('Location'));
        $this->assertEquals(302, $response->getStatusCode());
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\InvalidArgumentException
     */
    public function testEmptyUrl() {
        $response = RedirectResponse::create();
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\InvalidArgumentException
     */
    public function testInvalidHeaders() {
        $response = RedirectResponse::create('http://www.lipsum.com', 302, 'invalid header');
    }

}
