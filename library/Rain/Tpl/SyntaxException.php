<?php

namespace Rain\Tpl;

/**
 * Exception thrown when syntax error occurs.
 */
class SyntaxException extends Exception {


	/**
	 * Line in template file where error has occured.
	 *
	 * @var int | null
	 */
	protected $templateLine = null;

	/**
	 * Tag which caused an error.
	 *
	 * @var string | null
	 */
	protected $tag = null;


	/**
	 * Handles the line in template file
	 * where error has occured
	 *
	 * @param int | null $line
	 * @return SyntaxException | int | null
	 */
	public function templateLine($line) {
		if ($line === null) {
			return $this->templateLine;
		}

		$this->templateLine = (int) $line;

		return $this;
	}


	/**
	 * Handles the tag which caused an error.
	 *
	 * @param string | null $tag
	 */
	public function tag($tag = null) {
		if ($tag === null) {
			return $this->tag;
		}

		$this->tag = (string) $tag;

		return $this;
	}


}

// -- end
