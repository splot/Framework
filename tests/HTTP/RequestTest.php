<?php
namespace Splot\Framework\Tests\HTTP;

use Splot\Framework\HTTP\Request;

/**
 * @coversDefaultClass \Splot\Framework\HTTP\Request
 */
class RequestTest extends \PHPUnit_Framework_TestCase
{
    use \Splot\Framework\Tests\MockTrait;

    /**
     * @covers ::__class
     */
    public function testGettingClassName() {
        $this->assertEquals('Splot\Framework\HTTP\Request', Request::__class());
    }

    /**
     * @covers ::__construct
     */
    public function testRawPostData() {
        $rawPostData = array(
            'test' => 'value',
            'key' => 'lorem',
            'lipsum' => 'Lorem ipsum dolor sit amet',
            'something' => array(
                'whatever' => 'hahaha',
                'adipiscit' => true
            ),
            'items' => array('a', 'b', 'c', 'd')
        );

        $request = new Request(array(), array(), array(), array(), array(), array(
            'HTTP_CONTENT_TYPE' => 'application/json'
        ), json_encode($rawPostData));

        $this->assertEquals($rawPostData, $request->request->all());
    }

}
