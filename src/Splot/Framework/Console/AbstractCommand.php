<?php
/**
 * Abstract command for Splot Console, from which all commands should inherit.
 * 
 * @package SplotFramework
 * @subpackage Console
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Console;

use MD\Foundation\Exceptions\NotFoundException;

use Splot\Framework\DependencyInjection\ServiceContainer;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractCommand
{

    /**
     * Command name.
     * 
     * @var string
     */
    protected static $name;

    /**
     * Command description.
     * 
     * @var string
     */
    protected static $description = '';

    /**
     * Command help.
     * 
     * @var string
     */
    protected static $help = '';

    /**
     * Command arguments descriptions.
     * 
     * @var array
     */
    protected static $arguments = array();

    /**
     * Command options.
     * 
     * @var array
     */
    protected static $options = array();

    /**
     * Dependency injection service container.
     * 
     * @var ServiceContainer
     */
    protected $container;

    /**
     * Command line interface input.
     * 
     * @var InputInterface
     */
    protected $input;

    /**
     * Command line interface output.
     * 
     * @var OutputInterface
     */
    protected $output;

    /**
     * Options with which the command was ran.
     * 
     * @var array
     */
    protected $_options = array();

    /**
     * Constructor.
     * 
     * @param ServiceContainer $container Dependency injection service container.
     */
    public function __construct(ServiceContainer $container, InputInterface $input, OutputInterface $output) {
        $this->container = $container;
        $this->input = $input;
        $this->output = $output;
    }

    /*****************************************
     * HELPERS
     *****************************************/
    /**
     * Returns a service with the given name.
     * 
     * Shortcut to container.
     * 
     * @param string $name Name of the service to return.
     * @return object
     */
    final public function get($name) {
        return $this->getContainer()->get($name);
    }

    /**
     * Writes into output.
     * 
     * @param array|string $messages An array of or a single message to write.
     * @param bool $newline [optional] Should end with new line? Default: false.
     */
    final public function write($message, $newline = false) {
        $this->output->write($message, $newline);
    }

    /**
     * Writes into output and goes to next line.
     * 
     * @param array|string $messages An array of or a single message to write.
     */
    final public function writeln($message) {
        $this->output->writeln($message);
    }

    /*****************************************
     * SETTERS AND GETTERS
     *****************************************/
    /**
     * Returns the dependency injection service container.
     * 
     * @return ServiceContainer
     */
    final public function getContainer() {
        return $this->container;
    }

    /**
     * Returns command line interface input.
     * 
     * @return InputInterface
     */
    final public function getInput() {
        return $this->input;
    }

    /**
     * Returns command line interface output.
     * 
     * @return OutputInterface
     */
    final public function getOutput() {
        return $this->output;
    }

    /**
     * Sets the options with which the command should be run.
     * 
     * @param array $options [optional]
     */
    final public function setOptions(array $options = array()) {
        $this->_options = $options;
    }

    /**
     * Returns the value of the requested option.
     * 
     * @param string $name Name of the option which value should be returned.
     * @return mixed
     * 
     * @throws NotFoundException When option with the given name was not found.
     */
    final public function getOption($name) {
        if (!isset($this->_options[$name])) {
            throw new NotFoundException('Option "'. $name .'" was not found for command "'. static::getName() .' ('. static::__class() .')".');
        }

        return $this->_options[$name];
    }

    /**
     * Returns the name of the command.
     * 
     * @return string
     */
    final public static function getName() {
        return static::$name;
    }

    /**
     * Returns the description of the command.
     * 
     * @return string
     */
    final public static function getDescription() {
        return static::$description;
    }

    /**
     * Returns the help of the command.
     * 
     * @return string
     */
    final public static function getHelp() {
        return static::$help;
    }

    /**
     * Returns the descriptions of the arguments of the command.
     * 
     * @return array
     */
    final public static function getArguments() {
        return static::$arguments;
    }

    /**
     * Returns options for the command.
     * 
     * @return array
     */
    final public static function getOptions() {
        return static::$options;
    }

    /**
     * Returns class name of the controller.
     * 
     * @return string
     */
    final public static function __class() {
        return get_called_class();
    }

}