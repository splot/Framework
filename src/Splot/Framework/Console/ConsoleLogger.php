<?php
namespace Splot\Framework\Console;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

use MD\Foundation\Debug\Timer;
use MD\Foundation\Utils\StringUtils;

use Symfony\Component\Console\Output\OutputInterface;

class ConsoleLogger extends AbstractLogger implements LoggerInterface
{

    protected $output;

    public function __construct(OutputInterface $output) {
        $this->output = $output;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     * @return null
     */
    public function log($level, $message, array $context = array()) {
        // add color to all injected variables
        $message = preg_replace_callback('/{([\w\d_\.]+)}/is', function($matches) {
            $var = $matches[1];
            return '<info>{'. $var .'}</info>';
        }, $message);

        $message = StringUtils::interpolate($message, $context);

        $alert = '';
        switch($level) {
            case LogLevel::ALERT:
            case LogLevel::CRITICAL:
            case LogLevel::EMERGENCY:
            case LogLevel::ERROR:
                $alert = '<error> '. $level .' </error> ';
                break;

            case LogLevel::NOTICE:
            case LogLevel::WARNING:
                $alert = '<fg=yellow;options=bold>'. $level .'</fg=yellow;options=bold> ';
                break;
        }

        $this->output->writeln($alert . $message .'     [@mem: '. StringUtils::bytesToString(Timer::getCurrentMemory()) .']');
    }

}