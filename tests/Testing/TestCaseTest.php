<?php
namespace Splot\Framework\Tests\Testing;

use Splot\Framework\Application\AbstractApplication;
use Splot\Framework\Testing\TestCase;

/**
 * @coversDefaultClass \Splot\Framework\Testing\TestCase
 */
class TestCaseTest extends TestCase
{
    use \Splot\Framework\Tests\MockTrait;

    /**
     * @covers ::setUp
     */
    public function testApplicationStubAfterSetup() {
        $this->assertNotNull($this->application);
        $this->assertTrue($this->application instanceof AbstractApplication);
    }

    /**
     * @covers ::tearDown
     */
    public function testTearDown() {
        $this->tearDown();
        $this->assertNull($this->application);
    }

}