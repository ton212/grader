<?php

namespace Grader\Runner;

class Py3Runner extends InterpretedRunner{
	public $extension = array('py3');
	protected $interpreter = array('python3', '-O', '-W', 'ignore');
	public function version(){
		$proc = $this->exec('--version');
		$proc->run();
		$stdout = $proc->getErrorOutput();
		preg_match('~Python ([0-9.]+)~', $stdout, $version);
		return $version[0];
	}
}
