<?php
/**
 * HTTP Not Found Response object.
 * 
 * @package SplotFramework
 * @subpackage HTTP
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\HTTP;

use MD\Foundation\Exceptions\InvalidArgumentException;

use Splot\Framework\HTTP\Response;

class NotFoundResponse extends Response
{

    /**
     * Constructor.
     * 
     * @param string $content Content.
     * @param int $status [optional] Response status code. Default: 404.
     * @param array $headers [optional] Headers array.
     */
    public function __construct($content = '', $status = 404, $headers = array()) {
        if (!is_array($headers)) {
            throw new InvalidArgumentException('array', $headers, 3);
        }

        parent::__construct($content, $status, $headers);
    }

    /**
     * Creates the RedirectResponse object.
     * 
     * @param string $content URL to redirect to.
     * @param int $code [optional] Response code. Default: 404.
     * @param array $headers [optional] Headers array.
     * @return RedirectResponse
     */
    public static function create($content = '', $status = 404, $headers = array()) {
        return new static($content, $status, $headers);
    }

}