<?php
namespace Splot\Framework\Tests\Testing;

use Splot\Framework\Application\AbstractApplication;
use Splot\Framework\Testing\TestCase;

class TestCaseTest extends TestCase
{

    public function testApplicationStubAfterSetup() {
        $this->assertNotNull($this->_application);
        $this->assertTrue($this->_application instanceof AbstractApplication);
    }

    public function testTearDown() {
        $this->tearDown();
        $this->assertNull($this->_application);
    }

}