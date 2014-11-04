<?php
/**
 * Logger Provider Interface.
 * 
 * @package SplotFramework
 * @subpackage Log
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Log;

interface LoggerProviderInterface
{

    /**
     * Provide an instance of \Psr\Log\LoggerInterface.
     * 
     * @param  string $name Name of the logger.
     * @return \Psr\Log\LoggerInterface
     */
    public function provide($name);

}
