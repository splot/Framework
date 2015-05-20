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
     * @var ServiceContainer
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
     * @return array
     */
    public function loadModules() {
        return array();
    }

    public function configure() {
        try {
            $this->container->loadFromFile($this->getConfigDir() .'services.yml');
        } catch(NotFoundException $e) {}
    }

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
    final public function setContainer(ContainerInterface $container) {
        $this->container = $container;
    }

    /**
     * Returns the dependency injection service container.
     * 
     * @return ContainerInterface
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
        return $this->getModuleDir() .'Resources'. DS .'config'. DS;
    }

    /**
     * Returns class name for this module.
     * 
     * @return string
     */
    final public function getClass() {
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
    final public function getNamespace() {
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
    final public function getModuleDir() {
        if ($this->moduleDir) {
            return $this->moduleDir;
        }

        $this->moduleDir = dirname(Debugger::getClassFile($this)) . DS;
        return $this->moduleDir;
    }

}
