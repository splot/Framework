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
use MD\Foundation\Utils\ArrayUtils;
use MD\Foundation\Utils\FilesystemUtils;

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
     * Cache for all single resources already found.
     * 
     * @var array
     */
    private $_resourceCache = array();

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
     * GLOB patterns are allowed in the file name section of the resource pattern.
     *
     * If using GLOB patterns and multiple files were found then they are returned in an array.
     * 
     * @param string $resource Name of the resource in the format ModuleName:ResourceNameSpace:resourcename
     * @param string $type Type of the resource, e.g. "view". Really: sub dir of the module Resources dir where the resource could be.
     * @return string|array
     * 
     * @throws ResourceNotFoundException When the resource could not be found (does not exist).
     */
    public function find($resource, $type) {
        $cacheKey = $type .'#'. $resource;
        if (isset($this->_cache[$cacheKey])) {
            return $this->_cache[$cacheKey];
        }

        $expanded = $this->expand($resource, $type);

        if (empty($expanded)) {
            throw new ResourceNotFoundException('Could not find resource "'. $resource .'".');
        }

        $found = array();
        foreach($expanded as $resource) {
            $found[] = $this->findResource($resource, $type);
        }

        $this->_cache[$cacheKey] = count($found) === 1 ? $found[0] : $found;
        return $this->_cache[$cacheKey];
    }

    /**
     * Finds a single resource. Does not support GLOB patterns.
     *
     * If you want to use GLOB patterns, use the find() method.
     * 
     * @param  string $resource Name of the resource to find.
     * @param  string $type     Type of the resource.
     * @return string
     */
    public function findResource($resource, $type) {
        $cacheKey = $type .'#'. $resource;
        if (isset($this->_resourceCache[$cacheKey])) {
            return $this->_resourceCache[$cacheKey];
        }

        list($moduleName) = $this->parseResourceName($resource);

        // at first try application resources dir
        // we do it always because some module resources may be overwritten in app resources dir
        try {
            $file = $this->findInApplicationDir($resource, $type);
        } catch(ResourceNotFoundException $e) {
            // if this was a resource from app dir (no module name defined) then rethrow the exception
            if (empty($moduleName)) {
                throw $e;
            }

            $file = $this->findInModuleDir($resource, $type);
        }

        $this->_resourceCache[$cacheKey] = $file;
        return $this->_resourceCache[$cacheKey];
    }

    /**
     * Finds path to the given resource in the application resources dir.
     * 
     * @param string $resource Name of the resource in the format ModuleName:ResourceNameSpace:resourcename
     * @param string $type Type of the resource, e.g. "view". Really: sub dir of the module Resources dir where the resource could be.
     * @return string
     * 
     * @throws ResourceNotFoundException When the resource could not be found (does not exist).
     */
    public function findInApplicationDir($resource, $type) {
        list($moduleName, $subDir, $resourceFile) = $this->parseResourceName($resource);

        // point to the application resources dir
        $mainDir = rtrim($this->_application->getApplicationDir(), DS) . DS . 'Resources' . DS;

        // if a module name was specified then point to a subfolder with the module name
        if (!empty($moduleName)) {
            // check for module existence in the first place
            if (!$this->_application->hasModule($moduleName)) {
                throw new ResourceNotFoundException('There is no module "'. $moduleName .'" registered, so cannot find its resource.');
            }

            $mainDir = $mainDir . $moduleName . DS;
        }

        $path = $this->buildResourcePath($mainDir, $type, $subDir, $resourceFile);

        // single file was returned
        if (!file_exists($path)) {
            throw new ResourceNotFoundException('Resource "'. $resource .'" not found in application resources dir at path "'. $path .'".');
        }

        return $path;
    }

    /**
     * Finds path to the given resource in the resource's module dir.
     * 
     * @param string $resource Name of the resource in the format ModuleName:ResourceNameSpace:resourcename
     * @param string $type Type of the resource, e.g. "view". Really: sub dir of the module Resources dir where the resource could be.
     * @return string
     * 
     * @throws ResourceNotFoundException When the resource could not be found (does not exist).
     */
    public function findInModuleDir($resource, $type) {
        list($moduleName, $subDir, $resourceFile) = $this->parseResourceName($resource, true);

        if (!$this->_application->hasModule($moduleName)) {
            throw new ResourceNotFoundException('There is no module "'. $moduleName .'" registered, so cannot find its resource.');
        }

        $module = $this->_application->getModule($moduleName);
        $mainDir = $module->getModuleDir();

        $path = $this->buildResourcePath(rtrim($mainDir, DS) . DS .'Resources'. DS, $type, $subDir, $resourceFile);

        if (!file_exists($path)) {
            throw new ResourceNotFoundException('Resource "'. $resource .'" not found at path "'. $path .'".');
        }

        return $path;
    }

    /**
     * Expands GLOB resource patterns.
     * 
     * @param  string $resource Resource name.
     * @param  string $type    Type of the resource.
     * @return array
     */
    public function expand($resource, $type) {
        list($moduleName, $subDir, $filePattern) = $this->parseResourceName($resource);

        $resourceLocation = $moduleName .':'. $subDir .':';

        // read from application dir
        $appDir = rtrim($this->_application->getApplicationDir(), DS) . DS . 'Resources' . DS;

        // if a module name was specified then point to a subfolder with the module name
        if (!empty($moduleName)) {
            // check for module existence in the first place
            if (!$this->_application->hasModule($moduleName)) {
                throw new ResourceNotFoundException('There is no module "'. $moduleName .'" registered, so cannot find its resource.');
            }

            $appDir = $appDir . $moduleName . DS;
        }

        $appDir = $this->buildResourcePath($appDir, $type, $subDir, '');
        $appFiles = FilesystemUtils::glob($appDir . $filePattern, FilesystemUtils::GLOB_ROOTFIRST | GLOB_BRACE);

        $resources = array();
        foreach($appFiles as $file) {
            $resources[] = $resourceLocation . substr($file, mb_strlen($appDir));
        }

        // now take care of the module dir
        if ($moduleName) {
            $module = $this->_application->getModule($moduleName);
            $moduleDir = rtrim($module->getModuleDir(), DS) . DS . 'Resources' . DS;
            $moduleDir = $this->buildResourcePath($moduleDir, $type, $subDir, '');

            $moduleFiles = FilesystemUtils::glob($moduleDir . $filePattern, GLOB_BRACE);

            foreach($moduleFiles as $file) {
                $resources[] = $resourceLocation . substr($file, mb_strlen($moduleDir));
            }
        }

        $resources = array_unique($resources);
        return ArrayUtils::sortPaths($resources, true);
    }

    /**
     * Helper function for building paths to resources.
     * 
     * @param string $mainDir Root of the directory on which to build.
     * @param string $type Type of the resource.
     * @param string $subDir Any subdirectory under which the asset is.
     * @param string $file Name of the resource's file. GLOB patterns are allowed here.
     * @return string
     */
    private function buildResourcePath($mainDir, $type, $subDir, $file) {
        $typeDir = trim($type, DS);
        $typeDir = empty($type) ? null : $type . DS;
        $subDir = trim(str_replace(NS, DS, $subDir), DS);
        $subDir = empty($subDir) ? null : $subDir . DS;

        return $mainDir . $typeDir . $subDir . $file;
    }

    /**
     * Helper function for validating and parsing a resource name.
     * 
     * @param  string  $name           Resource name.
     * @param  boolean $moduleRequired [optional] Is module name required? Default: false.
     * @return array
     *
     * @throws InvalidArgumentException When the format is invalid.
     */
    private function parseResourceName($name, $moduleRequired = false) {
        $nameArray = explode(':', $name);
        if (count($nameArray) !== 3) {
            throw new InvalidArgumentException('in format "[ModuleName]:[subDir]:filename"', $name);
        }

        if ($moduleRequired && empty($nameArray[0])) {
            throw new InvalidArgumentException('in format "ModuleName:[subDir]:filename"', $name);
        }

        return $nameArray;
    }

    /**************************
     * SETTERS AND GETTERS
     **************************/
    /**
     * Returns the application.
     * 
     * @return AbstractApplication
     */
    public function getApplication() {
        return $this->_application;
    }

}