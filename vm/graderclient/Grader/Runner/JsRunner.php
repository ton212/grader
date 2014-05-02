<?php

namespace Grader\Runner;

class JsRunner extends InterpretedRunner{
	public $extension = array('js');
	protected $interpreter = array('node');
	public function version(){
		$proc = $this->exec('--version');
		$proc->run();
		$stdout = $proc->getErrorOutput();
		preg_match('~v([0-9.]+)~', $stdout, $version);
		if(!empty($version[0])){
			return 'NodeJS '.$version[0];
		}
	}
}
