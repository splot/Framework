<?php
/**
 * Pretty page error handler.
 * 
 * @package SplotFramework
 * @subpackage ErrorHandlers
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2014, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\ErrorHandlers;

use Whoops\Handler\Handler;
use Whoops\Handler\PrettyPageHandler;

class PrettyPageErrorHandler extends PrettyPageHandler
{

    /**
     * Is the handler enabled?
     * 
     * @var boolean
     */
    protected $enabled = true;

    /**
     * Constructor.
     * 
     * @param boolean $enabled [optional] Is the handler enabled? Default: `true`.
     */
    public function __construct($enabled = true) {
        $this->enabled = $enabled;
        parent::__construct();
    }

    /**
     * Handles an error, but checks if its enabled first. If not enabled then does nothing,
     * but if enabled it will call the parent Whoops's pretty page error handler.
     * 
     * @return int|null
     */
    public function handle() {
        if (!$this->enabled) {
            return Handler::DONE;
        }

        return $this->parentHandle();
    }

    /**
     * Calls the `::handle()` method on the parent.
     *
     * Enables testing of the parent call.
     * 
     * @return int|null
     *
     * @codeCoverageIgnore
     */
    protected function parentHandle() {
        return parent::handle();
    }

}
