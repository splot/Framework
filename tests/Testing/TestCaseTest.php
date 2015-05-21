<?php
namespace Splot\Framework\Tests\Testing;

use Splot\Framework\Application\AbstractApplication;
use Splot\Framework\Testing\TestCase;

/**
 * @coversDefaultClass \Splot\Framework\Testing\TestCase
 */
class TestCaseTest extends TestCase
{

    /**
     * @covers ::setUp
     */
    public function testApplicationStubAfterSetup() {
        $this->assertNotNull($this->_application);
        $this->assertTrue($this->_application instanceof AbstractApplication);
    }

    /**
     * @covers ::tearDown
     */
    public function testTearDown() {
        $this->tearDown();
        $this->assertNull($this->_application);
    }

}