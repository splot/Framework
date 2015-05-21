<?php
/**
 * Interface for a Splot compatible templating engine.
 * 
 * @package SplotFramework
 * @subpackage Templating
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Templating;

interface TemplatingEngineInterface
{

    /**
     * Render a view found under the given name with the given variables to be interpolated.
     * 
     * @param string $view View name.
     * @param array $data [optional] Any additional variables to be interpolated in the view template.
     * @return string
     */
    public function render($view, array $data = array());

}
