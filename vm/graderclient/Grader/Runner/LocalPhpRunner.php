<?php

namespace Grader\Runner;

use Symfony\Component\Process\ProcessBuilder;

class LocalPhpRunner extends Runner{
	public $extension = array('php-local');
	private $wrapper = <<<CODE
echo serialize(run());
CODE;
	public function input($code){
		$php = new \Symfony\Component\Process\PhpProcess($code.$this->wrapper);
		$php->run();
		return unserialize($php->getOutput());
	}
	// no compile
	public function compile($code, $runner=null, $limits=array()){}
	public function run($stdin=null, $limits=array()){
		$php = new \Symfony\Component\Process\PhpProcess($code.$this->wrapper);
		if(!empty($limits['time'])){
			$php->setTimeout($limits['time']);
		}
		if($stdin){
			$php->setStdin($stdin);
		}
		try{
			$start = microtime(true);
			$php->run();
			$this->last_runtime = microtime(true) - $start;
		}catch(\Symfony\Component\Process\Exception\RuntimeException $e){
			$this->last_runtime = microtime(true) - $start;
			throw $e;
		}

		return $php;
	}
	public function version(){
		$php = new \Symfony\Component\Process\PhpExecutableFinder();
		$php = $php->find();
		if(!$php){
			return false;
		}
		$proc = new ProcessBuilder(array($php, '--version'));
		$proc = $proc->getProcess();
		$proc->run();
		$stdout = $proc->getOutput();
		preg_match('~PHP ([0-9.]+)~', $stdout, $version);
		return $version[0];
	}
}