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
use MD\Foundation\Utils\FilesystemUtils;

use Symfony\Component\Yaml\Yaml;

use Splot\Framework\DependencyInjection\ServiceContainer;

class Config
{

    /**
     * Splot Dependency Injection Container for resolving parameters inside config variables.
     * 
     * @var ServiceContainer
     */
    protected $container;

    /**
     * Config variables.
     * 
     * @var array
     */
    protected $config = array();

    /**
     * List of loaded files.
     * 
     * @var array
     */
    protected $loadedFiles = array();

    /**
     * Reads config files from the given directory, but only the ones called `config.(php|yml|yaml)`.
     *
     * Optionally, if `$env` is defined, then it will also try to load `config.$env.(php|yml|yaml)` files.
     *
     * PHP config files are loaded before the YML files.
     * 
     * @param  ServiceContainer $container Splot DI Container for resolving parameters inside the config.
     * @param  string           $dir       Directory which should be searched for config files.
     * @param  string           $env       [optional] Environment for which to load additional files. Default: `null`.
     * @return Config
     */
    public static function readFromDir(ServiceContainer $container, $dir, $env = null) {
        $dir = rtrim($dir, DS) . DS;
        $config = new static($container);

        $files = FilesystemUtils::glob($dir .'config.{yml,yaml,php}', GLOB_BRACE);
        if ($env) {
            $files = array_merge($files, FilesystemUtils::glob($dir .'config.'. $env .'.{yml,yaml,php}', GLOB_BRACE));
        }

        foreach($files as $file) {
            $config->loadFromFile($file);
        }

        return $config;
    }
   
    /**
     * Constructor.
     * 
     * @param ServiceContainer $container Splot DI Container for resolving parameters inside the config.
     * @param string           $loadFile  [optional] Optional file to load into the config.
     */
    public function __construct(ServiceContainer $container, $loadFile = null) {
        $this->container = $container;
        if ($loadFile) {
            $this->loadFromFile($loadFile);
        }
    }

    /**
     * Apply the given array of options onto the already loaded config. 
     * 
     * Extend the already loaded config.
     * 
     * @param array $options Array of config options.
     * @return bool
     */
    public function apply(array $options) {
        $this->config = ArrayUtils::merge($this->config, $options);
        return true;
    }

    /**
     * Extends the config with the given config.
     * 
     * @param Config $config Config to read from.
     * @return bool
     */
    public function extend(Config $config) {
        $this->config = ArrayUtils::merge($this->config, $config->getNamespace());
        $this->loadedFiles = array_merge($this->loadedFiles, $config->getLoadedFiles());
        return true;
    }

    /**
     * Load config from the given file.
     * 
     * @param  string $file Path to the file.
     * @return bool
     *
     * @throws NotFoundException If the file could not be found.
     * @throws InvalidFileException If could not read the given file format (currently only YAML is supported)
     */
    public function loadFromFile($file) {
        // if file already loaded then ignore it
        if (in_array($file, $this->loadedFiles)) {
            return true;
        }

        // check if file exists
        if (!is_file($file)) {
            throw new NotFoundException('Could not find file "'. $file .'" to load into the config.');
        }

        $extension = mb_strtolower(mb_substr($file, strrpos($file, '.') + 1));

        switch($extension) {
            case 'yml':
            case 'yaml':
                $settings = Yaml::parse(file_get_contents($file));
                break;

            case 'php':
                $settings = include $file;
                break;

            default:
                throw new InvalidFileException('Unrecognized file type "'. $extension .'" could not be loaded into the container. Only supported file formats are YAML (.yml, .yaml) and PHP (.php that returns an array).');
        }

        if (!is_array($settings)) {
            throw new InvalidFileException('File "'. $file .'" loaded into config is not in readable format.');
        }

        $this->apply($settings);
        $this->loadedFiles[] = $file;

        return true;
    }

    /**
     * Get config variable at the given path.
     * 
     * Path is separated by '.' and traverses through the config array.
     * 
     * @param string $path Variable path / name.
     * @param mixed $default [optional] Default value if there is no such thing defined in the config. Default: null.
     * @return mixed
     * 
     * @throws NotFoundException When the requested path is not found anywhere in the configs.
     */
    public function get($path, $default = null) {
        try {
            $exPath = trim($path, '.');
            $exPath = explode('.', $exPath);

            // navigate to the requested parameter
            $pointer = $this->config;
            foreach($exPath as $name) {
                if (!isset($pointer[$name])) {
                    throw new NotFoundException('The requested config variable "'. $path .'" does not exist.');
                }

                $pointer = $pointer[$name];
            }
        } catch(NotFoundException $e) {
            if ($default !== null) {
                $pointer = $default;
            } else {
                throw $e;
            }
        }

        return $this->container->resolveParameters($pointer);
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
            return $this->config;
        }

        // if no such namespace then return empty array
        if (!isset($this->config[$namespace])) {
            return array();
        }

        return $this->config[$namespace];
    }

    /**
     * Returns list of files that were loaded to build this config.
     * 
     * @return array
     */
    public function getLoadedFiles() {
        return $this->loadedFiles;
    }

}
