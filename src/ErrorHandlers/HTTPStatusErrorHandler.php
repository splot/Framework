<?php
/**
 * Sets proper HTTP status code on error.
 *
 * @package SplotFramework
 * @subpackage ErrorHandlers
 * @author Michał Pałys-Dudek <michal@michaldudek.pl>
 *
 * @copyright Copyright (c) 2015, Michał Pałys-Dudek
 * @license MIT
 */
namespace Splot\Framework\ErrorHandlers;

use Whoops\Handler\Handler;

use Splot\Framework\HTTP\Exceptions\HTTPExceptionInterface;

class HTTPStatusErrorHandler extends Handler
{

    /**
     * Set proper HTTP status code when sending error response.
     */
    public function handle()
    {
        $exception = $this->getException();
        if ($exception instanceof HTTPExceptionInterface) {
            $this->getRun()->sendHttpCode($exception->getCode());
        }

        return Handler::DONE;
    }
}
