<?php
/**
 * Console class responsible for handling command line interface commands.
 * 
 * @package SplotFramework
 * @subpackage Console
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Console;

use MD\Foundation\Debug\Debugger;
use MD\Foundation\Exceptions\NotFoundException;

use Psr\Log\LoggerInterface;

use Splot\Framework\Application\AbstractApplication;
use Splot\Framework\Console\Exceptions\InvalidCommandException;
use Splot\Framework\Console\AbstractCommand;
use Splot\Framework\Modules\AbstractModule;

use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Command\Command as ConsoleCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class Console
{

    /**
     * Application.
     * 
     * @var AbstractApplication
     */
    protected $application;

    /**
     * Logger.
     * 
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Console application (from Symfony Components).
     * 
     * @var ConsoleApplication
     */
    protected $consoleApplication;

    /**
     * Container for all registered commands.
     * 
     * @var array
     */
    protected $commands = array();

    /**
     * Constructor.
     * 
     * @param AbstractApplication $pplication Application to which this console belongs.
     */
    public function __construct(AbstractApplication $application, LoggerInterface $logger = null) {
        $this->application = $application;
        $this->logger = $logger;

        // initialize Symfony Console component
        $this->consoleApplication = new ConsoleApplication($application->getName() .' (Splot Console)', $application->getVersion());

        // now gather all commands from the application
        foreach($this->application->getModules() as $module) {
            $this->readModuleCommands($module);       
        }
    }

    /**
     * Runs the console.
     */
    public function run() {
        $this->consoleApplication->run();
    }

    /**
     * Calls a command with the given name.
     * 
     * @param string $name Command name.
     * @param string $argv [optional] String in argv format to call the command with.
     * @param OutputInterface $output [optional] Output for the command. If ommitted, a console output will be used.
     */
    public function call($name, $argv = '', OutputInterface $output = null) {
        if (!isset($this->commands[$name])) {
            throw new NotFoundException('The command "'. $name .'" was not found in the list of registered commands.');
        }

        $command = $this->consoleApplication->find($name);
        
        $input = new StringInput($name .' '. $argv);
        $output = ($output === null) ? new ConsoleOutput() : $output;

        $command->run($input, $output);
    }

    /**
     * Registers a command.
     * 
     * @param string $name Name of the command.
     * @param string $commandClass Class name of the command.
     */
    public function addCommand($name, $commandClass) {
        // must extend AbstractCommand
        $abstractCommandClass = AbstractCommand::__class();
        if (!Debugger::isExtending($commandClass, $abstractCommandClass)) {
            throw new InvalidCommandException('Command "'. $commandClass .'" must extend "'. $abstractCommandClass .'".');
        }

        // name cannot be empty
        $name = trim($name);
        if (empty($name)) {
            throw new InvalidArgumentException('Command name cannot be empty for "'. $commandClass .'"!');
        }

        // configure the command
        $console = $this;
        $consoleCommand = new ConsoleCommand($name);
        $consoleCommand->setDescription($commandClass::getDescription());
        $consoleCommand->setHelp($commandClass::getHelp());
        $consoleCommand->setCode(function(InputInterface $input, OutputInterface $output) use ($console, $name) {
            $console->exec($name, $input, $output);
        });

        // read some meta info about the command
        $commandReflection = new \ReflectionClass($commandClass);
        $arguments = array();
        $argumentsDescriptions = $commandClass::getArguments();
        try {
            $methodReflection = $commandReflection->getMethod('execute');

            if (!$methodReflection->isPublic() || $methodReflection->isStatic()) {
                throw new InvalidCommandException('The "execute()" method for ommand "'. $commandClass .'" must be public and non-static.');
            }

            // get the execute() method's arguments so we can translate them to CLI arguments
            $parametersReflection = $methodReflection->getParameters();
            foreach($parametersReflection as $param) {
                $optional = $param->isDefaultValueAvailable();
                $paramName = $param->getName();

                $arguments[] = array(
                    'name' => $paramName,
                    'optional' => $optional,
                    'default' => ($optional) ? $param->getDefaultValue() : null,
                    'description' => isset($argumentsDescriptions[$paramName]) ? $argumentsDescriptions[$paramName] : ''
                );
            }

        } catch (\ReflectionException $e) {
            throw new InvalidCommandException('Command "'. $commandClass .'" must implement public function "execute()"!');
        }

        foreach($arguments as $argument) {
            $consoleCommand->addArgument(
                $argument['name'],
                $argument['optional'] ? InputArgument::OPTIONAL : InputArgument::REQUIRED,
                $argument['description'],
                $argument['default']
            );
        }

        // also register command's options
        $options = $commandClass::getOptions();
        foreach($options as $option => $optionInfo) {
            $value = (isset($optionInfo['required']) && $optionInfo['required'])
                ? InputOption::VALUE_REQUIRED
                : (!isset($optionInfo['default']) || empty($optionInfo['default']) || $optionInfo['default'] === null
                    ? InputOption::VALUE_NONE
                    : InputOption::VALUE_OPTIONAL
                );

            $consoleCommand->addOption(
                $option,
                isset($optionInfo['shortcut']) ? $optionInfo['shortcut'] : null,
                $value,
                isset($optionInfo['description']) ? $optionInfo['description'] : '',
                ($value === InputOption::VALUE_REQUIRED || $value === InputOption::VALUE_NONE)
                    ? null 
                    : (isset($optionInfo['default']) ? $optionInfo['default'] : null)
            );
        }

        // register the command
        $this->commands[$name] = array(
            'name' => $name,
            'class' => $commandClass,
            'command' => $consoleCommand,
            'arguments' => $arguments,
            'options' => $options
        );

        $this->consoleApplication->add($consoleCommand);
    }

    /*****************************************
     * HELPERS
     *****************************************/
    /**
     * Executes a command by parsing all the input options and arguments and passing them to the command.
     * 
     * @param string $name Name of the command to call.
     * @param InputInterface $input Input.
     * @param OutputInterface $ouput Output.
     */
    public function exec($name, InputInterface $input, OutputInterface $output) {
        if (!isset($this->commands[$name])) {
            throw new NotFoundException('The command "'. $name .'" was not found in the list of registered commands.');
        }

        $commandInfo = $this->commands[$name];
        $commandClass = $commandInfo['class'];
        $consoleCommand = $commandInfo['command'];

        // instantiate the command with appropriate DI
        $command = new $commandClass($this->application->getContainer(), $input, $output, $consoleCommand->getHelperSet());

        // parse options
        $command->setOptions($input->getOptions());

        // parse arguments
        $arguments = array();
        foreach($input->getArguments() as $key => $argument) {
            // if key is command then ignore it
            if ($key === 'command') {
                continue;
            }

            $arguments[] = $argument;
        }

        // call!
        call_user_func_array(array($command, 'execute'), $arguments);
    }

    /**
     * Searches the given module for any commands that could be automatically registered.
     * 
     * @param AbstractModule $module
     */
    protected function readModuleCommands(AbstractModule $module) {
        $name = $module->getName();
        $commandsDir = $module->getModuleDir() .'Commands';
        if (!is_dir($commandsDir)) {
            return;
        }

        $moduleNamespace = $module->getNamespace() . NS .'Commands'. NS;
        $console = $this;

        // register a closure so we can recursively scan the routes directory
        $scan = function($dir, $namespace, $self) use ($name, $moduleNamespace, $module, $console) {
            $namespace = ($namespace) ? trim($namespace, NS) . NS : '';
            
            $files = scandir($dir);
            foreach($files as $file) {
                // ignore . and ..
                if (in_array($file, array('.', '..'))) {
                    continue;
                }

                // if directory then go recursively
                if (is_dir($dir . DS . $file)) {
                    $self($dir . DS . $file, $namespace . $file, $self);
                    continue;
                }

                $file = explode('.', $file);
                $rawClass = $file[0];
                $class = $moduleNamespace . $namespace . $rawClass;

                // class_exists autoloads a file
                if (class_exists($class)) {
                    $name = $class::getName();
                    $name = trim($name);
                    if (empty($name)) {
                        throw new InvalidCommandException('Command "'. $class .'" has to have a name. Please set the static property $name.');
                    }

                    $name = ($module->getCommandNamespace() ? $module->getCommandNamespace() .':' : '') . $name;
                    $console->addCommand($name, $class);
                }
            }
        };

        // scan the module
        $scan($commandsDir, '', $scan);
    }

}