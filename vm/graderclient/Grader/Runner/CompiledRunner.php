<?php

namespace Grader\Runner;

abstract class CompiledRunner extends DockerRunner{
	protected $compiler;
	protected $compiler_output = null;
	protected $compiler_input = null;
	protected $input_name = 'code';
	protected $executable = 'program';
	protected $runner = null;
	public $last_compiletime=-1;

	public function input($code, $limits=array()){
		throw new \Exception('Not supported');
	}
	public function compile($code, $runner=null, $limits=array()){
		// Runner is not supported due to mono
		$tmp = sys_get_temp_dir() . '/grader-' . uniqid();
		mkdir($tmp);

		file_put_contents($tmp.'/'.$this->input_name, $code);
		$this->dockerBind = array(
			$tmp.':/grader:rw'
		);
		$this->bind_tmp = $tmp;

		$args = array();
		if($this->compiler_output){
			$args[] = $this->compiler_output;
			$args[] = '/grader/'.$this->executable;
		}
		if($this->compiler_input){
			$args[] = $this->compiler_input;
		}
		$args[] = '/grader/'.$this->input_name;

		$this->exec_root('chmod', '777', '/grader')->run();
		$proc = $this->exec($args);

		$proc->setTimeout(5);
		try{
			$start = microtime(true);
			$proc->run();
			$this->last_compiletime = microtime(true) - $start;
		}catch(\Symfony\Component\Process\Exception\RuntimeException $e){
			$this->last_compiletime = microtime(true) - $start;
			throw $e;
		}
		$this->dockerBind = array(
			$tmp.':/grader:ro'
		);
		$this->lockCid();
		return $proc->getErrorOutput();
	}

	public function cleanup(){
		parent::cleanup();
		unlink($this->bind_tmp.'/'.$this->input_name);
		unlink($this->bind_tmp.'/'.$this->executable);
		rmdir($this->bind_tmp);
	}


	public function run($stdin=null, $limits=array()){
		$this->limits = $limits;

		if($this->runner){
			$proc = $this->exec_app($this->runner, '/grader/'.$this->executable);
		}else{
			$proc = $this->exec_app('/grader/'.$this->executable);
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
