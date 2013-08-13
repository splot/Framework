<?php
/**
 * Dependency Injection Service Container.
 * 
 * @package SplotFramework
 * @subpackage DependencyInjection
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\DependencyInjection;

use MD\Foundation\Exceptions\InvalidArgumentException;
use MD\Foundation\Exceptions\NotFoundException;

use Splot\Framework\DependencyInjection\Exceptions\ReadOnlyDefinitionException;

class ServiceContainer
{

    /**
     * Container for all defined services.
     * 
     * @param array
     */
    private $_services = array();

    /**
     * Container for all defined parameters.
     * 
     * @param array
     */
    private $_parameters = array();

    /**
     * Sets a service definition by passing an anonymous function that is a factory of this service.
     * 
     * @param string $name Service unique name.
     * @param callable|object $factory Either service factory function or an instance of the service for direct return.
     * @param bool $readOnly [optional] Should this service be read only, ie. cannot be overwritten (but still can be extended). Default: false.
     * @param bool $singleton [optional] Should this service be a singleton, ie. once created it should always return the created instance? Default: false.
     * 
     * @throws ReadOnlyDefinitionException When service with the same name is already defined and marked as read only.
     */
    public function set($name, $serviceFactory, $readOnly = false, $singleton = false) {
        if (isset($this->_services[$name]) && $this->_services[$name]['readOnly']) {
            throw new ReadOnlyDefinitionException('The service "'. $name .'" is marked as read only and cannot be overwritten.');
        }

        // differentiate between callable and instance of the service
        if (is_callable($serviceFactory)) {
            $instance = null;
            $factory = $serviceFactory;
        } else {
            $instance = $serviceFactory;
            $singleton = true;
            /** 
             * @codeCoverageIgnore 
             * This factory isn't really called anywhere as instance is already set,
             * but the factory is created anyway just for safety's sake if the instance was lost for some reason.
             */
            $factory = function() use ($instance) {
                return $instance;
            };
        }

        // store the service data
        $this->_services[$name] = array(
            'factory' => $factory,
            'readOnly' => $readOnly,
            'singleton' => $singleton,
            'instance' => $instance,
            'callbacks' => array()
        );
    }

    /**
     * Retrieves the service with the given name.
     * 
     * @param string $name Name of the service to retrieve.
     * 
     * @throws NotFoundException When service with this name cannot be found.
     */
    public function get($name) {
        if (!isset($this->_services[$name])) {
            throw new NotFoundException('Service with name "'. $name .'" could not be found.');
        }

        if ($this->_services[$name]['singleton'] && $this->_services[$name]['instance'] !== null) {
            $service = $this->_services[$name]['instance'];
        } else {
            // create the service based on its factory
            $service = call_user_func_array($this->_services[$name]['factory'], array($this));

            if ($this->_services[$name]['singleton']) {
                $this->_services[$name]['instance'] = $service;
            }
        }

        // call any defined callbacks
        foreach($this->_services[$name]['callbacks'] as $callback) {
            // callbacks take the service as 1st argument and ServiceContainer as 2nd.
            call_user_func_array($callback, array($service, $this));
        }

        // return the service.
        return $service;
    }

    /**
     * Checks if a service with the given name has been defined.
     * 
     * @param string $name Name of the service to check.
     * @return bool
     */
    public function has($name) {
        return isset($this->_services[$name]);
    }

    /**
     * Removes the given service definition.
     * 
     * Also removes all its callbacks.
     * 
     * @param string $name Name of the service to remove.
     * 
     * @throws ReadOnlyDefinitionException When the service has been marked as read only, so cannot be removed.
     */
    public function remove($name) {
        if ($this->has($name)) {
            if ($this->_services[$name]['readOnly']) {
                throw new ReadOnlyDefinitionException('The service "'. $name .'" is marked as read only and cannot be removed.');
            }

            unset($this->_services[$name]);
        }
    }

    /**
     * Extends a service by calling the specified service factory callback after the service has been created.
     * 
     * @param string $name Name of the service to be extended.
     * @param callable $serviceFactoryCallback Callback to be called when the service is created. Takes two arguments: the created service and the ServiceContainer.
     * 
     * @throws NotFoundException When there is no such service defined yet.
     * @throws InvalidArgumentException When the 2nd argument is not a callable.
     */
    public function extend($name, $serviceFactoryCallback) {
        if (!$this->has($name)) {
            throw new NotFoundException('Service with name "'. $name .'" could not be found.');
        }

        if (!is_callable($serviceFactoryCallback)) {
            throw new InvalidArgumentException('callable', $serviceFactoryCallback, 2);
        }

        $this->_services[$name]['callbacks'][] = $serviceFactoryCallback;
    }

    /**
     * Lists all defined services.
     * 
     * @return array
     */
    public function listServices() {
        return array_keys($this->_services);
    }

    /**
     * Sets a paremeter to the given value.
     * 
     * @param string $name Name of the parameter.
     * @param mixed $value Value of the parameter.
     */
    public function setParameter($name, $value) {
        $this->_parameters[$name] = $value;
    }

    /**
     * Returns the given parameter.
     * 
     * @param string $name Name of the parameter.
     * @return mixed
     * 
     * @throws NotFoundException When there is no such parameter.
     */
    public function getParameter($name) {
        if (!isset($this->_parameters[$name])) {
            throw new NotFoundException('Parameter with name "'. $name .'" could not be found.');
        }
        return $this->_parameters[$name];
    }

    /**
     * Checks if the given parameter was defined.
     * 
     * @param string $name Name of the parameter.
     * @return bool
     */
    public function hasParameter($name) {
        return isset($this->_parameters[$name]);
    }

    /**
     * Removes the given parameter.
     * 
     * @param string $name Name of the parameter.
     */
    public function removeParameter($name) {
        if ($this->hasParameter($name)) {
            unset($this->_parameters[$name]);
        }
    }

    /**
     * Lists all defined parameters.
     * 
     * @return array
     */
    public function listParameters() {
        return array_keys($this->_parameters);
    }

}