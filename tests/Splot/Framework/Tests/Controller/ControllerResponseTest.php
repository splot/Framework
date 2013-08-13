<?php
namespace Splot\Framework\Tests\Controller;

use Splot\Framework\Controller\ControllerResponse;


class ControllerResponseTest extends \PHPUnit_Framework_TestCase
{

    public function testControllerResponse() {
        $responseText = 'Lorem ipsum dolor sit amet';

        $response = new ControllerResponse($responseText);
        $this->assertEquals($responseText, $response->getResponse());

        $responseText2 = 'Lipsum dolor amet adipiscit';
        $response->setResponse($responseText2);
        $this->assertEquals($responseText2, $response->getResponse());
    }

}
