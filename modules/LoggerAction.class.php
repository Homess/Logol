<?php

abstract class LoggerAction {
	protected $cfg = array();
//	abstract protected static $name;
	protected static $propertiesValues = array();
	/* properties list and values array
	 * Shoul be redefined in child class
	 * something like this: 
	 * 'output.type' => array('default' => 'nix', 'win', 'mac', 'auto')
	 * 'log.stack_trace' => array('default' => false, 'type' => 'boolean')
	 */
	private $pV; // a link to the proper static $propertiesValues array;
	
	abstract public function process($info);
	
	public function __construct($arr) {
		if (!is_array($arr)) {
			throw new Exception("A key-value array should be passed as parameter in action object constructor (".get_class($this).")");
		}
		eval('$this->pV = &'.get_class($this).'::$propertiesValues;');
		foreach($arr as $key => $value) {
			if (is_null($value) || !is_scalar($value)) continue;
			if ($key == 'type') continue;
			if (isset($this->pV[$key])) {
				if (isset($this->pV[$key]['type'])) {
					if (is_bool($value)) {
						if (is_array($this->pV[$key]['type']) ?
								array_search('boolean', $this->pV[$key]['type']) === false :
								$this->pV[$key]['type'] != 'boolean') {
							throw new Exception("Wrong type for the property $key in action class ".get_class($this));
						}
					} elseif (is_int($value)) {
						if (is_array($this->pV[$key]['type']) ?
								array_search('integer', $this->pV[$key]['type']) === false :
								$this->pV[$key]['type'] != 'integer') {
							throw new Exception("Wrong type for the property $key in action class ".get_class($this));
						}
					} elseif (is_float($value)) {
						if (is_array($this->pV[$key]['type']) ?
								array_search('float', $this->pV[$key]['type']) === false :
								$this->pV[$key]['type'] != 'float') {
							throw new Exception("Wrong type for the property $key in action class ".get_class($this));
						}
					} elseif (is_string($value)) {
						if (is_array($this->pV[$key]['type']) ?
								array_search('string', $this->pV[$key]['type']) === false :
								$this->pV[$key]['type'] != 'string') {
							throw new Exception("Wrong type for the property $key in action class ".get_class($this));
						}
					} else {
						throw new Exception("Wrong type for the property $key in action class ".get_class($this));
					}
					$ok = false;
					$type = is_array($this->pV[$key]['type']) ? reset($this->pV[$key]['type']) : $this->pV[$key]['type'];
					while (!$ok && $type) {
						switch ($type) {
							case 'boolean':
							case 'bool':
								if (is_bool($value)) $ok = true;
								break;
							case 'integer':
							case 'int':
								if (is_int($value)) $ok = true;
								break;
							case 'string':
								if (is_string($value)) $ok = true;
								break;
							default:
								throw new Exception("Wrong type for the property $key in action class ".get_class($this));
						}
						$type = is_array($this->pV[$key]['type']) ? next($this->pV[$key]['type']) : false;
					}
					if (!$ok) {
						throw new Exception("Wrong type for the property $key in action class ".get_class($this).". Should be ".(is_array($this->pV[$key]['type']) ? "one of ".implode(", ", $this->pV[$key]['type']) : $this->pV[$key]['type']).".");
					}
				} elseif (array_search($value, $this->pV[$key], true) === false) {
					throw new Exception("Wrong type for the property $key in action class ".get_class($this));
				}
			}
			$this->cfg[$key] = $value;
		}
	}
	
	final protected function getProperty($key, $lowercase = true) {
		if (!isset($this->cfg[$key]) && !isset($this->pV[$key]['default'])) {
			throw new Exception("No property '$key' is set in action ".get_class($this));
		}
		return isset($this->cfg[$key]) ? ((is_string($this->cfg[$key]) && $lowercase) ? strtolower($this->cfg[$key]) : $this->cfg[$key]) : $this->pV[$key]['default'];
	}
	
	final public function __get($name) {}
	final public function __set($name, $value) {}
	final public function __call($name, $args) {}
	
}

?>