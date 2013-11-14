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

use MD\Foundation\Exceptions\InvalidArgumentException;

use Splot\Framework\Application\AbstractApplication;
use Splot\Framework\Modules\AbstractModule;
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
     * 
     * @throws ResourceNotFoundException When the resource could not be found (does not exist).
     */
    public function find($name, $type) {
        if (isset($this->_cache[$type]) && isset($this->_cache[$type][$name])) {
            return $this->_cache[$type][$name];
        }

        // at first try application resources dir
        try {
            $path = $this->findInApplicationDir($name, $type);
        } catch(ResourceNotFoundException $e) {
            // and if that failed, check in the module dir (assuming that the module name is specified)
            $nameArray = explode(':', $name);
            if (empty($nameArray[0])) {
                // rethrow the original exception then
                throw $e;
            }

            $path = $this->findInModuleDir($name, $type);
        }

        if (!isset($this->_cache[$type])) {
            $this->_cache[$type] = array();
        }

        $this->_cache[$type][$name] = $path;

        return $path;
    }

    public function findInApplicationDir($name, $type) {
        $nameArray = explode(':', $name);

        if (count($nameArray) !== 3) {
            throw new InvalidArgumentException('in format "[ModuleName]:[subDir]:filename"', $name);
        }

        // point to the application resources dir
        $mainDir = rtrim($this->_application->getApplicationDir(), '/') . DS . 'Resources' . DS;

        // if a module name was specified then point to a subfolder with the modulename name
        if (!empty($nameArray[0])) {
            // check for module existence in the first place
            if (!$this->_application->hasModule($nameArray[0])) {
                throw new ResourceNotFoundException('There is no module "'. $nameArray[0] .'" registered, so cannot find its resource.');
            }

            $mainDir = $mainDir . $nameArray[0] . DS;
        }

        $path = $this->buildResourcePath($mainDir, $type, $nameArray[1], $nameArray[2]);

        if (!file_exists($path)) {
            throw new ResourceNotFoundException('Resource "'. $name .'" not found in application resources dir "'. $path .'".');
        }

        return $path;
    }

    public function findInModuleDir($name, $type) {
        // otherwise check in the module dir
        $nameArray = explode(':', $name);

        if (count($nameArray) !== 3 || empty($nameArray[0])) {
            throw new InvalidArgumentException('in format "ModuleName:[subDir]:filename"', $name);
        }

        if (!$this->_application->hasModule($nameArray[0])) {
            throw new ResourceNotFoundException('There is no module "'. $nameArray[0] .'" registered, so cannot find its resource.');
        }

        $module = $this->_application->getModule($nameArray[0]);
        $mainDir = $module->getModuleDir();

        $path = $this->buildResourcePath(rtrim($mainDir, '/') . DS .'Resources'. DS, $type, $nameArray[1], $nameArray[2]);

        if (!file_exists($path)) {
            throw new ResourceNotFoundException('Resource "'. $name .'" not found at path "'. $path .'".');
        }

        return $path;
    }

    private function buildResourcePath($mainDir, $type, $subDir, $file) {
        $typeDir = trim($type, DS);
        $typeDir = empty($type) ? null : $type . DS;
        $subDir = trim(str_replace(NS, DS, $subDir), DS);
        $subDir = empty($subDir) ? null : $subDir . DS;

        // final path
        return $mainDir . $typeDir . $subDir . $file;
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