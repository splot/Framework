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

        $composerFile = dirname(__FILE__) .'/../../../../../../../composer.json';
        $composer = json_decode(file_get_contents($composerFile), true);

        define('SPLOT_SCRIPT_HANDLER', true);

        $appEntryPoint = (isset($composer['extra']) && isset($composer['extra']['splot-console'])) ? $composer['extra']['splot-console'] : 'app/console';
        require_once dirname(__FILE__) .'/../../../../../../../'. $appEntryPoint;

        // the console above should automatically inject the application here
        return self::$_application;
    }

    /**
     * Set the application.
     * 
     * @param AbstractApplication $application Application.
     */
    public static function setApplication(AbstractApplication $application) {
        self::$_application = $application;
    }

}
