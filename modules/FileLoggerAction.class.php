<?php
 
class FileLoggerAction extends LoggerAction {
	protected static $propertiesValues = array(
		'output.type' => array('default' => 'nix', 'win', 'mac', 'auto'),
		'output.file' => array('type' => 'string', 'default' => 'logger'),
		'output.file.split_period' => array('type' => array('integer', 'string'), 'default' => 0),
		'log.file_position' => array('default' => false, 'short', true, 'full'),
		'log.stack_trace' => array('default' => false, 'type' => 'boolean'),
		'log.stack_trace.depth' => array('type' => 'integer', 'default' => 0),
		'log.stack_trace.file_position' => array('default' => false, 'short', true, 'full')
	);
	
	public function __construct($arr) {
		parent::__construct($arr);
	}
	
	public function process($info) {
		switch($this->getProperty('output.type')) {
			case 'win':
				$br = "\r\n";
				break;
			case 'nix':
				$br = "\n";
				break;
			case 'mac':
				$br = "\r";
				break;
			case 'auto':
				$br = (strpos(__FILE__, "\\") === false) ? "\n" : "\r\n";
				break;
		}
		$str = "{$info['time']}  {$info['level']}".($info['logger'] == '' ? '' : ":{$info['logger']}");
		$dopath = $this->getProperty('log.file_position');
		if ($dopath === 'short') {
			$str .= " ({$info['filename']}:{$info['line']})";
		} elseif ($dopath === true || $dopath === 'full') {
			$str .= " ({$info['fullpath']}:{$info['line']})";
		}
		$str .= " - {$info['message']}$br";
		if ($this->getProperty('log.stack_trace') === true) {
			$i = $this->getProperty('log.stack_trace.depth');
			$dopath = $this->getProperty('log.stack_trace.file_position');
			foreach ($info['trace'] as $traceline) {
				$str .= "\tat {$traceline['function']}";
				if ($dopath === 'short') {
					$str .= " ({$traceline['filename']}:{$traceline['line']})";
				} elseif ($dopath === true || $dopath === 'full') {
					$str .= " ({$traceline['fullpath']}:{$traceline['line']})";
				}
				$str .= $br;
				if (--$i == 0) break;
			}
			$i += count($info['trace']) - $this->getProperty('log.stack_trace.depth');
			if ($i > 0) {
				$str .= "\tand $i more ...$br";	
			}
		}
		if (!$f = fopen($fn = $this->getFileName($info), 'ab')) {
			throw new Exception("Can't open file for log writing ($fn)");
		}
		if (fwrite($f, $str) === false) {
			fclose($f);
			throw new Exception("Log writing into file ($fn) failed");
		}
		fclose($f);
	}
	
	private function getFileName(&$info) {
		switch ($this->getProperty('output.file')) {
			case 'global':
				$fileName = '_';
				break;
			case 'logger':
				$fileName = ($info['logger'] ? str_replace(' ', '_', strtolower($info['logger'])) : '_');
				break;
			case 'level':
				$fileName = str_replace(' ', '_', strtolower($info['logger'])).'.'.strtolower($info['level']);
				break;
		}
		if (isset($fileName)) {
			if ($this->getProperty('output.file.split_period') != 0) {
				$fileName .= '.%Y%M%D-%H%I';
			}
			$fileName = Logger::_getFilePath(Logger::_getGlobalProperty('dir.file_logs'), $fileName.'.logger.log');
		} else {
			$fileName = $this->getProperty('output.file', false);
			if (substr($fileName, 0, 2) == './' || substr($fileName, 0, 3) == '../') {
				$fileName = Logger::_getFilePath('', $fileName);
				echo "<br>$fileName<br>";
			} elseif ($fileName{0} != '/') {
				$fileName = Logger::_getFilePath(Logger::_getGlobalProperty('dir.file_logs'), $fileName);
			}
		}
		if (($split = $this->getProperty('output.file.split_period')) != 0) {
			if (!preg_match('/^[1-9]\d*[HhDdWwMmYy]?$/', $split)) {
				throw new Exception("Wrong value for the 'output.file.split_period' property (".$this->getProperty('outpur.file.split_period').")");
			}
			if (is_int($split)) {
				$value = $split;
				$timeframe = '';
			} else {
				$value = (int)substr($split, 0, -1);
				$timeframe = strtoupper(substr($split, -1));
			}
			
			if ($timeframe == '' && $value >= 1440) {
				$value = (int)floor($value/1440);
				$timeframe = 'D';
			}
			if ($timeframe == 'H' && $value >= 24) {
				$value = (int)floor($value/24);
				$timeframe = 'D';
			}
			if ($timeframe == 'D' && $value >= 30) {
				$value = (int)floor($value/30);
				$timeframe = 'M';
			}
			if ($timeframe == 'W' && $value >= 52) {
				$value = (int)floor($value/52);
				$timeframe = 'Y';
			}
			if ($timeframe == 'M' && $value >= 12) {
				$value = (int)floor($value/12);
				$timeframe = 'Y';
			}
			$today = getdate();
			$time = time();
			$beginDate = mktime(0, 0, 0, 1, 1, 2010);
// Slightly sophisticated algorthm for split date and file counter evaluating. 
// All numerical constants are unchangeable due to their nature (minutes in day, days in year etc.)
// It's very lazy to use letter constants instead of numerical ones.			
			switch ($timeframe) {
				case '':
					if ($value <= 720) {
						$baseDate = mktime(0, 0, 0, $today['mon'], $today['mday'], $today['year']);
						$count = ceil(1440/$value)*(($today['year'] - 2010)*366 + $today['yday']) + ($time - $baseDate)/(60*$value);
						$baseDate += (intval(($time - $baseDate)/(60*$value)))*60*$value;
					} else {
						$baseDate = mktime(0, 0, 0, $today['mon'], 1, $today['year']);
						$count = ceil(44640/$value)*(($today['year'] - 2010)*12 + $today['mon'] - 1) + ($time - $baseDate)/(60*$value);
						$baseDate += (intval(($time - $baseDate)/(60*$value)))*60*$value;
					}
					break;
				case 'H':
					if ($value <= 12) {
						$baseDate = mktime(0, 0, 0, $today['mon'], $today['mday'], $today['year']);
						$count = ceil(24/$value)*(($today['year'] - 2010)*366 + $today['yday']) + ($time - $baseDate)/(3600*$value);
						$baseDate += (intval(($time - $baseDate)/(3600*$value)))*3600*$value;
					} else {
						$baseDate = mktime(0, 0, 0, $today['mon'], 1, $today['year']);
						$count = ceil(744/$value)*(($today['year'] - 2010)*12 + $today['mon'] - 1) + ($time - $baseDate)/(3600*$value);
						$baseDate += (intval(($time - $baseDate)/(3600*$value)))*3600*$value;
					}
					break;
				case 'D':
					if ($value <= 16) {
						$baseDate = mktime(0, 0, 0, $today['mon'], 1, $today['year']);
						$count = ceil(31/$value)*(($today['year'] - 2010)*12 + $today['mon'] -1) + ($time - $baseDate)/(86400*$value);
						$baseDate += (intval(($time - $baseDate)/(86400*$value)))*86400*$value;
					} else {
						$baseDate = mktime(0, 0, 0, 1, 1, $today['year']);
						$count = ceil(366/$value)*($today['year'] - 2010) + ($time - $baseDate)/(86400*$value);
						$baseDate += (intval(($time - $baseDate)/(86400*$value)))*86400*$value;
					}
					break;
				case 'W':
					if ($value <= 27) {
						$baseDate = mktime(0, 0, 0, 1, 1, $today['year']);
						while (idate('w', $baseDate) != 1) {
							$baseDate -= 86400;
						}
						$count = ceil(54/$value)*($today['year'] - 2010) + ($time - $baseDate)/(604800*$value);
						$baseDate += (intval(($time - $baseDate)/(604800*$value)))*604800*$value;
					} else {
						$baseDate = mktime(0, 0, 0, 1, 4, 2010);
						$count = ($time - $baseDate)/(604800*$value);
						$baseDate += (intval(($time - $baseDate)/(604800*$value)))*604800*$value;
					}
					break;
				case 'M':
					if ($value <= 6) {
						$count = ceil(12/$value)*($today['year'] - 2010) + ($today['mon'] - 1)/$value;
						$baseDate = mktime(0, 0, 0, intval(($today['mon'] - 1)/$value)*$value + 1, 1, $today['year']);
					} else {
						$count = intval((($today['year'] - 2010)*12 + $today['mon'] -1)/$value);
						$baseDate = mktime(0, 0, 0, $count*$value + 1, 1, 2010);
					}
					break;
				case 'Y':
					$count = intval(($today['year'] - 2010)/$value);
					$baseDate = mktime(0, 0, 0, 1, 1, 2010 + $count*$value);
					break;
			}
			$replace = explode('|', date('Y|y|m|n|d|j|H|G|i', $baseDate));
			$replace[] = intval($replace[8]);
			$replace[] = intval($count);
			$replace[] = $value.$timeframe;
		} else {
			$replace = '';
		}
		$search = array('%Y', '%y', '%M', '%m', '%D', '%d', '%H', '%h', '%I', '%i', '%C', '%P');
		$fileName = str_replace($search, $replace, $fileName);
		$fileName = str_replace('%', '', $fileName);
		return $fileName;
	}

}

?>