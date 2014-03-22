<?php
/**
 * Null error handler.
 * 
 * @package SplotFramework
 * @subpackage ErrorHandlers
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2014, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\ErrorHandlers;

use Whoops\Handler\Handler;

class NullErrorHandler extends Handler
{

    /**
     * Handle the error by doing nothing.
     */
    public function handle() {
        return Handler::DONE;
    }

}