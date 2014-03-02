<?php
/**
 * Clog Bridge to SplotFramework.
 * 
 * @package SplotFramework
 * @subpackage Log
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Log;

use MD\Clog\Clog as BaseClog;

use Splot\Framework\Log\LoggerProviderInterface;

class Clog extends BaseClog implements LoggerProviderInterface
{

    /**
     * Provide an instance of \Psr\Log\LoggerInterface.
     * 
     * @param  string $name Name of the logger.
     * @return \Psr\Log\LoggerInterface
     */
    public function provide($name) {
        return $this->provideLogger($name);
    }

}