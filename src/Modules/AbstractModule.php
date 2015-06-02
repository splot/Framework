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
use MD\Foundation\Exceptions\NotFoundException;

use Splot\DependencyInjection\ContainerInterface;

use Splot\Framework\Config\Config;

abstract class AbstractModule
{

    /**
     * Prefix that will be added to all URL's from this module.
     * 
     * @var string|null
     */
    protected $urlPrefix;

    /**
     * Namespace for all commands that belong to this module
     * 
     * @var string|null
     */
    protected $commandNamespace;

    /**
     * Dependency injection service container.
     * 
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Class name for this module.
     * 
     * @var string
     */
    private $class;

    /**
     * Namespace of this module.
     * 
     * @var string
     */
    private $namespace;

    /**
     * Directory where this module is located.
     * 
     * @var string
     */
    private $moduleDir;

    /**
     * Module name
     * 
     * @var string
     */
    protected $name;

    /**
     * If the module depends on other modules then return those dependencies from this method.
     *
     * It works exactly the same as application's ::loadModules().
     *
     * @param  string  $env   Environment in which the application is running.
     * @param  boolean $debug Is debug mode on or off?
     * @return array
     */
    public function loadModules($env, $debug) {
        return array();
    }

    /**
     * Configure the module, or more specifically the dependency injection container.
     *
     * This method is invoked during configuration phase, before the application's `::configure()`
     * method (as a module should not know the context of the application in which it is being ran).
     *
     * Any configuration of services, parameters, etc. should be done in this method.
     *
     * Note, that after the application has been configured once and the container has been
     * cached, this method will not be invoked until the cache is cleared. Therefore,
     * any configuration to the container should be made in a way that is cacheable, ie.
     * static definitions of services (no use of object or closure services).
     *
     * By default, this method will attempt to load `services.yml` file from the module's
     * config dir (returned by `::getConfigDir()`). This file does not need to exist.
     */
    public function configure() {
        try {
            $this->container->loadFromFile($this->getConfigDir() .'/services.yml');
        } catch(NotFoundException $e) {}
    }

    /**
     * Run the module.
     *
     * This method is invoked during run phase, before the application's `::run()` method.
     *
     * This is a good place to perform any additional configuration that can only be done at
     * runtime or cannot be cached.
     *
     * By default, this method will call the `router` service and make it load any routes
     * contained in this module (note: this behavior will change in future releases of Splot).
     */
    public function run() {
        $this->container->get('router')->readModuleRoutes($this);
    }

    /*****************************************
     * SETTERS AND GETTERS
     *****************************************/
    /**
     * Returns the module name.
     * 
     * @return string
     */
    public function getName() {
        if ($this->name) {
            return $this->name;
        }

        $this->name = Debugger::getClass($this, true);
        return $this->name;
    }

    /**
     * Returns the module's config.
     * 
     * @return Config
     */
    public function getConfig() {
        return $this->container->get('config.'. $this->getName());
    }

    /**
     * Sets the dependency injection service container.
     * 
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container) {
        $this->container = $container;
    }

    /**
     * Returns the dependency injection service container.
     * 
     * @return ContainerInterface
     */
    public function getContainer() {
        return $this->container;
    }

    /**
     * Returns a prefix that will be added to all routes from this module.
     * 
     * @return string|null
     */
    public function getUrlPrefix() {
        return $this->urlPrefix;
    }

    /**
     * Returns a namespace for all console commands from this module.
     * 
     * @return string|null
     */
    public function getCommandNamespace() {
        return $this->commandNamespace;
    }

    /**
     * Returns location of the config directory for this module.
     * 
     * @return string
     */
    public function getConfigDir() {
        return $this->getModuleDir() .'/Resources/config';
    }

    /**
     * Returns class name for this module.
     * 
     * @return string
     */
    public function getClass() {
        if ($this->class) {
            return $this->class;
        }

        $this->class = Debugger::getClass($this);
        return $this->class;
    }

    /**
     * Returns namespace of this module.
     * 
     * @return string
     */
    public function getNamespace() {
        if ($this->namespace) {
            return $this->namespace;
        }

        $this->namespace = Debugger::getNamespace($this);
        return $this->namespace;
    }

    /**
     * Returns directory where this module is located.
     * 
     * @return string
     */
    public function getModuleDir() {
        if ($this->moduleDir) {
            return $this->moduleDir;
        }

        $this->moduleDir = dirname(Debugger::getClassFile($this));
        return $this->moduleDir;
    }

}
