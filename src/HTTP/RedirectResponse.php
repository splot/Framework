<?php
/**
 * HTTP Redirest Response object.
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

class RedirectResponse extends Response
{

    /**
     * URL to redirect to.
     * 
     * @var string
     */
    protected $url;

    /**
     * Constructor.
     * 
     * @param string $url URL to redirect to.
     * @param int $status [optional] Response status code. Default: 302.
     * @param array $headers [optional] Headers array.
     */
    public function __construct($url = '', $status = 302, $headers = array()) {
        if (empty($url)) {
            throw new InvalidArgumentException('non-empty string', $url);
        }

        if (!is_array($headers)) {
            throw new InvalidArgumentException('array', $headers, 3);
        }

        parent::__construct('', $status, array(
            'Location' => $url
        ));

        $this->url = $url;
    }

    /**
     * Creates the RedirectResponse object.
     * 
     * @param string $url URL to redirect to.
     * @param int $code [optional] Response code. Default: 302.
     * @param array $headers [optional] Headers array.
     * @return RedirectResponse
     */
    public static function create($url = '', $status = 302, $headers = array()) {
        return new static($url, $status, $headers);
    }

    /**
     * Returns the URL to redirect to.
     * 
     * @return string
     */
    public function getUrl() {
        return $this->url;
    }

}
