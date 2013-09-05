<?php
/**
 * Splot Framework Application test case.
 * 
 * @package SplotFramework
 * @subpackage Testing
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 * 
 * @codeCoverageIgnore
 */
namespace Splot\Framework\Testing;

use Splot\Log\LogContainer;

use Splot\Framework\Application\AbstractApplication;
use Splot\Framework\Framework;

class ApplicationTestCase extends \PHPUnit_Framework_TestCase
{

    /**
     * Application which elements are being tested.
     * 
     * @var AbstractApplication
     */
    protected $_application;

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

        $appClass = static::$_applicationClass();
        $this->_application = new $appClass();
        Framework::test($this->_application, array());
    }

    /**
     * Tear down after every test.
     */
    public function tearDown() {
        $this->_application = null;
        Framework::reset();
        LogContainer::clear();
    }

}
