<?php
/**
 * HTTP Response object.
 * 
 * @package SplotFramework
 * @subpackage HTTP
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\HTTP;

use Symfony\Component\HttpFoundation\Response as Base_Response;

class Response extends Base_Response
{

	/**
	 * Alters part of the content.
	 * 
	 * Uses str_replace() on the content.
	 * It's better to alter part of content using this function than by external function to prevent copying of long string variables, for speed.
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

		$this->content = str_replace($part, $replace, $this->content);
	}

}