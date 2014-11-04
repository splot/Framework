<?php
/**
 * Process is a service that allows to easily execute CLI processes.
 * 
 * @package SplotFramework
 * @subpackage Process
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Process;

use Symfony\Component\Process\Process as SymfonyProcess;

use Splot\Framework\Process\Exceptions\ProcessRuntimeException;

class Process
{

    /**
     * Run a command and wait for its output.
     * 
     * @param string $cmd Command to be executed.
     * @param callable $callback [optional] Callback function that will be called periodically during command's execution
     *                            and will take two arguments: 1st is a string buffer output and 2nd is bool error.
     *                            You can return (bool) false from the callback to stop the running command.
     * @return string Full command output.
     */
    public function run($cmd, $callback = null) {
        $process = new SymfonyProcess($cmd);

        // if callback is defined then wrap it in a helper function and call pass it to the ->run() command
        $callbackWrapper = is_callable($callback) ? function($type, $buffer) use ($callback, $process) {
            $error = $type === SymfonyProcess::ERR ? true : false;
            // call the callback
            $continue = call_user_func_array($callback, array($buffer, $error));

            // if callback returned false then stop the process
            if ($continue === false) {
                $process->stop(3, SIGINT);
            }
        } : null;

        $process->run($callbackWrapper);

        if (!$process->isSuccessful()) {
            throw new ProcessRuntimeException($process->getErrorOutput());
        }

        // return full output
        return $process->getOutput();
    }

    /**
     * Fire and forget a command. It will be executed asynchronously, but you can get its output via the $callback.
     * 
     * @param string $cmd Command to be fired.
     * @param callable $callback [optional] Callback function that will be called periodically during command's execution
     *                            and will take two arguments: 1st is a string buffer output and 2nd is bool error.
     *                            You can return (bool) false from the callback to stop the running command.
     */
    public function fire($cmd, $callback = null) {
        $process = new SymfonyProcess($cmd);
        $process->start();

        // if callback is defined then call it periodically
        if (is_callable($callback)) {
            while($process->isRunning()) {
                // call the callback
                $continue = call_user_func_array($callback, array($process->getIncrementalOutput()));

                // if callback returned false then stop the process
                if ($continue === false) {
                    $process->stop(3, SIGINT);
                }
            }
        }
    }

}
