<?php
/**
 * Exception thrown when trying to generate a route with missing parameters.
 * 
 * @package SplotFramework
 * @subpackage Routes
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Routes\Exceptions;

use Splot\Framework\HTTP\Exceptions\NotFoundException;

class RouteParameterNotFoundException extends NotFoundException
{



}