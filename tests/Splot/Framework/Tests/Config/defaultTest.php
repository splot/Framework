<?php
namespace Splot\Framework\Tests\Config;

class defaultTest extends \PHPUnit_Framework_TestCase
{

    public function testDefaultConfigFile() {
        $defaultFile = realpath(dirname(__FILE__) .'/../../../../../src/Splot/Framework/Config/default.php');
        $default = include $defaultFile;

        $this->assertInternalType('array', $default);
        $this->assertArrayHasKey('timezone', $default);
        $this->assertArrayHasKey('cache', $default);
    }

}
