<?php
/**
 * HTTP Request object.
 * 
 * @package SplotFramework
 * @subpackage Request
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Request;

use Splot\Foundation\Utils\StringUtils;
use Splot\Foundation\Utils\ArrayUtils;

class HttpRequest
{

	/**
	 * Array containing environment variables.
	 * 
	 * @var array
	 */
	protected $_environment = array();

	/**
	 * Array containing GET query variables.
	 * 
	 * @var array
	 */
	protected $_query = array();

	/**
	 * Array containing POST variables.
	 * 
	 * @var array
	 */
	protected $_post = array();

	/**
	 * Array containing request headers.
	 * 
	 * @var array
	 */
	protected $_headers = array();

	/**
	 * Array containing request cookies.
	 * 
	 * @var array
	 */
	protected $_cookies = array();

	/**
	 * Path part of the request URL.
	 * 
	 * @var string
	 */
	protected $_path;
	
	/**
	 * Creates a request object from known PHP globals.
	 * 
	 * @return HttpRequest
	 */
	public static function createFromGlobals() {
		return new static($_SERVER, $_GET, $_POST, \apache_request_headers(), $_COOKIE);
	}
	
	/**
	 * Constructor.
	 * 
	 * @param array $environment Environment configuration and variables.
	 * @param array $query [optional] Variables found in GET query string.
	 * @param array $post [optional] Variables found in POST request.
	 * @param array $headers [optional] Request headers.
	 * @param array $cookies [optional] Request cookies.
	 */
	public function __construct(array $environment, array $query = array(), array $post = array(), array $headers = array(), array $cookies = array()) {
		$this->_environment = self::normalizeRequestArray($environment, true);
		$this->_query = self::normalizeRequestArray($query, false);
		$this->_post = self::normalizeRequestArray($post, false);
		$this->_headers = self::normalizeRequestArray($headers, true);
		$this->_cookies = self::normalizeRequestArray($cookies, false);
	}
	
	/*
	 * HELPER FUNCTIONS
	 */
	/**
	 * Normalizes a request array (handles magic quotes, optionally converts to lower case).
	 * 
	 * @param array $array Array to be normalized.
	 * @param bool $toLowerCase [optional] Should the keys be converted to lowercase? Default: false.
	 * @return array Normalized array.
	 */
	private function normalizeRequestArray(array $array, $toLowerCase = false) {
		if (get_magic_quotes_gpc()) {
			$array = self::undoMagicQuotes($array);
		}

		if ($toLowerCase) {
			$array = array_change_key_case($array, CASE_LOWER);
		}

		return $array;
	}
	
	/**
	 * Strips slashes from the given array if PHP Magic Quotes GPC are turned on.
	 * 
	 * @param array $array Array to clear.
	 * @param bool $topLevel [optional] Magic quotes apply to non-toplevel keys as well, so this parameter needs to know whether the array passed is top level or not. Default: true.
	 * @return array
	 */
	final private static function undoMagicQuotes($array, $topLevel = true) {
		$return = array();
		foreach($array as $key => $value) {
			$key = (!$topLevel) ? stripslashes($key) : $key;
			$return[$key] = (is_array($value)) ? self::_undoMagicQuotes($value, false) : stripslashes($value);
		}
		
		return $return;
	}
	
	/*
	 * GLOBAL GETTERS
	 * Return arrays of data.
	 */
	/**
	 * Converts the whole request object to array.
	 * 
	 * @return array
	 */
	public function toArray() {
		return array(
			'environment' => $this->getEnvironmentVariables(),
			'query' => $this->getQuery(),
			'post' => $this->getPost(),
			'headers' => $this->getHeaders(),
			'cookies' => $this->getCookies()
		);
	}

	/**
	 * Returns environment variables.
	 * 
	 * Optionally returns specific variable.
	 * 
	 * @param string $name [optional] If you want to get specific environment variable then specify it here. Should be lowercased. Default: null.
	 * @return array|mixed Array of all environment variables (where all keys are lowercased) or a specific value.
	 */
	public function getEnvironmentVariables($name = null) {
		if ($name) {
			return $this->_environment[$name];
		}
		return $this->_environment;
	}

	/**
	 * Returns GET query string params.
	 * 
	 * Optionally returns specific param.
	 * 
	 * @param string $name [optional] If you want to get specific query string param then specify it here. Default: null.
	 * @return array|mixed Array of all query string params or a specific value.
	 */
	public function getQuery($name = null) {
		if ($name) {
			return $this->_query[$name];
		}
		return $this->_query;
	}

	/**
	 * Returns POST params.
	 * 
	 * Optionally returns specific param.
	 * 
	 * @param string $name [optional] If you want to get specific POST param then specify it here. Default: null.
	 * @return array|mixed Array of all POST params or a specific value.
	 */
	public function getPost($name = null) {
		if ($name) {
			return $this->_post[$name];
		}
		return $this->_post;
	}

	/**
	 * Returns request headers.
	 * 
	 * Optionally returns specific header.
	 * 
	 * @param string $name [optional] If you want to get specific header then specify it here. Should be lowercased. Default: null.
	 * @return array|mixed Array of all headers (where all keys are lowercased) or a specific value.
	 */
	public function getHeaders($name = null) {
		if ($name) {
			return $this->_headers[$name];
		}
		return $this->_headers;
	}

	/**
	 * Returns request cookies.
	 * 
	 * Optionally returns specific cookie.
	 * 
	 * @param string $name [optional] If you want to get specific cookie then specify it here. Default: null.
	 * @return array|mixed Array of all cookies or a specific value.
	 */
	public function getCookies($name = null) {
		if ($name) {
			return $this->_cookies[$name];
		}
		return $this->_cookies;
	}

	/*
	 * SPECIFIC GETTERS
	 * Return specific values.
	 */
	public function getHeader($name) {
		return $this->getHeaders($name);
	}

	public function getCookie($name) {
		return $this->getCookies($name);
	}

	public function getQueryParam($name) {
		return $this->getQuery($name);
	}

	public function getPostParam($name) {
		return $this->getPost($name);
	}

	public function getQueryString() {
		return $this->getEnvironmentVariables('query_string');
	}

	public function getUrl() {
		return $this->getEnvironmentVariables('request_uri');
	}

	public function getUrlPath() {
		if ($this->_path) {
			return $this->_path;
		}

		$url = $this->getUrl();
		$url = parse_url($url);
		$this->_path = $url['path'];

		return $this->_path;
	}

	public function getMethod() {
		return $this->getEnvironmentVariables('request_method');
	}

	public function getProtocol() {
		return $this->getEnvironmentVariables('server_protocol');
	}

	public function getRequestTime() {
		return $this->getEnvironmentVariables('request_time');
	}

	public function getHttpHost() {
		return $this->getHeaders('host');
	}

	public function getHttpAccept() {
		return $this->getHeaders('accept');
	}

	public function getUserAgent() {
		return $this->getHeaders('user-agent');
	}

	public function getHttpAcceptEncoding() {
		return $this->getHeaders('accept-encoding');
	}

	public function getHttpAcceptLanguage() {
		return $this->getHeaders('accept-language');
	}

	public function getHttpAcceptCharset() {
		return $this->getHeaders('accept-charset');
	}

	public function getServerName() {
		return $this->getEnvironmentVariables('server_name');
	}

	public function getServerAddress() {
		return $this->getEnvironmentVariables('server_addr');
	}

	public function getServerPort() {
		return $this->getEnvironmentVariables('server_post');
	}

	public function getServerAdmin() {
		return $this->getEnvironmentVariables('server_admin');
	}

	public function getClientIp() {
		return $this->getEnvironmentVariables('remote_addr');
	}

	public function getClientPort() {
		return $this->getEnvironmentVariables('remote_port');
	}
	
}