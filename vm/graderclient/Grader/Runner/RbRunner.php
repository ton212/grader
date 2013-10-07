<?php

namespace Grader\Runner;

class RbRunner extends InterpretedRunner{
	public $extension = array('rb');
	protected $interpreter = array('ruby');
	public function version(){
		$proc = $this->exec('--version');
		$proc->run();
		$stdout = $proc->getOutput();
		preg_match('~^ruby ([0-9.p]+)~', $stdout, $version);
		return $version[0];
	}
}