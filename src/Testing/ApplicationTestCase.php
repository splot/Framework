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
    public static $applicationClass = 'Splot\Framework\Testing\Stubs\TestApplication';

    /**
     * Sets up the application before every test.
     * 
     * If you're overwriting this then be sure to call parent::setUp().
     */
    public function setUp() {
        if (!class_exists(static::$applicationClass)) {
            throw new \RuntimeException('Application class "'. static::$applicationClass .'" does not exist. Has it been properly loaded?');
        }

        $appClass = static::$applicationClass;
        $this->application = new $appClass();
        Framework::run($this->application, 'test', true, Framework::MODE_TEST);
    }

    /**
     * Get instantiated controller with the given name.
     * 
     * @param string $name Short name of the controller.
     * @return AbstractController
     */
    public function getController($name) {
        $container = $this->application->getContainer();
        $class = $container->get('router')->getRoute($name)->getControllerClass();
        return new $class($container);
    }

}
