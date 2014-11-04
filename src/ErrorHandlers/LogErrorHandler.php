<?php
/**
 * Log error handler.
 * 
 * @package SplotFramework
 * @subpackage ErrorHandlers
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2014, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\ErrorHandlers;

use Psr\Log\LoggerInterface;

use Whoops\Handler\Handler;

class LogErrorHandler extends Handler
{

    /**
     * Logger.
     * 
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor.
     * 
     * @param LoggerInterface $logger Logger to which the error should be logged.
     */
    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
    }

    /**
     * Handle the error by pushing it to logger.
     */
    public function handle() {
        $e = $this->getException();

        $this->logger->critical('{class}: {message} in file {file} on line {line}.'. NL . 'Trace: ' . NL .'{trace}', array(
            'class' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ));

        return Handler::DONE;
    }

}
