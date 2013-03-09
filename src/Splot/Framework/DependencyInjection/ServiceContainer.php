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

use Splot\Foundation\Exceptions\NotUniqueException;
use Splot\Foundation\Exceptions\NotFoundException;

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
     * @param \Closure $factory Service factory function.
     * @param bool $readOnly [optional] Should this service be read only, ie. cannot be overwritten (but still can be extended). Default: false.
     * @param bool $singleton [optional] Should this service be a singleton, ie. once created it should always return the created instance? Default: false.
     * 
     * @throws NotUniqueException When service with the same name is already defined.
     */
    public function set($name, \Closure $serviceFactory, $readOnly = false, $singleton = false) {
        if (isset($this->_services[$name])) {
            throw new NotUniqueException('Service with name "'. $name .'" is already defined.');
        }

        // store the service data
        $this->_services[$name] = array(
            'factory' => $serviceFactory,
            'readOnly' => $readOnly,
            'singleton' => $singleton,
            'instance' => null,
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
     * @param \Closure $serviceFactoryCallback Callback to be called when the service is created. Takes two arguments: the created service and the ServiceContainer.
     * 
     * @throws NotFoundException When there is no such service defined yet.
     */
    public function extend($name, \Closure $serviceFactoryCallback) {
        if ($this->has($name)) {
            throw new NotFoundException('Service with name "'. $name .'" could not be found.');
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
     */
    public function getParameter($name) {
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