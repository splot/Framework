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
use Splot\Framework\DependencyInjection\ServiceContainer;

abstract class AbstractScriptHandler
{

    /**
     * Service container.
     * 
     * @var ServiceContainer
     */
    private static $_container;

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
    protected static function bootApplication() {
        if (self::$_application) {
            return self::$_application;
        }

        $_ENV['SPLOT_CLI'] = true;
        include realpath(dirname(__FILE__) .'/../../../../../../../web/index.php');

        // cache the application instance
        self::$_application = $application;

        // link to the container as well
        self::$_container = $application->getContainer();

        return $application;
    }

    /**
     * Returns the service container.
     * 
     * @return ServiceContainer
     */
    public static function getContainer() {
        static::bootApplication();

        return self::$_container;
    }

}