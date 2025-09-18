<?php

namespace Rain\Tpl;

use ArrayAccess;
use ArrayObject;
use InvalidArgumentException;

/**
 * Maintains template plugins and call hook methods.
 */
class PluginContainer {


	/**
	 * Hook callables sorted by hook name.
	 *
	 * @var array
	 */
	private $hooks = [];

	/**
	 * Registered plugin instances sorted by name.
	 *
	 * @var array
	 */
	private $plugins = [];


	/**
	 * Safe method that will not override plugin of same name.
	 * Instead an exception is thrown.
	 *
	 * @param string $name
	 * @return PluginContainer
	 * @throws InvalidArgumentException Plugin of same name already exists in container.
	 */
	public function addPlugin($name, IPlugin $plugin) {
		if (isset($this->plugins[(string) $name])) {
			throw new InvalidArgumentException('Plugin named "' . $name . '" already exists in container');
		}

		return $this->setPlugin($name, $plugin);
	}


	/**
	 * Sets plugin by name. Plugin of same name is replaced when exists.
	 *
	 * @param string $name
	 * @return PluginContainer
	 */
	public function setPlugin($name, IPlugin $plugin) {
		$this->removePlugin($name);
		$this->plugins[(string) $name] = $plugin;

		foreach ((array) $plugin->declareHooks() as $hook => $method) {
			if (is_int($hook)) {
				// numerical key, method has same name as hook
				$hook = $method;
			}
			$callable = [$plugin, $method];
			if (!is_callable($callable)) {
				throw new InvalidArgumentException(sprintf(
					'Wrong callcable suplied by %s for "%s" hook ',
					$plugin::class,
					$hook
				));
			}
			$this->hooks[$hook][] = $callable;
		}

		return $this;
	}


	public function removePlugin($name) {
		$name = (string) $name;
		if (!isset($this->plugins[$name])) {
			return $this;
		}
		$plugin = $this->plugins[$name];
		unset($this->plugins[$name]);
		// remove all registered callables
		foreach ($this->hooks as &$callables) {
			foreach ($callables as $i => $callable) {
				if ($callable[0] === $plugin) {
					unset($callables[$i]);
				}
			}
		}

		return $this;
	}


	/**
	 * Passes the context object to registered plugins.
	 *
	 * @param string $hookName
	 * @return PluginContainer
	 */
	public function run($hookName, ArrayAccess $context) {
		if (!isset($this->hooks[$hookName])) {
			return $this;
		}
		$context['_hook_name'] = $hookName;
		foreach ($this->hooks[$hookName] as $callable) {
			call_user_func($callable, $context);
		}

		return $this;
	}


	/**
	 * Retuns context object that will be passed to plugins.
	 *
	 * @param array $params
	 * @return ArrayObject
	 */
	public function createContext($params = []) {
		return new ArrayObject((array) $params, ArrayObject::ARRAY_AS_PROPS);
	}


}
