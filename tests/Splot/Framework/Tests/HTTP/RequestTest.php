<?php
namespace Splot\Framework\Tests\HTTP;

use Splot\Framework\HTTP\Request;


class RequestTest extends \PHPUnit_Framework_TestCase
{

    public function testGettingClassName() {
        $this->assertEquals('Splot\Framework\HTTP\Request', Request::__class());
    }

}
