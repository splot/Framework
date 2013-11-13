<?php
namespace Splot\Framework\Tests\HTTP;

use Splot\Framework\HTTP\JsonResponse;


class JsonResponseTest extends \PHPUnit_Framework_TestCase
{

    public function testResponse() {
        $data = array(
            'test' => 'value',
            'key' => 'lorem',
            'lipsum' => 'Lorem ipsum dolor sit amet',
            'something' => array(
                'whatever' => 'hahaha',
                'adipiscit' => true
            ),
            'items' => array('a', 'b', 'c', 'd')
        );
        $response = JsonResponse::create($data);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
        $this->assertEquals(json_encode($data), $response->getContent());
        $this->assertEquals('value', $response->get('test'));
        $this->assertEquals('lorem', $response->get('key'));
        $this->assertEquals($data['something'], $response->get('something'));

        $response->set('key', 'lipsum');
        $response->set('new', true);
        $this->assertEquals('lipsum', $response->get('key'));
        $this->assertTrue($response->get('new'));

        $updatedData = array_merge($data, array(
            'key' => 'lipsum',
            'new' => true
        ));

        $this->assertEquals(json_encode($updatedData), $response->getContent());

        ob_start();
        $response->sendContent();
        $string = ob_get_clean();
        $this->assertEquals(json_encode($updatedData), $string);
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\InvalidArgumentException
     */
    public function testNotArrayData() {
        $response = JsonResponse::create('data');
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\InvalidArgumentException
     */
    public function testInvalidHeaders() {
        $response = JsonResponse::create(array(), 200, 'invalid header');
    }

}
