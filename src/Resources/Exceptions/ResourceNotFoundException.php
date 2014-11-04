<?php
/**
 * Exception thrown when a found route is invalid or when trying to register an invalid route.
 * 
 * @package SplotFramework
 * @subpackage Resources
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Resources\Exceptions;

use MD\Foundation\Exceptions\NotFoundException;

class ResourceNotFoundException extends NotFoundException
{

}
