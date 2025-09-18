<?php

namespace Rain\Tpl;

use Throwable;

/**
 * Basic Rain tpl exception.
 */
class Exception extends \Exception {


	/**
	 * Path of template file with error.
	 */
	protected $templateFile = '';


	/**
	 * Handles path of template file with error.
	 *
	 * @param string | null $templateFile
	 * @return Throwable
	 */
	public function templateFile($templateFile) {
		if ($templateFile === null) {
			return $this->templateFile;
		}

		$this->templateFile = (string) $templateFile;

		return $this;
	}


}

// -- end
