<?php
namespace \net\homess\Logger;

/**
 * A stopper class for Logger.
 * Does nothing. Just for switching off Logger functionality without script code changes
 * @author Artem Messorosh
 */

class Logger {
	private static $_instance = false;
	private $_debug;
	private $_fatal;
	
	private function __construct() {
		$this->_debug = false;
		$this->_fatal = false;
	}
	
	public static function getLogger() {
		if (!self::$_instance) {
			self::$_instance = new Logger();
		}
		return self::$_instance;
	}
		
	private static function init() {}
	
	public function __set($name, $value) {
		trigger_error("$name Parameter set attempt detected. Logger doesn\'t support parameters setting.", E_USER_ERROR);
		die();
	}
	
	public function __get($name) {
		if (strtolower($name) == 'debug') {
			$this->debug = true;
		} elseif (strtolower($name) == 'fatal') {
			$this->fatal = true;
		}
		return $this;
	}
	
	public function __call($name, $arguments) {
		$this->$name;
		if ($this->_fatal && !$this->_debug) {
			die();
		}	
		$this->_fatal = false;
		$this->_debug = false;
	}
	
}
