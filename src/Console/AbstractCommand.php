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

use Psr\Log\LoggerInterface;

use MD\Foundation\Exceptions\NotFoundException;

use Splot\DependencyInjection\ContainerInterface;

use Splot\Framework\Console\ConsoleLogger;

use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Helper\HelperSet;
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
     * @var ContainerInterface
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
     * Helper set.
     * 
     * @var HelperSet
     */
    protected $helperSet;

    /**
     * Options with which the command was ran.
     * 
     * @var array
     */
    protected $_options = array();

    /**
     * Constructor.
     * 
     * @param ContainerInterface $container Dependency injection service container.
     * @param InputInterface $input Input.
     * @param OutputInterface $output Output.
     * @param HelperSet $helperSet Helper set.
     */
    public function __construct(ContainerInterface $container, InputInterface $input, OutputInterface $output, HelperSet $helperSet) {
        $this->container = $container;
        $this->input = $input;
        $this->output = $output;
        $this->helperSet = $helperSet;
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
     * Returns a parameter with the given name.
     * 
     * Shortcut to container.
     * 
     * @param string $name Name of the parameter to return.
     * @return mixed
     */
    final public function getParameter($name) {
        return $this->getContainer()->getParameter($name);
    }

    /**
     * Writes into output.
     * 
     * @param array|string $messages [optional] An array of or a single message to write.
     * @param bool $newline [optional] Should end with new line? Default: false.
     */
    final public function write($message = null, $newline = false) {
        if (is_null($message)) {
            $message = '';
        }
        
        $this->output->write($message, $newline);
    }

    /**
     * Writes into output and goes to next line.
     * 
     * @param array|string $message [optional] An array of or a single message to write.
     */
    final public function writeln($message = null) {
        if (is_null($message)) {
            $message = '';
        }

        $this->output->writeln($message);
    }

    /*****************************************
     * CONSOLE HELPERS SHORTCUTS
     *****************************************/
    /**
     * Ask the user to confirm.
     * 
     * @param string $question Question to confirm.
     * @param bool $default [optional] Default answer. Default: false.
     * @return bool
     */
    final public function confirm($question, $default = false) {
        $question = $question .' <comment>['. ($default ? 'Y/n' : 'y/N') .']</comment>: ';
        return $this->getDialog()->askConfirmation($this->output, $question, $default);
    }

    /**
     * Ask the user to provide some info.
     * 
     * @param string $question Question based on which the user will provide info.
     * @param string $default Default answer.
     * @param array $autocomplete [optional] List of values to autocomplete.
     * @param callable $validate Validation function callback. It should throw exceptions with explanatory messages
     *                           if the input is invalid or return the answer to pass the validation. It takes one
     *                           argument which is the user's input.
     * @param bool $hidden [optional] Should the input be hidden? If true then default and autocomplete values will be ignored. Default: false.
     * @param int $attempts [optional] Number of attempts to allow in case of validation. Default: false - infinite.
     * @return string
     */ 
    final public function ask($question, $default = '', array $autocomplete = array(), $validate = null, $hidden = false, $attempts = false) {
        if (!empty($default)) {
            $question = $question .' [<comment>'. $default .'</comment>]';
        }
        $question = $question .': ';

        if ($hidden) {
            if (is_callable($validate)) {
                return $this->getDialog()->askHiddenResponseAndValidate($this->output, $question, $validate, $attempts);
            }

            return $this->getDialog()->askHiddenResponse($this->output, $question);
        }

        if (is_callable($validate)) {
            return $this->getDialog()->askAndValidate($this->output, $question, $validate, $attempts, $default);
        }

        return $this->getDialog()->ask($this->output, $question, $default, $autocomplete);
    }

    /**
     * Ask the user to choose from a list of options.
     * 
     * @param string $question Question to ask that helps with the choice.
     * @param array $options Array of possible options.
     * @param mixed $default [optional] Default value. This can be either an index of the default answer or the default answer itself.
     * @param bool $multi [optional] Should user be allowed to select multiple options? Default: false.
     * @param string $errorMessage [optional] Error message when user selects an invalid option. Default: 'There is no option %s!'.
     * @param int $attempts [optional] Number of allowed attempts. Default: false - infinite.
     * @return string|array The selected answer as string for a single choice or array of selected options for a multiple choice.
     */
    final public function choose($question, array $options, $default = false, $multi = false, $errorMessage = 'There is no option %s!', $attempts = false) {
        if (is_string($default)) {
            $i = array_search($default, $options);
            if ($i === false) {
                throw new \InvalidArgumentException('Provided default value "'. $default .'" for a choice input cannot be found in the list of options!');
            }

            $default = $options[$i];
        }

        $selected = $this->getDialog()->select($this->output, $question, $options, $default, $attempts, $errorMessage, $multi);

        if ($multi) {
            return array_map(function($i) use ($options) {
                return $options[$i];
            }, $selected);
        }

        return $options[$selected];
    }

    /**
     * Prints an array in a nicely formated ASCII table.
     * 
     * @param  array  $data Data to be printed. All rows should contain the same number of fields.
     * @param  array  $headers [optional] Array of headers for the table. Should have the same amount of rows
     *                         as `$data`. Default: `array()`.
     */
    public function writeTable(array $data, array $headers = array()) {
        $table = $this->helperSet->get('table');
        if (!empty($headers)) {
            $table->setHeaders($headers);
        }
        $table->setRows($data);
        $table->render($this->output);
    }

    /*****************************************
     * SETTERS AND GETTERS
     *****************************************/
    /**
     * Returns the dependency injection service container.
     * 
     * @return ContainerInterface
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
     * Returns a logger that logs into the console.
     * 
     * @return LoggerInterface
     */
    public function getLogger() {
        return new ConsoleLogger($this->getOutput());
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
     * Returns a Dialog helper.
     * 
     * @return DialogHelper
     */
    public function getDialog() {
        return $this->helperSet->get('dialog');
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
