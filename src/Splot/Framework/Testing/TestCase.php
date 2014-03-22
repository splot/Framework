<?php
/**
 * Splot Framework dummy application test case.
 * 
 * Takes care of framework and dummy application setup.
 * 
 * For easy tests that require some dummy application initialized.
 * 
 * @package SplotFramework
 * @subpackage Testing
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Testing;

use Splot\Framework\Framework;
use Splot\Framework\Testing\Stubs\TestApplication;

class TestCase extends \PHPUnit_Framework_TestCase
{

    /**
     * Application which elements are being tested.
     * 
     * @var AbstractApplication
     */
    protected $_application;

    /**
     * Sets up the application before every test.
     * 
     * If you're overwriting this then be sure to call parent::setUp().
     */
    public function setUp() {
        $this->_application = new TestApplication();
        Framework::run($this->_application, 'dev', true, Framework::MODE_TEST);
    }

    /**
     * Tear down after every test.
     */
    public function tearDown() {
        $this->_application = null;
    }

}
