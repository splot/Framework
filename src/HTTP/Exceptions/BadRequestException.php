<?php
/**
 * Exception thrown when request is bad (e.g. invalid data sent).
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

class BadRequestException extends Exception implements HTTPExceptionInterface
{

    protected $code = 400;

}
