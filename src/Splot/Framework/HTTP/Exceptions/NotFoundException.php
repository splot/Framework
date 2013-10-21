<?php
/**
 * Exception thrown when something has not been found and it should trigger a 404 response.
 * 
 * @package SplotFramework
 * @subpackage HTTP
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\HTTP\Exceptions;

use MD\Foundation\Exceptions\NotFoundException as Base_NotFoundException;

class NotFoundException extends Base_NotFoundException
{

    

}