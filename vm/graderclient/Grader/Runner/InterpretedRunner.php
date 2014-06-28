<?php

namespace Grader\Runner;

abstract class InterpretedRunner extends DockerRunner{
	protected $interpreter;
	public $last_compiletime = 0;

	public function input($code, $limits=array()){
		$runner = 'runner/input.'.$this->extension[0];
		$this->compile($code, file_get_contents($runner));
		$proc = $this->run(null, $limits);
		$out = $proc->getOutput();
		$this->cleanup();
		return json_decode($out);
	}
	public function compile($code, $runner=null, $limits=array()){
		$this->runner = $runner;

		$tmp = sys_get_temp_dir() . '/grader-' . uniqid();
		mkdir($tmp);

		file_put_contents($tmp.'/code', $code);
		if($runner){
			$this->cleanup_runner = true;
			file_put_contents($tmp.'/runner', $runner);
		}
		$this->dockerBind = array(
			$tmp.':/grader:ro'
		);
		$this->bind_tmp = $tmp;
		$this->lockCid();
		return null;
	}

	public function cleanup(){
		parent::cleanup();
		unlink($this->bind_tmp.'/code');
		if(isset($this->cleanup_runner)){
			unlink($this->bind_tmp.'/runner');
		}
		rmdir($this->bind_tmp);
	}


	public function run($stdin=null, $limits=array()){
		$this->limits = $limits;

		if($this->runner){
			$proc = $this->exec(array('/grader/runner', '/grader/code'));
		}else{
			$proc = $this->exec('/grader/code');
		}

		if(!empty($limits['time'])){
			$proc->setTimeout($limits['time']);
		}
		if($stdin){
			$proc->setStdin($stdin);
		}

		try{
			$start = microtime(true);
			$proc->run();
			$this->last_runtime = microtime(true) - $start;
		}catch(\Symfony\Component\Process\Exception\RuntimeException $e){
			$this->last_runtime = microtime(true) - $start;
			throw $e;
		}

		return $proc;
	}
}
