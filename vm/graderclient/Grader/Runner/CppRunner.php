<?php

namespace Grader\Runner;

class CppRunner extends CRunner{
	public $extension = array('cpp');
	protected $compiler = array('g++', '-static', '-fno-optimize-sibling-calls', '-fno-strict-aliasing', '-DJUDGE', '-fno-asm', '-lm', '-s', '-x', 'c++', '-O2');
	protected $compiler_output = '-o';
	protected $input_name = 'code.cpp';
	public function version(){
		$proc = $this->exec('--version');
		$proc->run();
		$stdout = $proc->getOutput();
		preg_match('~g++ \([^\)]+\) ([0-9.]+)~', $stdout, $version);
		if(!empty($version[0])){
			return 'gcc '.$version[1];
		}
	}
}