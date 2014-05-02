<?php

namespace Grader\Runner;

class CsRunner extends CompiledRunner{
	public $extension = array('cs');
	protected $compiler = array('gmcs', '-define:JUDGE', '-o+');
	protected $compiler_output = null;
	protected $runner = 'mono';
	protected $executable = 'code.exe';
	protected $input_name = 'code.cs';
	public function version(){
		$proc = $this->exec('--version');
		$proc->run();
		$stdout = $proc->getErrorOutput();
		preg_match('~Mono C# compiler version ([0-9.]+)~', $stdout, $version);
		if(!empty($version[0])){
			return 'Mono C# '.$version[1];
		}
	}
}
