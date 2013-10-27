<?php
/**
 * HTTP JSON Response object.
 * 
 * @package SplotFramework
 * @subpackage HTTP
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\HTTP;

use Splot\Framework\HTTP\Response;

class JsonResponse extends Response
{

    protected $jsonData = array();

    /**
     * Constructor.
     * 
     * @param array $data [optional] Array of data to be sent in JSON.
     * @param int $status [optional] HTTP response status code. Default: 200.
     * @param array $headers [optional] Headers array.
     */
    public function __construct(array $data = array(), $status = 200, array $headers = array()) {
        $this->jsonData = $data;
        $headers = array_merge($headers, array(
            'Content-Type' => 'application/json'
        ));

        parent::__construct('', $status, $headers);
    }

    /**
     * Creates JsonResponse object.
     * 
     * @param array $data [optional] Array of data to be sent in JSON.
     * @param int $status [optional] HTTP response status code. Default: 200.
     * @param array $headers [optional] Headers array.
     * @return JsonResponse
     */
    public static function create(array $data = array(), $status = 200, array $headers = array()) {
        return new static($data, $status, $headers);
    }

    /**
     * Sets a value for the given key in the JSON data.
     * 
     * @param string $key Key of the data.
     * @param mixed $val Value to be set.
     */
    public function set($key, $val) {
        $this->jsonData[$key] = $val;
    }

    /**
     * Returns the data under the given key.
     * 
     * @param string $key Data key.
     * @return mixed
     */
    public function get($key) {
        return $this->jsonData[$key];
    }

    /**
     * Sends content for the current web response.
     *
     * @return JsonResponse
     */
    public function sendContent() {
        $this->content = $this->getContent();
        return parent::sendContent();
    }

    /**
     * Gets the current response content (JSON encoded).
     *
     * @return string
     */
    public function getContent() {
        return json_encode($this->jsonData);
    }

}