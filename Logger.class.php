<?php
namespace \net\homess\Logger;
/*#
    # directory for config files (except global config - logger.cfg.xml in Logger root dir)
  cfg.dir                    # path[drw]                                       # cfg
    # config files extension without starting . (dot). Doesn't affect global config (logger.cfg.xml in Logger root dir)
    # default config for noname logger or no-config logger is extension without .(dot) in cfg.dir, for example
    # in default case - logger.cfg.xml file in ./cfg dir 
  cfg.ext                    # string[[a-zA-Z\d\-_]+(\.[a-zA-Z\d\-_]+)*]       # logger.cfg.xml
    # turns on|off config caching
  cfg.cache                  # boolean                                         # off
    # internal error file
  error.file                 # path[fw]                                        # logger.error.log
    # turns on|off script halt on internal errors. Some errors (such as problems with config directory 
    # or class file absence) stop the script nevertheless this option
  error.halt                 # boolean                                         # no
    # turns on|off console output of internal errors
	error.console              # boolean                                         # no
    # turns on|off php-errors logging
  handle.errors              # boolean                                         # yes
    # turns on|off unhandled exceptions logging
  handle.exceptions          # boolean                                         # yes
    # turns on|off logger messages logging
  handle.loggers             # boolean                                         # yes
    # turns on|off debug mode
  mode.debug                 # boolean                                         # off
#*/


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
	private static $_globalLogger;
	private static $_init = false;
	private static $_slash; // OS path separator - '/' or '\'
	
	const GLOBAL_CFG_NAME = 'logger.cfg.xml';
	const LOGGER_NAME_PREG = '/^[a-zA-Z\d_]++(?:\.[a-zA-Z\d_]++)*$/u';
//	const LOGGER_CLASS_PREG = '/^[a-zA-Z_][a-zA-Z\d_]*+(?:\\[a-zA-Z_][a-zA-Z\d_]*+)*$/u';
//	const MODULE_TYPE_PREG = '/^[a-zA-Z][a-zA-Z\d]*$/u';
	const LEVEL_PREG = '/^[a-zA-Z][a-zA-Z\d_]*$/u';
	
	private $_name; // name of the Logger
	private $_redirectLogger;
	private $_configFile;
	private $_properties;
	
	//@TODO todo
	private function __construct($name, Logger $redirect = NULL) {
		$this->_name = $name;
		if (!is_null(redirect)) {
			$this->_redirectLogger = $redirect;
			return;
		}
		$this->_properties = new Properties();
		$configFile = self::_getFilePath(self::_getConfig('dir.configuration'), $this->lname.self::CONFIG_EXT);
		if (file_exists($configFile)) {
			self::_readConfig($configFile, $this->LCFG);
		} else {
			self::_fileList(self::_getFilePath(self::_getConfig('dir.system_info'), self::UNSET_LOGGERS_LIST), $name);
			$this->LCFG = &self::$DCFG;
		}
	}
	
	/**
	 * Constructs a new logger instance or return existing one with the same name.
	 * @param string|object $name
	 * @return Logger instance
	 */
	public static function getInstance($name = '') {
		self::_init();
		if (is_string($name)) {
			if ($name === '') return self::_getInstance('');
			if (!preg_match(self::LOGGER_NAME_PREG, $name)) {
				$xname = preg_replace('#[\x00-\x1F\?]#', ' ', $name);
				self::_internalError('Wrong name of logger in Logger::getInstance($name) - '.$xname);
				return self::$_getInstance('Default - Bad name('.$xname.') ['.self::_getTraceLine(1).']', self::getInstance());
			}
			$lname = strtolower($name);
			if ($name == $lname) return self::_getInstance($name);
			return self::_getInstance($name, self::getInstance($lname));
		}
		if (is_object($name)) {
			$name = get_class($name);
			$lname = strtolower(str_replace('\\', '.', $name));
			if ($name == $lname) return self::_getInstance($name);
			return self::_getInstance($name, self::getInstance($lname));
		}
		if (is_null($name)) {
			self::_internalError('Use Logger::init() instead of Logger::getInstance(NULL)');
			return self::$_getInstance('Default - NULL ['.self::_getTraceLine(1).']', self::getInstance());
		}
		self::_internalError('Wrong type of logger name in Logger::getInstance($name) - '.gettype($name));
		return self::$_getInstance('Default - Bad type('.gettype($name).') ['.self::_getTraceLine(1).']', self::getInstance());
	}
	
	/**
	 * Real Factory template function. No name checking performed. 
	 * $name used as factory key, second parameter is for constructor only
	 * @param string $name Logger name
	 * @param Logger $redirect redirect Logger
	 * @return Logger
	 */
	private static function _getInstance($name, $redirect = NULL) {
		if(!array_key_exists($name, $redirect)) {
			self::$_loggers[$name] = new Logger($name, $redirect);
		}
		return self::$_loggers[$name];
	}
	
	//@TODO todo
	public static function init() {
		if (self::$_init) return;
		self::$_init = true;
		date_default_timezone_set(@date_default_timezone_get());
		self::$_slash = (substr(__FILE__, 0, 1) == '/' ? '/' : '\\');
		
	}
	
	//@TODO todo
	private static function _log($logger, $level, $message, $debug='unknown') {
		$level = strtolower($level);
		if ($logger instanceof Logger) {
			if (!self::_getConfig("handle.loggers")) return;
			if (!self::_getConfig('process.log', $logger)) return;
			if (!self::_getConfig('debug_mode')) {
				if ($debug === true) return;
				if ($debug !== false && self::_getConfig('is_debug_level', $logger, $level)) return;
			}
			$CFG = &$logger->LCFG;
		} else {
			$CFG = &self::$GCFG;
		}
		if (!self::_getConfig('process.level', $logger, $level)) return;
		if (!isset($CFG['logs'][$level])) {
			self::_fileList(self::_getFilePath(self::_getConfig("dir.system_info"),
				($logger instanceof Logger) ? $logger->lname.self::UNSET_EXT :
				self::UNSET_ERROR_LEVELS_LIST), $level);
		}
		$info = array();
		$info['time'] = self::_getTime();
		$info['level'] = strtoupper($level);
		$info['logger'] = '';
		if (is_string($message) && ($logger instanceof Logger)) {
			$info['logger'] = $logger->name;
			$info['message'] = $message;
			$trace = debug_backtrace();
			unset($trace[0]);
			if (isset($trace[1]['class']) && $trace[1]['class'] == 'Logger' && $trace[1]['function'] == '__call') {
				$trace[1] = $trace[2];
				unset($trace[2]);
			}
			$info['fullpath'] = isset($trace[1]['file']) ? $trace[1]['file'] : '?';
			$info['line'] = isset($trace[1]['line']) ? $trace[1]['line'] : '?';
			unset($trace[1]);
		} elseif (is_array($message) && $logger === false) {
			$trace = debug_backtrace();
			unset($trace[0]);
			unset($trace[1]);
			$info['message'] = $message[0];
			$info['fullpath'] = $message[1];
			$info['line'] = $message[2];
		} elseif ($message instanceof Exception && $logger === false) {
			$trace = $message->getTrace();
			$info['level'] = 'EXCEPTION['.get_class($message).']';
			$info['message'] = $message->getMessage();
			$info['fullpath'] = $message->getFile();
			$info['line'] = $message->getLine();
		} else {
			self::_internalError("Wrong parameter type (message or logger) in self::_log function", true);
		}
		$pos = strrpos($info['fullpath'], self::$slash);
		$info['filename'] = ($pos === false ? $info['fullpath'] : substr($info['fullpath'], $pos+1));
		$info['trace'] = array();
		foreach ($trace as $traceline) {
			$itrace = array();
			$function = (isset($traceline['class']) ? $traceline['class'].$traceline['type'] : '');
			$function .= $traceline['function'];
			$args = array();
			if (isset($traceline['args'])) {
				foreach($traceline['args'] as $arg) {
					$args[] = gettype($arg);
				}
				$function .= '('.implode(', ', $args).')';
			} else {
				$function .= '(?)';
			}
			$itrace['function'] = $function;
			$pos = isset($traceline['file']) ? strrpos($traceline['file'], self::$slash) : false;
			$itrace['filename'] = ($pos === false ? (isset($traceline['file']) ? $traceline['file'] : '?') : substr($traceline['file'], $pos+1));
			$itrace['fullpath'] = isset($traceline['file']) ? $traceline['file'] : '?';
			$itrace['line'] = isset($traceline['line'])? $traceline['line'] : '?';
			$info['trace'][] = $itrace;
		}
		foreach (self::_getConfig('actions', $logger, $level) as $actionName => $action) {
			if (!self::_getConfig('process.action', $logger, $level, $actionName)) continue;
			$actionClass = self::_getConfig('type', $logger, $level, $actionName);
			if (!strlen($actionClass)) {
				self::_internalError("null-length action type for log level '$level', action name '$actionName'.");
				continue;
			}
			$actionClass{0} = strtoupper($actionClass{0}).
			$actionClass.='LoggerAction';
			if (!class_exists($actionClass, false)) {
				if (!file_exists(self::_getFilePath(self::_getConfig('dir.modules'), $actionClass.".class.php"))) {
					self::_internalError("No $actionClass class file exists in ".self::_getConfig('dir.modules'));
					continue;
				}
				include_once(self::_getFilePath(self::_getConfig('dir.modules'), $actionClass.".class.php"));
				if (!class_exists($actionClass)) {
					self::_internalError("$actionClass class can't be found despite including $actionClass.class.php file");
					continue;
				}
			}
			if (!array_key_exists('LoggerAction', class_parents($actionClass, false))) {
				self::_internalError("$actionClass class isn't a child of LoggerAction class");
				continue;
			}
			try {
				$act = new $actionClass(isset($CFG['actions'][$actionName]) ? $action + $CFG['actions'][$actionName] : $action);
				$act->process($info);
			} catch (Exception $e) {
				$errstr = (($logger instanceof Logger) ? "Logger ({$logger->name})" : "Global logger")
						." at level {$level} had an action processing error ("
						.self::_getConfig('type', $logger, $level, $actionName).") ["
						.(strrpos($e->getFile(), self::$slash) === false ? $e->getFile() : substr($e->getFile(), 0, strrpos($e->getFile(), self::$slash) + 1))
						.":".$e->getLine()."]: ".$e->getMessage(); 
				self::_internalError($errstr);
			}
		}
	}
	
	//@TODO todo
	private static function _readConfig(&$xmlOrFile, &$CFG) {
		if ($xmlOrFile instanceof simpleXMLElement) {
			$xml = &$xmlOrFile;
		} elseif (is_string($xmlOrFile)) {
			$xml = simplexml_load_file($xmlOrFile);
		} else { 
			self::_internalError("Wrong parameter type (xmlOrFile) in self::_readConfig function", true);
		}
		try {
			foreach($xml->xpath('./property') as $property) {
				self::_setProperty($CFG, $property);
			}
			$actions = $xml->xpath('./action');
			if (!empty($actions) && !isset($CFG['actions'])) {
				$CFG['actions'] = array();
			}
			foreach($actions as $action) {
				$name = strtolower($action['name']);
				if (isset($ACFG)) {
					unset($ACFG);
				}
				$ACFG = array();
				if (!$name) {
					$CFG['actions'][] = &$ACFG;
				} else {
					$CFG['actions'][$name] = &$ACFG;
				}
				if ($action['type']) {
					if (!preg_match(self::ACTION_TYPE_PREG, $action['type'])) {
						throw new Exception("Wrong type ({$action['type']}) for 'action' tag");
					}
					$ACFG['type'] = strtolower($action['type']);
				} else {
					throw new Exception("No 'type' property in 'action' tag");
				}
				self::_readConfig($action, $ACFG);
			}
			$logs = $xml->xpath('./log');
			if (!empty($logs) && !isset($CFG['logs'])) {
				$CFG['logs'] = array();
			}
			foreach($logs as $log) {
				$level = strtolower($log['level']);
				if (!$level) continue;
				if (!isset($CFG['logs'][$level])) {
					$CFG['logs'][$level] = array();
				}
				self::_readConfig($log, $CFG['logs'][$level]);
			}
		} catch (Exception $e) {
			if (is_string($xmlOrFile)) {
				self::_internalError("Configuration reading error (".substr($xmlOrFile, strrpos($xmlOrFile, self::$slash) === false ? 0 : strrpos($xmlOrFile, self::$slash) + 1)."). ".$e->getMessage());
			} else {
				throw $e;
			}
		}
		if (is_string($xmlOrFile)) {
			foreach($xml->xpath("/logger-configuration/log/action[@name!='']") as $action) {
				foreach($xml->xpath("/logger-configuration/action[@name='{$action['name']}']") as $act) {
					if ($act && ((string)$act['type'] != (string)$action['type'])) {
						self::_internalError("Named action tag configuration error (name='{$action['name']}'). Global action tag type property ({$act['type']}) doesn't match local action tag type property ({$action['type']})", true);
					}
				}	
			}
		}
	}
	
	//@TODO todo
	private static function _setProperty(&$CFG, $xmlProperty) {
		$name = (string)$xmlProperty['name'];
		if ($name) {
			$value = (string)$xmlProperty;
			$lvalue = trim(strtolower($value));
			if ($lvalue == 'false' || $lvalue == 'off' || $lvalue == 'no') {
				$value = false;
			} elseif ($lvalue == 'true' || $lvalue == 'on' || $lvalue == 'yes') {
				$value = true;
			} elseif ($lvalue == (string)(int)$lvalue) {
				$value = (int)$lvalue;
			}
			$CFG[$name] = $value;
		}
	}
		
	//@TODO todo
	private static function _getConfig($name, $logger = false, $level = false, $action = false) {
		if ($logger instanceof Logger) {
			$CFG = &$logger->LCFG;
		} else {
			$CFG = &self::$GCFG;
		}
		$result = NULL;
		if ($level === false) {
			$result = isset($CFG[$name]) ? $CFG[$name] : NULL;
		} else {
			if (!isset($CFG['logs'][$level])) {
				$level = '*';
			}
			if ($action === false) {
				$result =  isset($CFG['logs'][$level][$name]) ? $CFG['logs'][$level][$name] : NULL;
			} else {
				$result = isset($CFG['logs'][$level]['actions'][$action]) ? 
					(isset($CFG['logs'][$level]['actions'][$action][$name]) ? 
						$CFG['logs'][$level]['actions'][$action][$name] : 
						(isset($CFG['actions'][$action][$name]) ? $CFG['actions'][$action][$name] : NULL)) : NULL;	
			}
		}
		if (is_null($result)) {
			if ($logger instanceof Logger) {
				$error = "Logger configuration error: can't find local configuration parameter [$name]";
				$error .= " in logger [{$logger->name}]".($level === false ? '' : ", level [$level]");
				$error .= ($action === false ? '' : ", action [$action]");
			} else {
				$error = "Logger configuration error: can't find global configuration parameter [$name]";
			}
			self::_internalError($error, true);
		}
		return $result;
	}
	
	//@TODO todo
	public static function _getGlobalProperty($name) {
		return self::_getConfig($name);
	}

	
	//@TODO todo
	public function fatal($msg) {
		self::_log($this, 'FATAL', $msg, false);
		die();
	}
	
	//@TODO todo
	public function error($msg) {
		self::_log($this, 'ERROR', $msg, false);
	}
	
	//@TODO todo
	public function warning($msg) {
		self::_log($this, 'WARNING', $msg, false);
	}

	//@TODO todo
	public function notice($msg) {
		self::_log($this, 'NOTICE', $msg, false);
	}
	
	//@TODO todo
	public function info($msg) {
		self::_log($this, 'INFO', $msg, false);
	}
	
	//@TODO todo
	public function debug($msg) {
		self::_log($this, 'DEBUG', $msg, true);
	}
	
	//@TODO todo
	public function __call($method, $vars) {
		if (preg_match(self::LEVEL_PREG, strtolower($method))) {
			$msg = (string)$vars[0];
			self::_log($this, $method, $msg);
		} else {
			self::_internalError("Calling impossible log level ($method) in logger {$this->name}");
		}
	} 
	
	//@TODO todo
	public function __set($name, $value) {
		self::_internalError("Trying to set a property ($name) in logger {$this->name}.");
	}
	
	//@TODO todo
	public function __get($name) {
		self::_internalError("Trying to get a property ($name) in logger {$this->name}");
		return null;
	}
	
	//@TODO todo
	public static function _err_handler($errno, $errmsg, $filename, $linenum) {
		$errtype='';
		switch ($errno) {
			case E_WARNING:
				$errtype = 'E_WARNING';
				break;
			case E_NOTICE:
				$errtype = 'E_NOTICE';
				break;
			case E_USER_ERROR:
				$errtype = 'E_USER_ERROR';
				break;
			case E_USER_WARNING:
				$errtype = 'E_USER_WARNING';
				break;
			case E_USER_NOTICE:
				$errtype = 'E_USER_NOTICE';
				break;
			case E_STRICT:
				$errtype = 'E_STRICT';
				break;
			case E_RECOVERABLE_ERROR:
				$errtype = 'E_RECOVERABLE_ERROR';
				break;
			case E_DEPRECATED:
				$errtype = 'E_DEPRECATED';
				break;
			case E_USER_DEPRECATED:
				$errtype = 'E_USER_DEPRECATED';
				break;
			default:
				$errtype = $errno;
		}
		self::_log(false, $errtype, array($errmsg, $filename, $linenum), false);
		if ($errno == E_USER_ERROR || $errno == E_RECOVERABLE_ERROR) {
			exit();
		}
	} 

	//@TODO todo
	public static function _ex_handler($exception) {
		self::_log(false, 'Exception', $exception, false);
		exit();
	}
	
	/**
	 * Returns current time in form YYYY-MM-DD HH:II:SS,MIL
	 * Used for internalErrors logging
	 * @return string ms precision current time
	 */
	private static function _getTime() {
		list($usec, $sec) = explode(' ', microtime(false));
		$ms = substr($usec, 2, 3);
		return date("Y-m-d H:i:s,$ms", $sec);
	}
	
	//@TODO todo
	public static function _getFilePath($dir, $file) {
		$filename = ($dir === '') ? $file : (substr($dir, -1) == self::$slash ? $dir.$file : $dir.self::$slash.$file);
		$filename = (self::$slash == "/" ? str_replace("\\", "/", $filename) : str_replace("/", "\\", $filename));
		$filename = ($filename{0} == self::$slash ? (self::$slash == "\\" ? substr(dirname(__FILE__), 0, 2) : '') : dirname(__FILE__).self::$slash).$filename;
		return $filename;  
	}

	//@TODO todo
	private static function _internalError($msg) {
		$echo = (!isset(self::$GCFG['internal_error.console_print']) || self::$GCFG['internal_error.console_print']) ? true : false;
		if (!isset(self::$GCFG['dir.system_info'])) {
			if ($echo) {
				echo "Logger error: No system informational directory ('dir.system_info' global parameter) is set. ";
				echo $msg;
			}
			exit();
		}
		$filename = self::_getFilePath(self::$GCFG['dir.system_info'], self::INTERNAL_ERRORS_FILE);
		if (!$f = fopen($filename, "ab")) {
			if ($echo) {
				echo "Logger error: can't open file for writing ($filename). ";
				echo $msg;
			}
			exit();
		} else {
			fwrite($f, self::_getTime()." $msg\r\n");
			fclose($f);
		}
		if ($break || !isset(self::$GCFG['internal_error.exit']) || self::$GCFG['internal_error.exit']) {
			if ($echo) echo $msg;
			exit();
		}
	}
	
	/**
	 * The same as _internalError, but breaks script execution despite on config options
	 */
	private static function _internalFatal($msg) {
		self::_internalError($msg);
		die();
	}
	
	/**
	 * Returns file and line number of calling file at particular depth
	 * @param int $depth trace depth. 0 is _getTraceLine call itself
	 * @return string file[lineNum]
	 */
	private static function _getTraceLine($depth) {
		$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		if (isset($trace[$depth])) {
			return $trace[$depth]['file'].':'.$trace[$depth]['line'];
		}
	}
	
}