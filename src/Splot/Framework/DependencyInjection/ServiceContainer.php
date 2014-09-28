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

    public function getParameters() {
        return $this->dumpParameters();
    }
    
}
