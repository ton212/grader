<?php
namespace Grader\Client\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Grader extends Command{
	public function __construct($client){
		parent::__construct();
		$this->client = $client;
	}
	protected function configure(){
		$this->setName('grader')
			->setDescription('Run task queue for grader judge system')
			->addOption('tube', null, InputOption::VALUE_OPTIONAL, 'Beanstalkd tube name', 'grader');
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$this->output = $output;
		$this->client->output = $output;
		$this->client->beanstalk->watch($input->getOption('tube'));
		$this->client->loop($output, array($this, 'job'));
	}

	public function job(\Pheanstalk\Job $job){
		$data = json_decode($job->getData(), true);

		if($data['type'] == 'input'){ // not used
			// get runner
			$runner = \Grader\Runner\RunnerRegistry::by_extension($data['lang']);
			if(!$runner){
				// invalid task, remove it from queue
				$this->client->writeln('<error>Invalid grader '.$data['lang'].'</error>');
				return true;
			}
			// build limit if missing
			if(!isset($data['limits'])){
				$data['limits'] = array();
			}
			// run input
			$out = $runner->input($data['code'], $data['limits']);
			//submit
			$this->client->submit($job, array(
				'result' => $out
			));
		}else if($data['type'] == 'run'){ // debug command line
			$runner = \Grader\Runner\RunnerRegistry::by_extension($data['lang']);
			if(!$runner){
				// invalid task, remove it from queue
				$this->client->writeln('<error>Invalid grader '.$data['lang'].'</error>');
				return true;
			}
			if(!isset($data['limits'])){
				$data['limits'] = array();
			}
			if(!isset($data['input'])){
				$data['input'] = null;
			}
			try{
				$runner->compile($data['code'], null, $data['limits']);
				$out = $runner->run($data['input'], $data['limits']);
				$runner->cleanup();
			}catch(\Symfony\Component\Process\Exception\RuntimeException $e){
				$runner->cleanup();
				$this->client->submit($job, array(
					'result' => 'TIMEOUT',
					'status' => 'error'
				), $this->output);
			}
			$this->client->submit($job, array(
				'result' => $out->getOutput(),
				'time' => $runner->last_runtime
			));
		}else if($data['type'] == 'grade'){
			// accept task
			$this->client->submit($job, array());
			if($data['comparator'] == 'hash'){
				// 1: Generate input
				$inputRunner = \Grader\Runner\RunnerRegistry::by_extension($data['input']['lang']);
				$input = $inputRunner->input($data['input']['code']);
				if(empty($input)){
					$this->client->submit($job, array(
						'correct' => 0,
						'result' => 'E',
						'time' => array(
							'average' => 0,
							'max' => 0,
							'min' => 0
						),
						'compile' => 'Internal error: Input statement does not return any test case.'
					));
					return true;
				}
				$output = array();
				// 2: Compile
				$this->client->writeln('<info>Compiling...</info>');
				$outputRunner = \Grader\Runner\RunnerRegistry::by_extension($data['output']['lang']);
				$subRunner = \Grader\Runner\RunnerRegistry::by_extension($data['submission']['lang']);
				$outputRunner->compile($data['output']['code']);
				try{
					$compileMsg = $subRunner->compile($data['submission']['code'], null, array(
						'mem' => 16,
						'time' => 5
					));
				}catch(\Symfony\Component\Process\Exception\RuntimeException $e){
					$subRunner->stop();
					$this->client->submit($job, array(
						'correct' => 0,
						'result' => 'T',
						'time' => array(
							'average' => $subRunner->last_compiletime,
							'max' => $subRunner->last_compiletime,
							'min' => $subRunner->last_compiletime
						),
						'compile' => 'Compiler timed out'
					));
					return true;
				}
				// check for compiler error
				if($subRunner->has_error()){
					$this->client->submit($job, array(
						'correct' => 0,
						'result' => 'E',
						'time' => array(
							'average' => $subRunner->last_compiletime,
							'max' => $subRunner->last_compiletime,
							'min' => $subRunner->last_compiletime
						),
						'compile' => $compileMsg
					));
					return true;
				}
				// 2: Run for each input
				$correct = 2;
				$total_time = array();
				$run_error = null;
				foreach($input as $no => $inp){
					$this->client->writeln('Running case '.$no, OutputInterface::VERBOSITY_DEBUG);
					// 2.1: Expected
					$expected = $outputRunner->run($inp . "\n");
					// 2.2: Submission
					try{
						$sub = $subRunner->run($inp . "\n", $data['limits']);
					}catch(\Symfony\Component\Process\Exception\RuntimeException $e){
						$subRunner->stop();
						$this->client->writeln('<error>Case '.$no.' timeout. Aborting</error>');
						$output[] = 'T';
						$correct = 0;
						break;
					}
					if($subRunner->last_runtime > -1){
						$total_time[] = $subRunner->last_runtime;
					}
					$this->client->writeln('<comment>Case '.$no.' solution took '.$outputRunner->last_runtime.'s submission took '.$subRunner->last_runtime.'s</comment>', OutputInterface::VERBOSITY_DEBUG);
					$subOut = $sub->getOutput();
					$expectedOut = $expected->getOutput();
					// 2.3: Compare
					// TODO: Use comparator
					$this->client->writeln("<comment>Input:\n".$inp."\n\nSubmission:\n".$subOut."\n\nSolution:\n</comment>".$expectedOut."\n\n", OutputInterface::VERBOSITY_DEBUG);

					// strip end of line from every lines
					$expectedOut = preg_replace('~[ \t]+$~m', '', $expectedOut);
					$subOut = preg_replace('~[ \t]+$~m', '', $subOut);

					if($subRunner->has_error()){
						$run_error = !$sub->getErrorOutput() ? $sub->getErrorOutput() : $sub->getOutput();
						$output[] = 'E';
						$correct = 0;
					}else if(rtrim($expectedOut) == rtrim($subOut)){
						$output[] = 'P';
						if($correct == 2){
							$correct = 1;
						}
					}else{
						if(strtolower($subOut) == strtolower($expectedOut)){
							$output[] = 'C';
						}else if(preg_replace('~[\\s]~', '', $subOut) == preg_replace('~[\\s]~', '', $expectedOut)){
							$output[] = 'S';
						}else{
							$output[] = 'F';
						}
						$correct = 0;
					}
				}
				// 3: Cleanup
				$this->client->writeln('<info>Cleaning up...</info>', OutputInterface::VERBOSITY_DEBUG);
				$outputRunner->cleanup();
				$subRunner->cleanup();
				// 4: Submit
				if($correct === 2){
					$correct = 0;
				}
				$this->client->submit($job, array(
					'correct' => $correct,
					'result' => implode('', $output),
					'time' => array(
						'average' => array_sum($total_time) / count($input),
						'max' => max($total_time),
						'min' => min($total_time)
					),
					'compile' => $compileMsg,
					'error' => $run_error
				));
			}else if($data['comparator'] == 'junit'){
				// upload and compile problem statement class
				$runner = new \Grader\Runner\JavaRunner();
				if($data['output']['lang'] === 'jar'){
					$runner->upload_jar(base64_decode($data['output']['code']));
					$this->client->writeln('<info>Suppliment jar added. Classpath is '.json_encode($runner->classPath).'</info>');
				}
				$this->client->writeln('<info>Compiling submission</info>');
				try{
					$compileMsg = $runner->compile($data['submission']['code'], null, array(
						'mem' => 16,
						'time' => 5
					));
				}catch(\Symfony\Component\Process\Exception\RuntimeException $e){
					$runner->stop();
					$this->client->submit($job, array(
						'correct' => 0,
						'result' => 'T',
						'time' => array(
							'average' => $runner->last_compiletime,
							'max' => $runner->last_compiletime,
							'min' => $runner->last_compiletime
						),
						'compile' => 'Compiler timed out'
					));
					return true;
				}
				// check for compiler error
				if($runner->has_error()){
					$this->client->submit($job, array(
						'correct' => 0,
						'result' => 'E',
						'time' => array(
							'average' => $runner->last_compiletime,
							'max' => $runner->last_compiletime,
							'min' => $runner->last_compiletime
						),
						'compile' => $compileMsg
					));
					return true;
				}
				$runner->setJUnit(true);
				$this->client->writeln('<info>Compiling JUnit test</info>');
				// upload and compile junit task
				$compileMsg = $runner->compile($data['input']['code']);
				// check for compiler error
				if($runner->has_error()){
					$this->client->submit($job, array(
						'correct' => 0,
						'result' => 'E',
						'time' => array(
							'average' => $runner->last_compiletime,
							'max' => $runner->last_compiletime,
							'min' => $runner->last_compiletime
						),
						'compile' => $compileMsg
					));
					return true;
				}
				// run junit
				$this->client->writeln('<info>Running JUnit test class '.$runner->className.'</info>');
				try{
					$junit = $runner->junit($data['limits']);
				}catch(\Symfony\Component\Process\Exception\RuntimeException $e){
					$runner->stop();
					$this->client->submit($job, array(
						'correct' => 0,
						'result' => 'T',
						'time' => array(
							'average' => $runner->last_compiletime,
							'max' => $runner->last_compiletime,
							'min' => $runner->last_compiletime
						),
						'compile' => 'Unit test timed out'
					));
					return true;
				}
				$runner->cleanup();
				$opt = $junit->getOutput();
				$optLn = explode("\n", $opt);
				// prettify
				foreach($optLn as $ind=>$v){
					if(preg_match("~\tat (sun|java|org\\.junit)~", $v)){
						unset($optLn[$ind]);
					}
				}
				$this->client->submit($job, array(
					'correct' => $runner->has_error() == 0 && preg_match('~^\.+$~', $optLn[1]),
					'result' => $optLn[1],
					'time' => array(
						'average' => $runner->last_compiletime,
						'max' => $runner->last_compiletime,
						'min' => $runner->last_compiletime
					),
					'error' => implode("\n", $optLn)
				));
				return true;
			}
		}

		return true;
	}
}