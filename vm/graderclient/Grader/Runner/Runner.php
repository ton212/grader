<?php

namespace Grader\Runner;

abstract class Runner{
	public $extension;
	public $last_runtime = -1;
	public function support_ext($ext){
		return in_array($ext, $this->extension);
	}
	public abstract function version();
	public abstract function input($code);
	public abstract function run($stdin=null, $limits=array());
	public abstract function compile($code, $runner=null, $limits=array());
	public function cleanup(){}
	public abstract function has_error();
}