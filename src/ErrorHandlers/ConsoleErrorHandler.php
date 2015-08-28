<?php
/**
 * Handles errors in CLI.
 *
 * @package SplotFramework
 * @subpackage ErrorHandlers
 * @author Michał Pałys-Dudek <michal@michaldudek.pl>
 *
 * @copyright Copyright (c) 2015, Michał Pałys-Dudek
 * @license MIT
 */
namespace Splot\Framework\ErrorHandlers;

use Exception;

use MD\Foundation\Debug\Debugger;
use MD\Foundation\Utils\StringUtils;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

use Whoops\Handler\Handler;

/**
 * @codeCoverageIgnore
 */
class ConsoleErrorHandler extends Handler
{

    const LINE_WIDTH = 120;

    /**
     * Output.
     *
     * @var ConsoleOutputInterface
     */
    protected $output;

    /**
     * Filesystem service.
     *
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * Should exit with a code?
     *
     * @var integer|null
     */
    protected $exit = null;

    /**
     * Current working dir.
     *
     * @var string
     */
    protected $cwd;

    /**
     * Constructor.
     *
     * @param ConsoleOutputInterface $output      Output.
     * @param Filesystem             $filesystem  Filesystem service.
     * @param integer|null           $exit        Should exit with a code? If `null` then
     *                                            won't exit, otherwise will exit with
     *                                            specified code.
     */
    public function __construct(OutputInterface $output, Filesystem $filesystem, $exit = null)
    {
        $this->output = $output;
        $this->filesystem = $filesystem;
        $this->exit = $exit;
        $this->cwd = getcwd();
    }

    /**
     * Handle error if it's in console environment.
     *
     * @return integer
     */
    public function handle()
    {
        if (!Debugger::isCli()) {
            return Handler::DONE;
        }

        $this->printException($this->getException());

        if ($this->exit !== null) {
            exit(intval($this->exit));
        }

        return Handler::DONE;
    }

    /**
     * Prints details about an exeption.
     *
     * @param  Exception $exception The exception to be printed out.
     */
    private function printException(Exception $exception)
    {
        // add top padding
        $this->output->writeln('');
        $this->output->writeln('');

        $this->printExceptionHeader($exception);

        $this->output->writeln('');
        $this->output->writeln(sprintf(
            'in <comment>%s</comment> on line <comment>%d</comment>',
            $this->filesystem->makePathRelative($exception->getFile(), $this->cwd),
            $exception->getLine()
        ));

        $this->output->writeln('');

        $this->printExceptionTrace($exception->getTrace());

        // add bottom padding
        $this->output->writeln('');
        $this->output->writeln('');

        $previous = $exception->getPrevious();
        if ($previous) {
            $this->output->writeln('<info>Caused by:</info>');
            $this->printException($previous);
        }
    }

    /**
     * Prints the "header" block with general info about the exception (type and message).
     *
     * @param  Exception $exception The exception.
     */
    private function printExceptionHeader(Exception $exception)
    {
        $lines = array(
            '',
            Debugger::getClass($exception) .' :',
        );
        $lines = array_merge($lines, explode("\n", wordwrap($exception->getMessage(), self::LINE_WIDTH)));
        $lines[] = '';

        $maxWidth = 0;
        foreach ($lines as $line) {
            $width = strlen($line);
            $maxWidth = $width > $maxWidth ? $width : $maxWidth;
        }

        foreach ($lines as $line) {
            $this->output->writeln(sprintf(
                '    <error> %s </error>',
                str_pad($line, $maxWidth)
            ));
        }
    }

    /**
     * Prints trace / call stack / backtrace of an exception.
     *
     * @param  array  $trace The back trace to print out.
     */
    private function printExceptionTrace(array $trace)
    {
        $trace = array_reverse($trace);
        
        $this->output->writeln('Call Stack:');

        foreach ($trace as $i => $call) {
            $this->output->writeln(sprintf(
                '    %d. <info>%s%s%s(</info>%s<info>)</info> in <comment>%s</comment>:%d',
                $i + 1,
                $call['class'],
                $call['type'],
                $call['function'],
                $this->parseCallArguments($call['args']),
                $this->filesystem->makePathRelative($call['file'], $this->cwd),
                $call['line']
            ));
        }
    }

    /**
     * Parses arguments of a method call.
     *
     * @param  array  $arguments Arguments.
     *
     * @return array
     */
    private function parseCallArguments(array $arguments)
    {
        $args = array();

        foreach ($arguments as $argument) {
            if (is_object($argument)) {
                $args[] = '<comment>(object)</comment> '. StringUtils::truncate(
                    Debugger::getClass($argument),
                    32,
                    '...',
                    StringUtils::TRUNCATE_MIDDLE
                );
            } elseif (is_array($argument)) {
                $args[] = '<comment>(array)</comment> '. StringUtils::truncate(
                    json_encode($argument),
                    32,
                    '...',
                    StringUtils::TRUNCATE_MIDDLE
                );
            } elseif (is_string($argument)) {
                $args[] = sprintf("'%s'", StringUtils::truncate(
                    $argument,
                    32,
                    '...',
                    StringUtils::TRUNCATE_MIDDLE
                ));
            } else {
                $args[] = $argument;
            }
        }

        return implode(', ', $args);
    }
}
