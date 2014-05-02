<?php

namespace Grader\Runner;

class PyRunner extends InterpretedRunner{
	public $extension = array('py');
	protected $interpreter = array('pypy', '-W', 'ignore');
	private $check_pypy = false;
	public function compile($code, $runner=null, $limits=array()){
		// check for pypy
		if(!$this->check_pypy){
			$this->check_pypy = true;
			if(!$this->version()){
				// fallback to normal python
				$this->interpreter = array('python2', '-O', '-W', 'ignore');
			}
		}
		return parent::compile($code, $runner, $limits);
	}
	public function version(){
		$proc = $this->exec('--version');
		$proc->run();
		$stderr = $proc->getErrorOutput();
		//echo $stderr;
		preg_match('~PyPy ([0-9.]+)~', $stderr, $version);
		preg_match('~Python ([0-9.]+)~', $stderr, $pyversion);
		if(count($pyversion) == 0){
			return false;
		}
		if(count($version) > 0){
			return $version[0].' ('.$pyversion[0].')';
		}else{
			return $pyversion[0];
		}
	}
}
