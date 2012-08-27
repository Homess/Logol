<?php
namespace net\homess\Logger;

/**
 * Logger class for Logging.
 * Logs php system errors, unhandled exceptions, and Logger->messages
 * Logger has flexible, extandable and customizable system of message levels, actions, filters and formatters
 * Logger uses factory pattern for logger instances. Loggers are identified by names.
 * Loggers are easily configurable by xml config files.
 * Loggers has hierarcial name structure, so it easy to configurate as a group of loggers, so one particular logger
 * No configuration in code. The only thing is needed is to call Logger::getInstance() method;
 * Logger can be used only for Logging system errors and unhandled exceptions, in this case just call Logger::init()
 * Logger has a stopper class, so if you want to stop any Logger work, just replace the Logger.class.php 
 *	with Logger.stop.class.php. No scripts code change is need.
 * You can use any desired message level just by calling $logger->myMessageLevel($message). Just six of them
 *	are considered as predefined (fatal, error, warning, notice, info, and debug). Just two of them have
 *	unconfigurable behaviour - fatal allways halts the script and debug is working only in debug mode.
 *	Other levels have default behaviour, but it can be easily redefined.
 * You can use level chains, for example: $logger->warning()->myWarnings()->mySqlWarning($message). You can define
 *	chain levels as in form of methods, so in form of properties: $logger->warning->myWarnings->mySqlWarning($message)
 *	This is useful for flexible level configuration. Then chains are used the last existing configuration will be taken.
 *	The fatal or debug level in chain adds its predefined behaviour (halting or working in debug, then both,
 *	halting will be processed only in debug mode). The predefined behaviour adds in any case, despite on using this
 *	particular level configuration or the place of fatal or debug level in chain.
 * The only configuration is needed in minimal - the real and writtable path to error.log file (for any internal errors
 *	such as configuration problems, directopries or files absence, database connection errors and other environment
 *	customization problems.
 * @author Artem Messorosh
 */
class Logger {
	private static $_loggers = array();
	private static $_init = false;
	private static $_slash; // (strpos(__FILE__, '/') !== false ? '/' : '\\');
	
	private $_name; // name of the Logger
	private $_stop;
	private $_debug;
	
	private function __construct($name) {
		$this->_name = $name;
		$this->_stop = false;
		$this->_debug = false;
	}
	
	/**
	 * Constructs a new logger instance or return existing one with the same name.
	 * @param string $name
	 * @return logger instance
	 */
	public static function getLogger($name = '') {
		self::_init();
		if (is_object($name)) {
			$lgName = get_class($name);
		} else {
			$lgName = (string)$name;
		}
		$lname = strtolower($lgName);
		if(!array_key_exists($lname, self::$_loggers)) {
			self::$_loggers[$lname] = new Logger($lgName);
		}
		return self::$_loggers[$lname];
	}
	
	public static function _init() {
		if (self::$_init) return;
		self::$_init = true;
		date_default_timezone_set(@date_default_timezone_get());
		self::$_slash = (substr(__FILE__, 0, 1) == '/' ? '/' : '\\');
	}
	
	private static function _log($logger, $level, $message, $debug='unknown') {
		if ($f = fopen(__DIR__.'/Logger.log', 'ab')) {
			fwrite($f, self::_getTime()." $level ($logger->_name): $message\r\n");
			fclose($f);
		}
	}
	
	public function fatal($msg = null) {
		return $this->__call('FATAL', func_get_args());
	}
	
	public function __get($method) {
		return $this->__call($method, array());
	}
	
	public function __call($method, $vars) {
		if (strtolower($method) == 'fatal') {
			$this->_stop = true;
		} elseif (strtolower($method) == 'debug') {
			$this->_debug = true;
		}
		if (count($vars) > 0) {
			self::_log($this, $method, $vars[0]);
			if ($this->_stop && !$this->debug) {
				die();
			}
			$this->_stop = false;
			$this->_debug = false;
		} else {
			return $this;
		}
	} 
	
	private static function _getTime() {
		$ms = microtime(true);
		$ms -= floor($ms);
		$ms = (int)($ms*1000);
		$ms = str_pad($ms, 3, '0', STR_PAD_LEFT);
		return date("Y-m-d H:i:s,$ms");
	}

}