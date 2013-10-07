<?php
/**
 * Splot Framework Application test case.
 * 
 * For easy testing of specific applications.
 * 
 * @package SplotFramework
 * @subpackage Testing
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Testing;

use Splot\Framework\Controller\AbstractController;
use Splot\Framework\Framework;
use Splot\Framework\Testing\TestCase;

class ApplicationTestCase extends TestCase
{

    /**
     * Application class name.
     * 
     * @var string
     */
    public static $_applicationClass = 'Application';

    /**
     * Sets up the application before every test.
     * 
     * If you're overwriting this then be sure to call parent::setUp().
     */
    public function setUp() {
        if (!class_exists(static::$_applicationClass)) {
            throw new \RuntimeException('Application class "'. static::$_applicationClass .'" does not exist. Has it been properly loaded?');
        }

        $appClass = static::$_applicationClass;
        $this->_application = new $appClass();
        Framework::test($this->_application, array());
    }

    /**
     * Get instantiated controller with the given name.
     * 
     * @param string $name Short name of the controller.
     * @return AbstractController
     */
    public function getController($name) {
        $class = $this->_application->getRouter()->getRoute($name)->getControllerClass();
        return new $class($this->_application->getContainer());
    }

}
