<?php

namespace Grader\Runner;

use Symfony\Component\Process\ProcessBuilder;

abstract class DockerRunner extends Runner{
	public $dockerTag = 'grader';
	//public $docker;
	protected $dockerBind = array();
	protected $limits = array();
	//protected $dockerCid = null;
	//protected $cidFile;
	private $lastProc;

	public function __construct(){
		//$this->docker = new \Grader\Docker\Docker();
		$this->cidFile = tempnam(sys_get_temp_dir(), 'gd-dockercid-');
	}

	public function exec(){
		$exe = isset($this->interpreter) ? $this->interpreter : $this->compiler;
		if(func_num_args() > 0 && is_array(func_get_arg(0))){
			$cmd = array_merge($exe, func_get_arg(0));
		}else{
			$cmd = array_merge($exe, func_get_args());
		}
		return $this->exec_app($cmd);
	}

	public function exec_app(){
		if(func_num_args() > 0 && is_array(func_get_arg(0))){
			$cmd = func_get_arg(0);
		}else{
			$cmd = func_get_args();
		}
		$args = $this->get_docker_args();
		$args[] = implode(' ', array_map(function($item){
			return escapeshellarg($item);
		}, $cmd));
		//echo implode(' ', $args)."\n";
		$proc = new ProcessBuilder($args);
		return $this->lastProc = $proc->getProcess();
	}

	public function exec_root(){
		if(func_num_args() > 0 && is_array(func_get_arg(0))){
			$cmd = func_get_arg(0);
		}else{
			$cmd = func_get_args();
		}
		$args = $this->get_docker_args(true);
		$args[] = implode(' ', array_map(function($item){
			return escapeshellarg($item);
		}, $cmd));
		//echo json_encode($args)."\n";
		$proc = new ProcessBuilder($args);
		return $this->lastProc = $proc->getProcess();
	}

	public function has_error(){
		return false;
		/*$cid = file_get_contents($this->cidFile);
		if(empty($cid)){
			return false;
		}
		$proc = new ProcessBuilder(array('docker', 'inspect', $cid));
		$proc = $proc->getProcess();
		$proc->run();
		$data = json_decode($proc->getOutput());
		return $data[0]->State->ExitCode;*/
	}

	public function stop(){
		$cid = file_get_contents($this->cidFile);
		if(empty($cid)){
			return false;
		}
		$proc = new ProcessBuilder(array('docker', 'rm', '-f', $cid));
		$proc = $proc->getProcess();
		$proc->run();
	}

	protected function get_docker_args($root=false){
		$out = array('docker', 'run', '--net=none', '--rm=true', '-i');
		/*if($this->dockerCid){
			$out[] = '--volumes-from';
			$out[] = $this->dockerCid;
		}
		$out[] = '--cidfile';
		if($this->cidFile === null){
			throw new \Exception('null cidfile');
		}
		$out[] = $this->cidFile;
		if(is_file($this->cidFile)){
			unlink($this->cidFile);
		}*/

		if(!empty($this->dockerBind)){
			foreach($this->dockerBind as $bind){
				$out[] = '-v';
				$out[] = $bind;
			}
		}
		if(!empty($this->limits['mem'])){
			$out[] = '-m';
			$out[] = (string) ($this->limits['mem'] * 1024*1024);
		}
		if(!$root){
			$out[] = '--user=nobody';
		}
		$out[] = $this->dockerTag;
		$out = array_merge($out, array('bash', '-c'));
		return $out;
	}

	protected function lockCid(){
		//$this->dockerCid = file_get_contents($this->cidFile);
	}

	public function cleanup(){
		$this->stop();
		unlink($this->cidFile);
		$this->dockerCid = null;
	}
}
