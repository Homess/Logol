<?php

class ConsoleLoggerAction extends LoggerAction {
	protected static $propertiesValues = array(
		'output.type' => array('default' => 'html', 'win', 'nix', 'mac'),
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
				$dspace = '  ';
				$tab = "\t";
				$br = "\r\n";
				break;
			case 'nix':
				$dspace = '  ';
				$tab = "\t";
				$br = "\n";
				break;
			case 'mac':
				$dspace = '  ';
				$tab = "\t";
				$br = "\r";
				break;
			case 'html':
				$dspace = '&nbsp;&nbsp;';
				$tab = '&nbsp;&nbsp;&nbsp;&nbsp;';
				$br = "<br />\r\n";
				break;
		}
		$str = $info['time'].$dspace.$info['level'].($info['logger'] == '' ? '' : ":{$info['logger']}");
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
				$str .= "$tab at {$traceline['function']}";
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
				$str .= "{$tab}and $i more ...$br";	
			}
		}
		echo $str;
	}
	
}

?>