<?php
namespace Splot\Framework\Tests\Testing;

use Splot\Framework\Application\AbstractApplication;
use Splot\Framework\Testing\ApplicationTestCase;

use Splot\Framework\Tests\Testing\Stubs\StubController;

/**
 * @coversDefaultClass \Splot\Framework\Testing\ApplicationTestCase
 */
class ApplicationTestCaseTest extends ApplicationTestCase
{
    use \Splot\Framework\Tests\MockTrait;

    public static $applicationClass = 'Splot\Framework\Testing\Stubs\TestApplication';

    /**
     * @covers ::setUp
     */
    public function testApplicationStubAfterSetup() {
        $this->assertNotNull($this->application);
        $this->assertTrue($this->application instanceof static::$applicationClass);
    }

    /**
     * @covers ::setUp
     */
    public function testApplicationClassNotDefined() {
        $old = self::$applicationClass;
        self::$applicationClass = null;
        $exceptionThrown = false;
        try {
            $this->setUp();
        } catch (\RuntimeException $e) {
            $exceptionThrown = true;
        }

        self::$applicationClass = $old;
        $this->assertTrue($exceptionThrown);
    }

    /**
     * @covers ::getController
     */
    public function testGetController() {
        $this->application->getContainer()->get('router')->addRoute('stub_controller', 'Splot\Framework\Tests\Testing\Stubs\StubController');

        $controller = $this->getController('stub_controller');
        $this->assertTrue($controller instanceof StubController);
    }

}