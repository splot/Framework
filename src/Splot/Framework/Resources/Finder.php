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
use MD\Foundation\Utils\FilesystemUtils;

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
     * GLOB patterns are allowed in the file name section of the resource pattern.
     *
     * If using GLOB patterns and multiple files were found then they are returned in an array.
     * 
     * @param string $name Name of the resource in the format ModuleName:ResourceNameSpace:resourcename
     * @param string $type Type of the resource, e.g. "view". Really: sub dir of the module Resources dir where the resource could be.
     * @return string|array
     * 
     * @throws ResourceNotFoundException When the resource could not be found (does not exist).
     */
    public function find($name, $type) {
        if (isset($this->_cache[$type]) && isset($this->_cache[$type][$name])) {
            return $this->_cache[$type][$name];
        }

        $nameArray = explode(':', $name);

        $appFiles = array();
        $moduleFiles = array();

        // at first try application resources dir
        try {
            $appFiles = $this->findInApplicationDir($name, $type);
            $appFiles = !is_array($appFiles) ? array($appFiles) : $appFiles;
        } catch(ResourceNotFoundException $e) {
            // and if that failed, check in the module dir (assuming that the module name is specified)
            if (empty($nameArray[0])) {
                // rethrow the original exception then
                throw $e;
            }
        }

        // if module name is specified then also look in the module
        if (!empty($nameArray[0])) {
            try {
                $moduleFiles = $this->findInModuleDir($name, $type);
                $moduleFiles = !is_array($moduleFiles) ? array($moduleFiles) : $moduleFiles;

                // now remove any of the module files that were overwritten in the application dir
                $appDir = rtrim($this->_application->getApplicationDir(), '/') . DS . 'Resources' . DS;
                $appDirLength = strlen($appDir);
                $moduleDir = rtrim($this->_application->getModule($nameArray[0])->getModuleDir(), '/') . DS .'Resources'. DS;
                $moduleDirLength = strlen($moduleDir);

                $rawAppFiles = array();
                foreach($appFiles as $file) {
                    $rawAppFile = substr($file, $appDirLength);
                    $rawAppFiles[$rawAppFile] = $file;
                }

                foreach($moduleFiles as $i => $file) {
                    $rawModuleFile = $nameArray[0] .'/'. substr($file, $moduleDirLength);
                    if (isset($rawAppFiles[$rawModuleFile])) {
                        unset($moduleFiles[$i]);
                    }
                }

            } catch(ResourceNotFoundException $e) {
                // if something was found in the app dir then we're good - ignore this exception
                // otherwise rethrow it
                if (empty($appFiles)) {
                    throw $e;
                }
            }
        }

        $files = array_merge($appFiles, $moduleFiles);

        // if only one result then return it
        if (count($files) === 1) {
            $files = $files[0];
        }

        // cache the result
        if (!isset($this->_cache[$type])) {
            $this->_cache[$type] = array();
        }
        $this->_cache[$type][$name] = $files;

        return $files;
    }

    /**
     * Finds path to the given resource in the application resources dir.
     * 
     * @param string $name Name of the resource in the format ModuleName:ResourceNameSpace:resourcename
     * @param string $type Type of the resource, e.g. "view". Really: sub dir of the module Resources dir where the resource could be.
     * @return string
     * 
     * @throws ResourceNotFoundException When the resource could not be found (does not exist).
     */
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

        $files = $this->buildResourcePath($mainDir, $type, $nameArray[1], $nameArray[2]);

        // multiple files were found by glob so just return them (glob wouldn't return nonexistent files)
        if (is_array($files)) {
            return $files;
        }

        // single file was returned
        if (!file_exists($files)) {
            throw new ResourceNotFoundException('Resource "'. $name .'" not found in application resources dir at path "'. $files .'".');
        }

        return $files;
    }

    /**
     * Finds path to the given resource in the resource's module dir.
     * 
     * @param string $name Name of the resource in the format ModuleName:ResourceNameSpace:resourcename
     * @param string $type Type of the resource, e.g. "view". Really: sub dir of the module Resources dir where the resource could be.
     * @return string
     * 
     * @throws ResourceNotFoundException When the resource could not be found (does not exist).
     */
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

        $files = $this->buildResourcePath(rtrim($mainDir, '/') . DS .'Resources'. DS, $type, $nameArray[1], $nameArray[2]);

        // multiple files were found by glob so just return them (glob wouldn't return nonexistent files)
        if (is_array($files)) {
            return $files;
        }

        if (!file_exists($files)) {
            throw new ResourceNotFoundException('Resource "'. $name .'" not found at path "'. $files .'".');
        }

        return $files;
    }

    /**
     * Helper function for building paths to resources.
     *
     * GLOB patterns can be used in $file.
     *
     * If GLOB matches more than one file then an array of those matched files is returned.
     * 
     * @param string $mainDir Root of the directory on which to build.
     * @param string $type Type of the resource.
     * @param string $subDir Any subdirectory under which the asset is.
     * @param string $file Name of the resource's file. GLOB patterns are allowed here.
     * @return string|array
     */
    private function buildResourcePath($mainDir, $type, $subDir, $file) {
        $typeDir = trim($type, DS);
        $typeDir = empty($type) ? null : $type . DS;
        $subDir = trim(str_replace(NS, DS, $subDir), DS);
        $subDir = empty($subDir) ? null : $subDir . DS;

        // final pattern
        $pattern = $mainDir . $typeDir . $subDir . $file;

        $files = FilesystemUtils::glob($pattern, GLOB_NOCHECK | GLOB_BRACE);

        return is_array($files) && count($files) === 1 ? $files[0] : $files;
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