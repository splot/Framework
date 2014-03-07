<?php
/**
 * Exception thrown when access is denied to a resource and it should trigger a 403 response.
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

class ForbiddenException extends Exception implements HTTPExceptionInterface
{

    protected $code = 403;

}