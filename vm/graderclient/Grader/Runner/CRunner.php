<?php

namespace Grader\Runner;

class CRunner extends CompiledRunner{
	public $extension = array('c');
	protected $compiler = array('gcc', '-static', '-fno-optimize-sibling-calls', '-fno-strict-aliasing', '-DJUDGE', '-fno-asm', '-lm', '-s', '-O2');
	protected $compiler_output = '-o';
	protected $input_name = 'code.c';
	public function version(){
		$proc = $this->exec('--version');
		$proc->run();
		$stdout = $proc->getErrorOutput();
		preg_match('~gcc version ([0-9.]+)~', $stdout, $version);
		if(!empty($version[0])){
			return 'gcc '.$version[1];
		}
	}
}
