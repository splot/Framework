<?php
/**
 * Abstract module class. All Splot modules should extend it.
 * 
 * @package SplotFramework
 * @subpackage Modules
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Modules;

use MD\Foundation\Debug\Debugger;

use Splot\Framework\Config\Config;
use Splot\Framework\Application\AbstractApplication;
use Splot\Framework\DependencyInjection\ServiceContainer;

abstract class AbstractModule
{

    /**
     * Module config.
     * 
     * @var Config
     */
    private $_config;

    /**
     * Reference to parent application.
     * 
     * @var AbstractApplication
     */
    private $_application;

    /**
     * Class name for this module.
     * 
     * @var string
     */
    private $_class;

    /**
     * Namespace of this module.
     * 
     * @var string
     */
    private $_namespace;

    /**
     * Directory where this module is located.
     * 
     * @var string
     */
    private $_moduleDir;

    /**
     * Dependency injection service container.
     * 
     * @var ServiceContainer
     */
    protected $container;

    /**
     * Module name
     * 
     * @var string
     */
    protected $_name;

    /**
     * Prefix that will be added to all URL's from this module.
     * 
     * @var string|null
     */
    protected $_urlPrefix;

    /**
     * Namespace for all commands that belong to this module
     * 
     * @var string|null
     */
    protected $_commandNamespace;

    /**
     * Boots the module.
     */
    abstract public function boot();

    /**
     * Initializes the module after all other modules have been loaded.
     */
    public function init() {}

    /*****************************************
     * SETTERS AND GETTERS
     *****************************************/
    /**
     * Returns the module name.
     * 
     * @return string
     */
    public function getName() {
        if ($this->_name) {
            return $this->_name;
        }

        $this->_name = Debugger::getClass($this, true);
        return $this->_name;
    }

    /**
     * Set the module's config.
     * 
     * @param Config $config
     */
    public function setConfig(Config $config) {
        $this->_config = $config;
    }

    /**
     * Returns the module's config.
     * 
     * @return Config
     */
    public function getConfig() {
        return $this->_config;
    }

    /**
     * Sets a reference to the parent application.
     * 
     * @param AbstractApplication $application
     */
    final public function setApplication(AbstractApplication $application) {
        $this->_application = $application;
    }

    /**
     * Returns reference to the parent application.
     * 
     * @return AbstractApplication
     */
    final public function getApplication() {
        return $this->_application;
    }

    /**
     * Sets the dependency injection service container.
     * 
     * @param ServiceContainer $serviceContainer
     */
    final public function setContainer(ServiceContainer $serviceContainer) {
        $this->container = $serviceContainer;
    }

    /**
     * Returns the dependency injection service container.
     * 
     * @return ServiceContainer
     */
    final public function getContainer() {
        return $this->container;
    }

    /**
     * Returns a prefix that will be added to all routes from this module.
     * 
     * @return string|null
     */
    public function getUrlPrefix() {
        return $this->_urlPrefix;
    }

    /**
     * Returns a namespace for all console commands from this module.
     * 
     * @return string|null
     */
    public function getCommandNamespace() {
        return $this->_commandNamespace;
    }

    /**
     * Returns class name for this module.
     * 
     * @return string
     */
    final public function getClass() {
        if ($this->_class) {
            return $this->_class;
        }

        $this->_class = Debugger::getClass($this);
        return $this->_class;
    }

    /**
     * Returns namespace of this module.
     * 
     * @return string
     */
    final public function getNamespace() {
        if ($this->_namespace) {
            return $this->_namespace;
        }

        $this->_namespace = Debugger::getNamespace($this);
        return $this->_namespace;
    }

    /**
     * Returns directory where this module is located.
     * 
     * @return string
     */
    final public function getModuleDir() {
        if ($this->_moduleDir) {
            return $this->_moduleDir;
        }

        $file = Debugger::getClassFile($this);
        $this->_moduleDir = dirname($file) .'/';
        return $this->_moduleDir;
    }

}