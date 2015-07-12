<?php
/**
 * HTTP Request object.
 * 
 * @package SplotFramework
 * @subpackage HTTP
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\HTTP;

use Symfony\Component\HttpFoundation\Request as Base_Request;

class Request extends Base_Request
{

    /**
     * Constructor.
     * 
     * Automatically translates JSON raw post data into post parameters.
     */
    public function __construct(array $query = array(), array $request = array(), array $attributes = array(), array $cookies = array(), array $files = array(), array $server = array(), $content = null) {
        parent::__construct($query, $request, $attributes, $cookies, $files, $server, $content);

        $rawPost = $this->getContent();
        if (!empty($rawPost)) {
            if (stripos($this->headers->get('Content-Type'), 'application/json') === 0) {
                $post = json_decode($rawPost, true);
                if ($post && is_array($post)) {
                    $this->request->add($post);
                }
            }
        }
    }
}
