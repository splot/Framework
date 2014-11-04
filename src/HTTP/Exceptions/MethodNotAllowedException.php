<?php
/**
 * Exception thrown when request method is not allowed.
 * 
 * @package SplotFramework
 * @subpackage HTTP
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\HTTP\Exceptions;

use MD\Foundation\Exceptions\Exception;

use Splot\Framework\HTTP\Exceptions\HTTPExceptionInterface;

class MethodNotAllowedException extends Exception implements HTTPExceptionInterface
{

    protected $code = 405;

}
