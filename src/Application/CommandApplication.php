<?php
/**
 * Command Application used for single command apps.
 * 
 * @package SplotFramework
 * @subpackage Application
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Application;

use Splot\Framework\Application\AbstractApplication;

class CommandApplication extends AbstractApplication
{

    /**
     * Command class name.
     * 
     * @var string
     */
    protected $commandClass;

    /**
     * Constructor.
     * 
     * @param string $commandClass [optional] Command class name. Default: '\App'.
     */
    public function __construct($commandClass = '\App') {
        $this->commandClass = $commandClass;
        $this->name = $commandClass::getName();
    }

    /**
     * Loads modules for the application.
     */
    public function loadModules() {
        return call_user_func(array($this->commandClass, 'loadModules'));
    }

}
