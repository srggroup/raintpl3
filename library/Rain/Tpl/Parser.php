<?php

//@phpcs:ignoreFile Generic.Metrics.NestingLevel.TooHigh

namespace Rain\Tpl;

/**
 *  RainTPL
 *  --------
 *  Realized by Federico Ulfo & maintained by the Rain Team
 *  Distributed under GNU/LGPL 3 License
 */
class Parser {


	// variables


	/**
	 * Plugin container
	 *
	 * @var PluginContainer
	 */
	protected static $plugins = null;

	// configuration
	protected static $conf = [];

	// tags registered by the developers
	protected static $registeredTags = [];

	// tags natively supported
	protected static $tags = [
		'loop'             => [
			'({loop.*?})',
			'/{loop="(?<variable>\${0,1}[^"]*)"(?: as (?<key>\$.*?)(?: => (?<value>\$.*?)){0,1}){0,1}}/',
		],
		'loop_close'       => ['({\/loop})', '/{\/loop}/'],
		'loop_break'       => ['({break})', '/{break}/'],
		'loop_continue'    => ['({continue})', '/{continue}/'],
		'if'               => ['({if.*?})', '/{if="([^"]*)"}/'],
		'elseif'           => ['({elseif.*?})', '/{elseif="([^"]*)"}/'],
		'else'             => ['({else})', '/{else}/'],
		'if_close'         => ['({\/if})', '/{\/if}/'],
		'autoescape'       => ['({autoescape.*?})', '/{autoescape="([^"]*)"}/'],
		'autoescape_close' => ['({\/autoescape})', '/{\/autoescape}/'],
		'noparse'          => ['({noparse})', '/{noparse}/'],
		'noparse_close'    => ['({\/noparse})', '/{\/noparse}/'],
		'ignore'           => ['({ignore}|{\*)', '/{ignore}|{\*/'],
		'ignore_close'     => ['({\/ignore}|\*})', '/{\/ignore}|\*}/'],
		'include'          => ['({include.*?})', '/{include="([^"]*)"}/'],
		'function'         => [
			'({function.*?})',
			'/{function="([a-zA-Z_][a-zA-Z_0-9\:]*)(\(.*\)){0,1}"}/',
		],
		'ternary'          => ['({.[^{?>]*?\?.*?\:.*?})', '/{(.[^{?]*?)\?(.*?)\:(.*?)}/'],
		'variable'         => ['({\$.*?})', '/{(\$.*?)}/'],
		'constant'         => ['({#.*?})', '/{#(.*?)#{0,1}}/'],
	];

	// black list of functions and variables
	protected static $blackList = [
		'exec', 'shell_exec', 'pcntl_exec', 'passthru', 'proc_open', 'system',
		'posix_kill', 'posix_setsid', 'pcntl_fork', 'posix_uname', 'php_uname',
		'phpinfo', 'popen', 'file_get_contents', 'file_put_contents', 'rmdir',
		'mkdir', 'unlink', 'highlight_contents', 'symlink',
		'apache_child_terminate', 'apache_setenv', 'define_syslog_variables',
		'escapeshellarg', 'escapeshellcmd', 'eval', 'fp', 'fput',
		'ftp_connect', 'ftp_exec', 'ftp_get', 'ftp_login', 'ftp_nb_fput',
		'ftp_put', 'ftp_raw', 'ftp_rawlist', 'highlight_file', 'ini_alter',
		'ini_get_all', 'ini_restore', 'inject_code', 'mysql_pconnect',
		'openlog', 'passthru', 'php_uname', 'phpAds_remoteInfo',
		'phpAds_XmlRpc', 'phpAds_xmlrpcDecode', 'phpAds_xmlrpcEncode',
		'posix_getpwuid', 'posix_kill', 'posix_mkfifo', 'posix_setpgid',
		'posix_setsid', 'posix_setuid', 'posix_uname', 'proc_close',
		'proc_get_status', 'proc_nice', 'proc_open', 'proc_terminate',
		'syslog', 'xmlrpc_entity_decode',
	];

	public $var = [];

	protected $templateInfo = [];

	protected $config = [];

	protected $objectConf = [];


	public function __construct($config, $plugins, $registeredTags) {
		$this->config = $config;
		static::$plugins = $plugins;
		static::$registeredTags = $registeredTags;
	}


	/**
	 * Compile the file and save it in the cache
	 *
	 * @param string $templateName           : name of the template
	 * @param string $templateBasedir
	 * @param string $templateDirectory
	 * @param string $templateFilepath
	 * @param string $parsedTemplateFilepath : cache file where to save the template
	 * @throws Exception
	 * @throws Exception
	 * @throws Exception
	 */
	public function compileFile(
		$templateName,
		$templateBasedir,
		$templateDirectory,
		$templateFilepath,
		$parsedTemplateFilepath
	) {

		// open the template
		$fp = fopen($templateFilepath, "r");

		// lock the file
		if (flock($fp, LOCK_EX)) {

			// save the filepath in the info
			$this->templateInfo['template_filepath'] = $templateFilepath;

			// read the file
			$this->templateInfo['code'] = $code = fread($fp, filesize($templateFilepath));

			// xml substitution
			//@phpcs:ignore WebimpressCodingStandard.Commenting.Placement.AtTheEnd
			$code = preg_replace("/<\?xml(.*?)\?>/s", /*<?*/ "##XML\\1XML##", $code);

			// disable php tag
			if (!$this->config['php_enabled']) {
				$code = str_replace(["<?", "?>"], ["&lt;?", "?&gt;"], $code);
			}

			// xml re-substitution
			$code = preg_replace_callback("/##XML(.*?)XML##/s", static function ($match) {
				return "<?php echo '<?xml " . stripslashes($match[1]) . " ?>'; ?>";
			}, $code);

			$parsedCode = $this->compileTemplate($code, $isString = false, $templateBasedir, $templateDirectory, $templateFilepath);
			$parsedCode = "<?php if(!class_exists('Rain\Tpl')){exit;}?>" . $parsedCode;

			// fix the php-eating-newline-after-closing-tag-problem
			$parsedCode = str_replace("?>\n", "?>\n\n", $parsedCode);

			// create directories
			if (!is_dir($this->config['cache_dir'])) {
				mkdir($this->config['cache_dir'], 0755, true);
			}

			// check if the cache is writable
			if (!is_writable($this->config['cache_dir'])) {
				throw new Exception(
					'Cache directory ' . $this->config['cache_dir'] . 'doesn\'t have write permission. 
					Set write permission or set RAINTPL_CHECK_TEMPLATE_UPDATE to FALSE. More details on 
					https://www.raintpl.com/Documentation/Documentation-for-PHP-developers/Configuration/'
				);
			}

			// write compiled file
			file_put_contents($parsedTemplateFilepath, $parsedCode);

			// release the file lock
			flock($fp, LOCK_UN);
		}

		// close the file
		fclose($fp);
	}


	/**
	 * Compile a string and save it in the cache
	 *
	 * @param string $templateName           : name of the template
	 * @param string $templateBasedir
	 * @param string $templateFilepath
	 * @param string $parsedTemplateFilepath : cache file where to save the template
	 * @param string $code                   : code to compile
	 * @throws Exception
	 * @throws Exception
	 */
	public function compileString($templateName, $templateBasedir, $templateFilepath, $parsedTemplateFilepath, $code) {

		// open the template
		$fp = fopen($parsedTemplateFilepath, "w");

		// lock the file
		if (flock($fp, LOCK_SH)) {

			// xml substitution
			$code = preg_replace("/<\?xml(.*?)\?>/s", "##XML\\1XML##", $code);

			// disable php tag
			if (!$this->config['php_enabled']) {
				$code = str_replace(["<?", "?>"], ["&lt;?", "?&gt;"], $code);
			}

			// xml re-substitution
			$code = preg_replace_callback("/##XML(.*?)XML##/s", static function ($match) {
				return "<?php echo '<?xml " . stripslashes($match[1]) . " ?>'; ?>";
			}, $code);

			$parsedCode = $this->compileTemplate($code, $isString = true, $templateBasedir, $templateDirectory = null, $templateFilepath);

			$parsedCode = "<?php if(!class_exists('Rain\Tpl')){exit;}?>" . $parsedCode;

			// fix the php-eating-newline-after-closing-tag-problem
			$parsedCode = str_replace("?>\n", "?>\n\n", $parsedCode);

			// create directories
			if (!is_dir($this->config['cache_dir'])) {
				mkdir($this->config['cache_dir'], 0755, true);
			}

			// check if the cache is writable
			if (!is_writable($this->config['cache_dir'])) {
				throw new Exception(
					'Cache directory ' . $this->config['cache_dir'] . 'doesn\'t have write permission. 
					Set write permission or set RAINTPL_CHECK_TEMPLATE_UPDATE to false. 
					More details on https://www.raintpl.com/Documentation/Documentation-for-PHP-developers/Configuration/'
				);
			}

			// write compiled file
			fwrite($fp, $parsedCode);

			// release the file lock
			flock($fp, LOCK_UN);
		}

		// close the file
		fclose($fp);
	}


	public static function reducePath($path) {
		// reduce the path
		$path = str_replace("://", "@not_replace@", $path);
		$path = preg_replace("#(/+)#", "/", $path);
		$path = preg_replace("#(/\./+)#", "/", $path);
		$path = str_replace("@not_replace@", "://", $path);
		while (preg_match('#\w+\.\./#', $path)) {
			$path = preg_replace('#\w+/\.\./#', '', $path);
		}

		return $path;
	}


	/**
	 * Returns plugin container.
	 *
	 * @return PluginContainer
	 */
	protected static function getPlugins() {
		return static::$plugins ?: static::$plugins = new PluginContainer();
	}


	/**
	 * Compile template
	 *
	 * @access protected
	 * @param string $code : code to compile
	 */
	protected function compileTemplate($code, $isString, $templateBasedir, $templateDirectory, $templateFilepath) {

		// Execute plugins, before_parse
		$context = $this->getPlugins()->createContext([
			'code'              => $code,
			'template_basedir'  => $templateBasedir,
			'template_filepath' => $templateFilepath,
			'conf'              => $this->config,
		]);

		$this->getPlugins()->run('beforeParse', $context);
		$code = $context->code;

		// set tags
		foreach (static::$tags as $tag => $tagArray) {
			[$split, $match] = $tagArray;
			$tagSplit[$tag] = $split;
			$tagMatch[$tag] = $match;
		}

		$keys = array_keys(static::$registeredTags);
		$tagSplit += array_merge($tagSplit, $keys);

		//Remove comments
		if ($this->config['remove_comments']) {
			$code = preg_replace('/<!--(.*)-->/Uis', '', $code);
		}

		//split the code with the tags regexp
		$codeSplit = preg_split("/" . implode("|", $tagSplit) . "/", $code, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		//variables initialization
		$parsedCode = $commentIsOpen = $ignoreIsOpen = null;
		$openIf = $loopLevel = 0;

		// if the template is not empty
		if ($codeSplit) //read all parsed code
		{
			foreach ($codeSplit as $html) {

				//close ignore tag
				if (!$commentIsOpen && preg_match($tagMatch['ignore_close'], $html)) {
					$ignoreIsOpen = false;
				} //code between tag ignore id deleted
				elseif ($ignoreIsOpen) {
					//ignore the code
				} //close no parse tag
				elseif (preg_match($tagMatch['noparse_close'], $html)) {
					$commentIsOpen = false;
				} //code between tag noparse is not compiled
				elseif ($commentIsOpen) {
					$parsedCode .= $html;
				} //ignore
				elseif (preg_match($tagMatch['ignore'], $html)) {
					$ignoreIsOpen = true;
				} //noparse
				elseif (preg_match($tagMatch['noparse'], $html)) {
					$commentIsOpen = true;
				} //include tag
				elseif (preg_match($tagMatch['include'], $html, $matches)) {

					//get the folder of the actual template
					$actualFolder = $templateDirectory;

					if (is_array($this->config['tpl_dir'])) {
						foreach ($this->config['tpl_dir'] as $tpl) {
							if (substr($actualFolder, 0, strlen($tpl)) == $tpl) {
								$actualFolder = substr($actualFolder, strlen($tpl));
							}
						}
					} elseif (substr($actualFolder, 0, strlen($this->config['tpl_dir'])) == $this->config['tpl_dir']) {
						$actualFolder = substr($actualFolder, strlen($this->config['tpl_dir']));
					}

					//get the included template
					if (strpos($matches[1], '$') !== false) {
						$includeTemplate = "'$actualFolder'." . $this->varReplace($matches[1], $loopLevel);
					} elseif (strpos($matches[1], '/') === 0) {
						$includeTemplate = $this->varReplace(substr($matches[1], 1), $loopLevel);
					} else {
						$includeTemplate = $actualFolder . $this->varReplace($matches[1], $loopLevel);
					}

					// reduce the path
					$includeTemplate = Parser::reducePath($includeTemplate);

					if (strpos($matches[1], '$') !== false) {
						//dynamic include
						$parsedCode .= '<?php require $this->checkTemplate(' . $includeTemplate . ');?>';

					} else {
						//dynamic include
						$parsedCode .= '<?php require $this->checkTemplate("' . $includeTemplate . '");?>';
					}

				} //loop
				elseif (preg_match($tagMatch['loop'], $html, $matches)) {

					// increase the loop counter
					$loopLevel++;

					//replace the variable in the loop
					$var = $this->varReplace($matches['variable'], $loopLevel - 1, $escape = false);
					if (preg_match('#\(#', $var)) {
						$newvar = "\$newvar$loopLevel";
						$assignNewVar = "$newvar=$var;";
					} else {
						$newvar = $var;
						$assignNewVar = null;
					}

					// check black list
					$this->blackList($var);

					//loop variables
					$counter = "\$counter$loopLevel";       // count iteration

					if (isset($matches['key']) && isset($matches['value'])) {
						$key = $matches['key'];
						$value = $matches['value'];
					} elseif (isset($matches['key'])) {
						$key = "\$key$loopLevel";               // key
						$value = $matches['key'];
					} else {
						$key = "\$key$loopLevel";               // key
						$value = "\$value$loopLevel";           // value
					}


					//loop code
					$parsedCode .= "<?php $counter=-1; $assignNewVar if( isset($newvar) && ( is_array($newvar) || $newvar instanceof Traversable ) && sizeof($newvar) ) foreach( $newvar as $key => $value ){ $counter++; ?>";
				} //close loop tag
				elseif (preg_match($tagMatch['loop_close'], $html)) {

					//iterator
					$counter = "\$counter$loopLevel";

					//decrease the loop counter
					$loopLevel--;

					//close loop code
					$parsedCode .= "<?php } ?>";
				} //break loop tag
				elseif (preg_match($tagMatch['loop_break'], $html)) {
					//close loop code
					$parsedCode .= "<?php break; ?>";
				} //continue loop tag
				elseif (preg_match($tagMatch['loop_continue'], $html)) {
					//close loop code
					$parsedCode .= "<?php continue; ?>";
				} //if
				elseif (preg_match($tagMatch['if'], $html, $matches)) {

					//increase open if counter (for intendation)
					$openIf++;

					//tag
					$tag = $matches[0];

					//condition attribute
					$condition = $matches[1];

					// check black list
					$this->blackList($condition);

					//variable substitution into condition (no delimiter into the condition)
					$parsedCondition = $this->varReplace($condition, $loopLevel, $escape = false);

					//if code
					$parsedCode .= "<?php if( $parsedCondition ){ ?>";
				} //elseif
				elseif (preg_match($tagMatch['elseif'], $html, $matches)) {

					//tag
					$tag = $matches[0];

					//condition attribute
					$condition = $matches[1];

					// check black list
					$this->blackList($condition);

					//variable substitution into condition (no delimiter into the condition)
					$parsedCondition = $this->varReplace($condition, $loopLevel, $escape = false);

					//elseif code
					$parsedCode .= "<?php }elseif( $parsedCondition ){ ?>";
				} //else
				elseif (preg_match($tagMatch['else'], $html)) {

					//else code
					$parsedCode .= '<?php }else{ ?>';
				} //close if tag
				elseif (preg_match($tagMatch['if_close'], $html)) {

					//decrease if counter
					$openIf--;

					// close if code
					$parsedCode .= '<?php } ?>';
				} // autoescape off
				elseif (preg_match($tagMatch['autoescape'], $html, $matches)) {

					// get function
					$mode = $matches[1];
					$this->config['auto_escape_old'] = $this->config['auto_escape'];

					if ($mode == 'off' or $mode == 'false' or $mode == '0' or $mode == null) {
						$this->config['auto_escape'] = false;
					} else {
						$this->config['auto_escape'] = true;
					}

				} // autoescape on
				elseif (preg_match($tagMatch['autoescape_close'], $html, $matches)) {
					$this->config['auto_escape'] = $this->config['auto_escape_old'];
					unset($this->config['auto_escape_old']);
				} // function
				elseif (preg_match($tagMatch['function'], $html, $matches)) {

					// get function
					$function = $matches[1];

					// var replace
					if (isset($matches[2])) {
						$parsedFunction = $function . $this->varReplace($matches[2], $loopLevel, $escape = false, $echo = false);
					} else {
						$parsedFunction = $function . "()";
					}

					// check black list
					$this->blackList($parsedFunction);

					// function
					$parsedCode .= "<?php echo $parsedFunction; ?>";
				} //ternary
				elseif (preg_match($tagMatch['ternary'], $html, $matches)) {
					$parsedCode .= "<?php echo " . '(' . $this->varReplace($matches[1], $loopLevel, $escape = true, $echo = false) . '?' . $this->varReplace($matches[2], $loopLevel, $escape, $echo) . ':' . $this->varReplace($matches[3], $loopLevel, $escape, $echo) . ')' . "; ?>";
				} //variables
				elseif (preg_match($tagMatch['variable'], $html, $matches)) {
					//variables substitution (es. {$title})
					$parsedCode .= "<?php " . $this->varReplace($matches[1], $loopLevel, $escape = true, $echo = true) . "; ?>";
				} //constants
				elseif (preg_match($tagMatch['constant'], $html, $matches)) {
					$parsedCode .= "<?php echo " . $this->conReplace($matches[1]) . "; ?>";
				} // registered tags
				else {

					$found = false;
					foreach (static::$registeredTags as $tags => $array) {
						if (preg_match_all('/' . $array['parse'] . '/', $html, $matches)) {
							$found = true;
							$parsedCode .= "<?php echo call_user_func( static::\$registeredTags['$tags']['function'], " . var_export($matches, 1) . " ); ?>";
						}
					}

					if (!$found) {
						$parsedCode .= $html;
					}
				}
			}
		}


		if ($isString) {
			if ($openIf > 0) {

				$trace = debug_backtrace();
				$caller = array_shift($trace);

				$e = new SyntaxException("Error! You need to close an {if} tag in the string, loaded by {$caller['file']} at line {$caller['line']}");
				throw $e->templateFile($templateFilepath);
			}

			if ($loopLevel > 0) {

				$trace = debug_backtrace();
				$caller = array_shift($trace);
				$e = new SyntaxException("Error! You need to close the {loop} tag in the string, loaded by {$caller['file']} at line {$caller['line']}");
				throw $e->templateFile($templateFilepath);
			}
		} else {
			if ($openIf > 0) {
				$e = new SyntaxException("Error! You need to close an {if} tag in $templateFilepath template");
				throw $e->templateFile($templateFilepath);
			}

			if ($loopLevel > 0) {
				$e = new SyntaxException("Error! You need to close the {loop} tag in $templateFilepath template");
				throw $e->templateFile($templateFilepath);
			}
		}

		$html = str_replace('?><?php', ' ', $parsedCode);

		// Execute plugins, after_parse
		$context->code = $parsedCode;
		$this->getPlugins()->run('afterParse', $context);

		return $context->code;
	}


	protected function varReplace($html, $loopLevel = null, $escape = true, $echo = false) {

		// change variable name if loop level
		if (!empty($loopLevel)) {
			$html = preg_replace(['/(\$key)\b/', '/(\$value)\b/', '/(\$counter)\b/'], ['${1}' . $loopLevel, '${1}' . $loopLevel, '${1}' . $loopLevel], $html);
		}

		// if it is a variable
		if (preg_match_all('/(\$[a-z_A-Z][^\s]*)/', $html, $matches)) {
			// substitute . and [] with [" "]
			for ($i = 0; $i < count($matches[1]); $i++) {

				$rep = preg_replace('/\[(\${0,1}[a-zA-Z_0-9]*)\]/', '["$1"]', $matches[1][$i]);
				//$rep = preg_replace('/\.(\${0,1}[a-zA-Z_0-9]*)/', '["$1"]', $rep);
				$rep = preg_replace('/\.(\${0,1}[a-zA-Z_0-9]*(?![a-zA-Z_0-9]*(\'|\")))/', '["$1"]', $rep);
				$html = str_replace($matches[0][$i], $rep, $html);
			}

			// update modifier
			$html = $this->modifierReplace($html);

			// if does not initialize a value, e.g. {$a = 1}
			if (!preg_match('/\$.*=.*/', $html)) {

				// escape character
				if ($this->config['auto_escape'] && $escape) //$html = "htmlspecialchars( $html )";
				{
					$html = "htmlspecialchars( $html, ENT_COMPAT, '" . $this->config['charset'] . "', FALSE )";
				}

				// if is an assignment it doesn't add echo
				if ($echo) {
					$html = "echo " . $html;
				}
			}
		}

		return $html;
	}


	protected function conReplace($html) {
		return $this->modifierReplace($html);
	}


	protected function modifierReplace($html) {

		$this->blackList($html);
		if (strpos($html, '|') !== false && substr($html, strpos($html, '|') + 1, 1) != "|") {
			preg_match('/([\$a-z_A-Z0-9\(\),\[\]"->]+)\|([\$a-z_A-Z0-9\(\):,\[\]"->\s]+)/i', $html, $result);

			$function_params = $result[1];
			$result[2] = str_replace("::", "@double_dot@", $result[2]);
			$explode = explode(":", $result[2]);
			$function = str_replace('@double_dot@', '::', $explode[0]);
			$params = isset($explode[1]) ? "," . $explode[1] : null;

			$html = str_replace($result[0], $function . "(" . $function_params . "$params)", $html);

			if (strpos($html, '|') !== false && substr($html, strpos($html, '|') + 1, 1) != "|") {
				$html = $this->modifierReplace($html);
			}
		}

		return $html;
	}


	protected function blackList($html) {

		if (!$this->config['sandbox'] || !static::$blackList) {
			return true;
		}

		if (empty($this->config['black_list_preg'])) {
			$this->config['black_list_preg'] = '#[\W\s]*' . implode('[\W\s]*|[\W\s]*', static::$blackList) . '[\W\s]*#';
		}

		// check if the function is in the black list (or not in white list)
		if (preg_match($this->config['black_list_preg'], $html, $match)) {

			// find the line of the error
			$line = 0;
			$rows = explode("\n", $this->templateInfo['code']);
			while (!strpos($rows[$line], $html) && $line + 1 < count($rows)) {
				$line++;
			}

			// stop the execution of the script
			$e = new SyntaxException('Syntax ' . $match[0] . ' not allowed in template: ' . $this->templateInfo['template_filepath'] . ' at line ' . $line);
			throw $e->templateFile($this->templateInfo['template_filepath'])
				->tag($match[0])
				->templateLine($line);
		}

		return false;
	}


}
