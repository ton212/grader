<?php

namespace Grader\Runner;

class PhpRunner extends InterpretedRunner{
	public $extension = array('php');
	protected $interpreter = array('php');

	public function version(){
		$proc = $this->exec('--version');
		$proc->run();
		$stdout = $proc->getOutput();
		preg_match('~PHP ([0-9.]+)~', $stdout, $version);
		return $version[0];
	}
}