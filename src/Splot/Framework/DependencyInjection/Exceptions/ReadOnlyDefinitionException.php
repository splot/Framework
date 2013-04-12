<?php
/**
 * Exception thrown when trying to overwrite a service or a parameter that has been defined as read-only.
 * 
 * @package SplotFramework
 * @subpackage DependencyInjection
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\DependencyInjection\Exceptions;

use Splot\Foundation\Exceptions\ReadOnlyException;

class ReadOnlyDefinitionException extends ReadOnlyException
{



}