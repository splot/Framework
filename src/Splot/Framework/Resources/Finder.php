<?php
/**
 * Resource finder.
 * 
 * @package SplotFramework
 * @subpackage Resources
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Resources;

use Splot\Framework\Application\AbstractApplication;
use Splot\Framework\Resources\Exceptions\ResourceNotFoundException;

class Finder
{

    /**
     * Reference to the application.
     * 
     * @var AbstractApplication
     */
    private $_application;

    /**
     * Cache for all resources already found.
     * 
     * @var array
     */
    private $_cache = array();

    /**
     * Constructor.
     * 
     * @param AbstractApplication $application Reference to the application.
     */
    public function __construct(AbstractApplication $application) {
        $this->_application = $application;
    }

    /**
     * Finds path to the given resource based on its name and type.
     * 
     * @param string $name Name of the resource in the format ModuleName:ResourceNameSpace:resourcename
     * @param string $type Type of the resource, e.g. "view". Really: sub dir of the module Resources dir where the resource could be.
     * @return string
     */
    public function findResource($name, $type) {
        if (isset($this->_cache[$type]) && isset($this->_cache[$type][$name])) {
            return $this->_cache[$type][$name];
        }

        $nameArray = explode(':', $name);

        if (empty($nameArray[0])) {
            $mainDir = $this->_application->getApplicationDir();
        } else {
            $module = $this->_application->getModule($nameArray[0]);
            if (!$module) {
                throw new ResourceNotFoundException('There is no module "'. $nameArray[0] .'" registered, so cannot find its resource.');
            }
            $mainDir = $module->getModuleDir();
        }

        $type = trim($type, DS);
        $type = empty($type) ? null : $type . DS;
        $subDir = trim(str_replace(NS, DS, $nameArray[1]), DS);
        $subDir = empty($subDir) ? null : $subDir . DS;

        // final path
        $path = $mainDir .'Resources'. DS . $type . $subDir . $nameArray[2];

        if (!file_exists($path)) {
            throw new ResourceNotFoundException('Resource "'. $name .'" not found at path "'. $path .'".');
        }

        if (!isset($this->_cache[$type])) {
            $this->_cache[$type] = array();
        }

        $this->_cache[$type][$name] = $path;

        return $path;
    }

    /*
     * SETTERS AND GETTERS
     */
    /**
     * Returns the application.
     * 
     * @return AbstractApplication
     */
    public function getApplication() {
        return $this->_application;
    }

}