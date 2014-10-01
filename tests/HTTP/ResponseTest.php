<?php
namespace Splot\Framework\Tests\HTTP;

use Splot\Framework\HTTP\Response;


class ResponseTest extends \PHPUnit_Framework_TestCase
{

    public function testAlterPart() {
        $response = new Response('Lorem ipsum dolor sit amet, adipiscit elit!');
        $response->alterPart('Lorem ipsum', 'Lipsum');
        $this->assertEquals('Lipsum dolor sit amet, adipiscit elit!', $response->getContent());
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\InvalidArgumentException
     */
    public function testInvalidAlterPart() {
        $response = new Response('Lorem ipsum dolor sit amet, adipiscit elit!');
        $response->alterPart('', 'Lipsum');
    }

    public function testGettingClassName() {
        $this->assertEquals('Splot\Framework\HTTP\Response', Response::__class());
    }

}
