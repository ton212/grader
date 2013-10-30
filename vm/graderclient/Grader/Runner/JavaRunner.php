<?php

namespace Grader\Runner;

class JavaRunner extends DockerRunner{
	public $extension = array('java');
	public $compiler = array('javac', '-encoding', 'UTF8');
	public $runner = 'java';
	public $last_runtime = -1;
	public $fileName = 'Input.java';
	public $className = 'Input';
	public $useJUnit = false;
	public $classPath = array('/grader');
	/**
	 * Set whether compile() load JUnit or not
	 */
	public function setJUnit($val){
		$this->useJUnit = $val;
		if($val){
			$this->addClassPath('/usr/share/java/junit4.jar');
		}else{
			$this->setClassPath(array('/grader'));
		}
	}
	public function setClassPath($cp){
		$this->classPath = $cp;
		$this->compiler = array('javac', '-encoding', 'UTF8', '-cp', implode(':', $this->classPath));
	}
	public function addClassPath($cp){
		$this->classPath[] = $cp;
		$this->setClassPath($this->classPath);
	}
	public function version(){
		$proc = $this->exec('-version');
		$proc->run();
		$stdout = $proc->getOutput();
		return trim($stdout);
	}
	public function input($code){
		$this->compile($code);
		copy('runner/JavaInput.jar', $this->bind_tmp.'/JavaInput.jar');
		copy('runner/gson-2.2.4.jar', $this->bind_tmp.'/gson-2.2.4.jar');
		$this->addClassPath('/grader/JavaInput.jar');
		$this->addClassPath('/grader/gson-2.2.4.jar');
		$proc = $this->exec_app($this->runner, '-Dfile.encoding=utf-8', '-cp', implode(':', $this->classPath), 'th.in.whs.grader.JavaInput', $this->className);
		$proc->run();
		$out = $proc->getOutput();
		$this->cleanup();
		return json_decode($out);
	}
	public function compile($code, $runner=null, $limits=array()){
		$this->mk_tmp();

		$this->dockerBind = array(
			$this->bind_tmp.':/grader:rw'
		);

		// reset to default value first
		$this->fileName = 'Input.java';
		$this->className = 'Input';

		file_put_contents($this->bind_tmp.'/'.$this->fileName, $code);

		$this->exec_root('chmod', '777', '/grader')->run();

		// compile and get output
		$retryCount = 0;
		while($retryCount < 3){
			$proc = $this->exec('/grader/'.$this->fileName);
			$proc->setTimeout(5);
			try{
				$start = microtime(true);
				$proc->run();
				$this->last_compiletime = microtime(true) - $start;
			}catch(\Symfony\Component\Process\Exception\RuntimeException $e){
				$this->last_compiletime = microtime(true) - $start;
				throw $e;
			}
			if(strpos($proc->getOutput(), 'error: ') === false){
				break;
			}else if($this->fileName == 'Input.java'){
				preg_match('~class [^ ]+ is public, should be declared in a file named ([^\.]+\.java)~', $proc->getOutput(), $match);
				if(count($match) < 2){
					return $proc->getOutput();
				}else{
					// recompile with the filename
					rename($this->bind_tmp.'/'.$this->fileName, $this->bind_tmp.'/'.$match[1]);
					$this->fileName = $match[1];
					$this->className = preg_replace('~\.java$~', '', $this->fileName);
				}
			}
			$retryCount++;
		}
		$this->dockerBind = array(
			$this->bind_tmp.':/grader:ro'
		);
		$this->lockCid();
		return $proc->getOutput();
	}
	public function run($stdin=null, $limits=array()){
		$this->limits = $limits;
		$proc = $this->exec_app($this->runner, '-Dfile.encoding=utf-8', '-cp', implode(':', $this->classPath), $this->className);

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
	public function cleanup(){
		parent::cleanup();
		// class can be compiled to more than one files, so globbing should be
		// the best way
		// XXX: Recursion
		foreach(glob($this->bind_tmp.'/*') as $file){
			unlink($file);
		}
		rmdir($this->bind_tmp);
	}
	public function upload_jar($data){
		$this->mk_tmp();
		file_put_contents($this->bind_tmp.'/supplement.jar', $data);
		$this->addClassPath('/grader/supplement.jar');
	}
	public function junit($limits){
		$this->limits = $limits;
		$proc = $this->exec_app($this->runner, '-Dfile.encoding=utf-8', '-cp', implode(':', $this->classPath), 'org.junit.runner.JUnitCore', $this->className);

		if(!empty($limits['time'])){
			$proc->setTimeout($limits['time']);
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
	private function mk_tmp(){
		if(!$this->bind_tmp){
			$tmp = sys_get_temp_dir() . '/grader-' . uniqid();
			mkdir($tmp);
			$this->bind_tmp = $tmp;
		}
	}
}