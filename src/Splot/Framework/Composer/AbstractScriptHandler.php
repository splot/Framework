<?php
/**
 * Abstract script handler to use for Composer commands.
 * 
 * @package SplotFramework
 * @subpackage Composer
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Composer;

use Splot\Framework\Framework;
use Splot\Framework\Application\AbstractApplication;

abstract class AbstractScriptHandler
{

    /**
     * Application.
     * 
     * @var AbstractApplication
     */
    private static $_application;

    /**
     * Boots the application so it can be used in all scripts.
     * 
     * @return AbstractApplication
     */
    protected static function boot() {
        if (defined('SPLOT_SCRIPT_HANDLER') && self::$_application) {
            return self::$_application;
        }

        define('SPLOT_SCRIPT_HANDLER', true);
        require_once realpath(dirname(__FILE__) .'/../../../../../../../app/console');

        self::$_application = Framework::getFramework()->getApplication();
        return self::$_application;
    }

}