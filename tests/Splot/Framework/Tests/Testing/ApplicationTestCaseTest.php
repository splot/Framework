<?php
namespace Splot\Framework\Tests\Testing;

use Splot\Framework\Application\AbstractApplication;
use Splot\Framework\Testing\ApplicationTestCase;

use Splot\Framework\Tests\Testing\Stubs\StubController;

class ApplicationTestCaseTest extends ApplicationTestCase
{

    public static $_applicationClass = 'Splot\Framework\Testing\Stubs\TestApplication';

    public function testApplicationStubAfterSetup() {
        $this->assertNotNull($this->_application);
        $this->assertTrue($this->_application instanceof static::$_applicationClass);
    }

    public function testApplicationClassNotDefined() {
        $old = self::$_applicationClass;
        self::$_applicationClass = null;
        $exceptionThrown = false;
        try {
            $this->setUp();
        } catch (\RuntimeException $e) {
            $exceptionThrown = true;
        }

        self::$_applicationClass = $old;
        $this->assertTrue($exceptionThrown);
    }

    public function testGetController() {
        $this->_application->getRouter()->addRoute('stub_controller', 'Splot\Framework\Tests\Testing\Stubs\StubController');

        $controller = $this->getController('stub_controller');
        $this->assertTrue($controller instanceof StubController);
    }

}