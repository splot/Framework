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

use Splot\DependencyInjection\Container;

class ServiceContainer extends Container
{

    /**
     * Resolve parameters in a string or array.
     * 
     * @param  string|array $variable Variable to have parameters resolved.
     * @return string|array
     */
    public function resolveParameters($variable) {
        return $this->parametersResolver->resolve($variable);
    }
    
}
