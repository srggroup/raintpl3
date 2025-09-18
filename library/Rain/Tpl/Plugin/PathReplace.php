<?php

//@phpcs:ignoreFile SR1.Files.SideEffects

namespace Rain\Tpl\Plugin;

use ArrayAccess;
use ArrayObject;
use Rain\Tpl\Plugin;

require_once __DIR__ . '/../Plugin.php';

class PathReplace extends Plugin {


	protected $hooks = ['afterParse'];

	private $tags = ['a', 'img', 'link', 'script', 'form', 'input', 'object', 'embed'];


	/**
	 * replace the path of image src, link href and a href.
	 * url => template_dir/url
	 * url# => url
	 * http://url => http://url
	 */
	public function afterParse(ArrayObject $context) {

		// set variables
		$html = $context->code;
		$tags = $this->tags;
		$basecode = "<?php echo static::\$conf['base_url']; ?>";


		// get the template base directory
		$template_directory = $basecode . $context->conf['tpl_dir'] . $context->template_basedir;

		// reduce the path
		$path = str_replace("://", "@not_replace@", $template_directory);
		$path = preg_replace("#(/+)#", "/", $path);
		$path = preg_replace("#(/\./+)#", "/", $path);
		$path = str_replace("@not_replace@", "://", $path);

		while (preg_match('#\.\./#', $path)) {
			$path = preg_replace('#\w+/\.\./#', '', $path);
		}


		$exp = $sub = [];

		if (in_array("img", $tags)) {
			$exp = [
				'/<img(.*?)src=(?:")(http|https)\:\/\/([^"]+?)(?:")/i', '/<img(.*?)src=(?:")([^"]+?)#(?:")/i', '/<img(.*?)src="(.*?)"/', '/<img(.*?)src=(?:\@)([^"]+?)(?:\@)/i',
			];
			$sub = ['<img$1src=@$2://$3@', '<img$1src=@$2@', '<img$1src="' . $path . '$2"', '<img$1src="$2"'];
		}

		if (in_array("script", $tags)) {
			$exp = array_merge($exp, [
				'/<script(.*?)src=(?:")(http|https)\:\/\/([^"]+?)(?:")/i', '/<script(.*?)src=(?:")([^"]+?)#(?:")/i', '/<script(.*?)src="(.*?)"/',
				'/<script(.*?)src=(?:\@)([^"]+?)(?:\@)/i',
			]);
			$sub = array_merge($sub, ['<script$1src=@$2://$3@', '<script$1src=@$2@', '<script$1src="' . $path . '$2"', '<script$1src="$2"']);
		}

		if (in_array("link", $tags)) {
			$exp = array_merge($exp, [
				'/<link(.*?)href=(?:")(http|https)\:\/\/([^"]+?)(?:")/i', '/<link(.*?)href=(?:")([^"]+?)#(?:")/i', '/<link(.*?)href="(.*?)"/',
				'/<link(.*?)href=(?:\@)([^"]+?)(?:\@)/i',
			]);
			$sub = array_merge($sub, ['<link$1href=@$2://$3@', '<link$1href=@$2@', '<link$1href="' . $path . '$2"', '<link$1href="$2"']);
		}

		if (in_array("a", $tags)) {
			$exp = array_merge($exp, [
				'/<a(.*?)href=(?:")(http:\/\/|https:\/\/|javascript:|mailto:|\/|{)([^"]+?)(?:")/i', '/<a(.*?)href="(.*?)"/', '/<a(.*?)href=(?:\@)([^"]+?)(?:\@)/i',
			]);
			$sub = array_merge($sub, ['<a$1href=@$2$3@', '<a$1href="' . $basecode . '$2"', '<a$1href="$2"']);
		}

		if (in_array("input", $tags)) {
			$exp = array_merge($exp, [
				'/<input(.*?)src=(?:")(http|https)\:\/\/([^"]+?)(?:")/i', '/<input(.*?)src=(?:")([^"]+?)#(?:")/i', '/<input(.*?)src="(.*?)"/',
				'/<input(.*?)src=(?:\@)([^"]+?)(?:\@)/i',
			]);
			$sub = array_merge($sub, ['<input$1src=@$2://$3@', '<input$1src=@$2@', '<input$1src="' . $path . '$2"', '<input$1src="$2"']);
		}

		if (in_array("object", $tags)) {
			$exp = array_merge($exp, [
				'/<object(.*?)data=(?:")(http|https)\:\/\/([^"]+?)(?:")/i', '/<object(.*?)data=(?:")([^"]+?)#(?:")/i', '/<object(.*?)data="(.*?)"/',
				'/<object(.*?)data=(?:\@)([^"]+?)(?:\@)/i',
			]);
			$sub = array_merge($sub, ['<object$1data=@$2://$3@', '<object$1data=@$2@', '<object$1data="' . $path . '$2"', '<object$1data="$2"']);
		}

		if (in_array("embed", $tags)) {
			$exp = array_merge($exp, [
				'/<embed(.*?)src=(?:")(http|https)\:\/\/([^"]+?)(?:")/i', '/<embed(.*?)src=(?:")([^"]+?)#(?:")/i', '/<embed(.*?)src="(.*?)"/',
				'/<embed(.*?)src=(?:\@)([^"]+?)(?:\@)/i',
			]);
			$sub = array_merge($sub, ['<embed$1src=@$2://$3@', '<embed$1src=@$2@', '<embed$1src="' . $path . '$2"', '<embed$1src="$2"']);
		}

		if (in_array("form", $tags)) {
			$exp = array_merge($exp, ['/<form(.*?)action="(.*?)"/']);
			$sub = array_merge($sub, ['<form$1action="' . $basecode . '$2"']);
		}

		$context->code = preg_replace($exp, $sub, $html);
	}


	public function setTags($tags) {
		$this->tags = (array) $tags;

		return $this;
	}


}
