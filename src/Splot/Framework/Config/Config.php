<?php
/**
 * Holds config information about Splot Framework and its application or modules.
 * 
 * @package SplotFramework
 * @subpackage Config
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Config;

use MD\Foundation\Exceptions\NotFoundException;
use MD\Foundation\Exceptions\InvalidFileException;
use MD\Foundation\Utils\ArrayUtils;

class Config
{

    /**
     * List of files loaded when creating this config.
     * 
     * @var array
     */
    private $_files = array();

    /**
     * Config variables holder.
     * 
     * @var array
     */
    private $_config = array();

    /**
     * Reads configs from the given directory. First it reads a "global" config and then merges an environment specific config on top of it (if found).
     * 
     * @param string $configDir Path to a directory where the configs are stored.
     * @param string $env Name of the environment.
     * @return Config
     * 
     * @throws InvalidFileException When any of the config files found in the $configDir return a config array.
     */
    public static function read($configDir, $env) {
        $configDir = rtrim($configDir, DS) . DS;
        $config = array();
        $files = array();

        // firstly include a global config, env agnostic (if it exists)
        $globalFile = $configDir .'config.php';
        if (file_exists($globalFile)) {
            $included = include $globalFile;
            if (is_array($included)) {
                $files[] = $globalFile;
                $config = $included;
            } else {
                throw new InvalidFileException('Config file "'. $globalFile .'" does not return a config array.');
            }
        }

        // then include config specific for the requested environment and merge it into the previous config
        $envFile = $configDir .'config.'. $env .'.php';
        if (file_exists($envFile)) {
            $included = include $envFile;
            if (is_array($included)) {
                $files[] = $envFile;
                $config = array_merge_recursive($config, $included);
            } else {
                throw new InvalidFileException('Config file "'. $envFile .'" does not return a config array.');
            }
        }

        // and finally instantiate Config based on the data
        return new self($config, $files);
    }

    /**
     * Creates a config instance based on the passed array.
     * 
     * Optionally, you can also pass a list of files that were loaded - for debugging purposes.
     * 
     * @param array $config Configuration array.
     * @param array $files [optional] List of loaded files.
     */
    final public function __construct(array $config, array $files = array()) {
        $this->_config = $config;
        $this->_files = $files;
    }

    /**
     * Apply the given array of options onto the already loaded config. 
     * 
     * Extend the already loaded config.
     * 
     * @param array $options Array of config options.
     */
    public function apply(array $options) {
        $this->_config = ArrayUtils::merge($this->_config, $options);
    }

    /**
     * Get config variable at the given path.
     * 
     * Path is separated by '.' and traverses through the config array.
     * 
     * @param string $path Variable path / name.
     * @return mixed
     * 
     * @throws NotFoundException When the requested path is not found anywhere in the configs.
     */
    public function get($path) {
        $exPath = trim($path, '.');
        $exPath = explode('.', $exPath);

        // navigate to the requested parameter
        $pointer = &$this->_config;
        foreach($exPath as $name) {
            if (isset($pointer[$name])) {
                $pointer = &$pointer[$name];
            } else if (is_null($pointer[$name])) {
                $pointer = null;
            } else {
                throw new NotFoundException('The requested config variable "'. $path .'" does not exist');
            }
        }

        return $pointer;
    }

    /**
     * Get the full config in form of array found under the given namespace.
     * 
     * @param string $namespace [optional] If no namespace specified then the global config is returned.
     * @return array
     */
    public function getNamespace($namespace = null) {
        // if no namespace then return the global config
        if (empty($namespace)) {
            return $this->_config;
        }

        // if no such namespace then return empty array
        if (!isset($this->_config[$namespace])) {
            return array();
        }

        return $this->_config[$namespace];
    }

    /**
     * Returns list of files that were read to build this config.
     * 
     * @return array
     */
    public function getReadFiles() {
        return $this->_files;
    }

}