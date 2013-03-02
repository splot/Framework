<?php
/**
 * HTTP Response object.
 * 
 * @package SplotFramework
 * @subpackage Response
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Foundation\Response;

use Splot\Foundation\Debug\Debugger;

class HttpResponse
{

	/**
	 * Response body.
	 * 
	 * @var string
	 */
	private $_body;

	/**
	 * HTTP Response status code.
	 * 
	 * @var int
	 */
	private $_statusCode = 200;

	/**
	 * Array of response headers.
	 * 
	 * @var array
	 */
	private $_headers = array();

	/**
     * Status codes translation table.
     *
     * The list of codes is complete according to the
     * {@link http://www.iana.org/assignments/http-status-codes/ Hypertext Transfer Protocol (HTTP) Status Code Registry}
     * (last updated 2012-02-13).
     *
     * Unless otherwise noted, the status code is defined in RFC2616.
     * 
     * Taken from Symfony2 HTTP Foundation Component.
     *
     * @var array
     */
    public static $_statusCodes = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',            // RFC2518
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',          // RFC4918
        208 => 'Already Reported',      // RFC5842
        226 => 'IM Used',               // RFC3229
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Reserved',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',    // RFC-reschke-http-status-308-07
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',                                               // RFC2324
        422 => 'Unprocessable Entity',                                        // RFC4918
        423 => 'Locked',                                                      // RFC4918
        424 => 'Failed Dependency',                                           // RFC4918
        425 => 'Reserved for WebDAV advanced collections expired proposal',   // RFC2817
        426 => 'Upgrade Required',                                            // RFC2817
        428 => 'Precondition Required',                                       // RFC6585
        429 => 'Too Many Requests',                                           // RFC6585
        431 => 'Request Header Fields Too Large',                             // RFC6585
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates (Experimental)',                      // RFC2295
        507 => 'Insufficient Storage',                                        // RFC4918
        508 => 'Loop Detected',                                               // RFC5842
        510 => 'Not Extended',                                                // RFC2774
        511 => 'Network Authentication Required',                             // RFC6585
    );

	/**
	 * @param string $body Body of the response. Must be a string or whatever that can be cast as string, e.g. object implementing __toString() method.
	 * @param int $statusCode [optional] HTTP response code. Default: 200.
	 * @param array $headers [optional] Array of optional headers.
	 * 
	 * @throws \InvalidArgumentException When the given body cannot be cast as string.
	 * @throws \InvalidArgumentException When the given status code is invalid.
	 */
	public function __construct($body = '', $statusCode = 200, array $headers = array()) {
		$this->setBody($body);
		$this->setStatusCode($statusCode);

		// add each header separately so we don't overwrite any default ones.
		foreach($headers as $header => $value) {
			$this->setHeader($header, $value);
		}
	}

	/**
	 * Sends the response to the client.
	 */
	public function send() {
		echo $this->getBody();
	}

	/**
	 * Alters part of the body.
	 * 
	 * Uses str_replace() on the body.
	 * It's better to alter part of content using this function then by external function to prevent copying of long string variables, for speed.
	 * 
	 * @param string $part Part that should be replaced.
	 * @param string $replace Value to be replaced with.
	 * 
	 * @throws \InvalidArgumentException When the given part to be replaced is not a string or is empty.
	 */
	public function alterPart($part, $replace) {
		if (!is_string($part) || empty($part)) {
			throw new \InvalidArgumentException('The given part "'. $part .'" to be replaced is either not a string or empty.');
		}

		$this->_body = str_replace($part, $replace, $this->_body);
	}

	/*
	 * SETTERS AND GETTERS
	 */
	/**
	 * Returns the response body.
	 * 
	 * @return string
	 */
	public function getBody() {
		return $this->_body;
	}

	/**
	 * Sets the response body.
	 * 
	 * @param string $body Body of the response. Must be a string or whatever that can be cast as string, e.g. object implementing __toString() method.
	 * 
	 * @throws \InvalidArgumentException When the given body cannot be cast as string.
	 */
	public function setBody($body) {
		$body = (string)$body;
		if (!is_string($body)) {
			throw new \InvalidArgumentException('Response body must be a string or object that can be cast as string, "'. Debugger::getClass($body) . '" given.');
		}

		$this->_body = $body;
	}

	/**
	 * Returns the response body.
	 * 
	 * @return string
	 */
	public function getContent() {
		return $this->getBody();
	}

	/**
	 * Sets the response body.
	 * 
	 * @param string $body Body of the response. Must be a string or whatever that can be cast as string, e.g. object implementing __toString() method.
	 * 
	 * @throws \InvalidArgumentException When the given body cannot be cast as string.
	 */
	public function setContent($content) {
		$this->setBody($content);
	}

	/**
	 * Returns the current HTTP response status code.
	 * 
	 * @return int
	 */
	public function getStatusCode() {
		return $this->_statusCode;
	}

	/**
	 * Sets the HTTP response status code.
	 * 
	 * @param int $statusCode
	 * 
	 * @throws \InvalidArgumentException When the given status code is invalid.
	 */
	public function setStatusCode($statusCode) {
		$statusCode = (int)$statusCode;
		if (!is_int($statusCode)) {
			throw new \InvalidArgumentException('HTTP Response Status Code must be an int, "'. $statusCode .'" given.');
		}

		if (!isset(self::$_statusCodes[$statusCode])) {
			throw new \InvalidArgumentException('Not a valid HTTP Response Status Code given - "'. $statusCode .'".');
		}

		$this->_statusCode = $statusCode;
	}

	/**
	 * Sets a header.
	 * 
	 * @param string $header Name of the header to set.
	 * @param string $value [optional] Value of the header to set.
	 */
	public function setHeader($header, $value = '') {
		$this->_headers[$header] = $value;
	}

	/**
	 * Returns the given header value.
	 * 
	 * @param string $header Header name.
	 * @return string
	 */
	public function getHeader($header) {
		return $this->_headers[$header];
	}

	/**
	 * Returns all set headers.
	 * 
	 * @param array
	 */
	public function getHeaders() {
		return $this->_headers;
	}

	/**
	 * Sets all headers.
	 * 
	 * @param array $headers Array of headers to be set.
	 */
	public function setHeaders(array $headers) {
		$this->_headers = $headers;
	}

}