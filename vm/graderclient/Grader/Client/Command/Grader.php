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

	public function job(\Pheanstalk_Job $job){
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
			// 1: Generate input
			$inputRunner = \Grader\Runner\RunnerRegistry::by_extension($data['input']['lang']);
			$input = $inputRunner->input($data['input']['code']);
			$output = array();
			// 2: Compile
			$this->client->writeln('<info>Compiling...</info>');
			$outputRunner = \Grader\Runner\RunnerRegistry::by_extension($data['output']['lang']);
			$subRunner = \Grader\Runner\RunnerRegistry::by_extension($data['submission']['lang']);
			$outputRunner->compile($data['output']['code']);
			$compileMsg = $subRunner->compile($data['submission']['code'], null, array(
				'mem' => 16,
				'time' => 5
			));
			// 2: Run for each input
			$correct = 1;
			$total_time = array();
			foreach($input as $no => $inp){
				$this->client->writeln('Running case '.$no, OutputInterface::VERBOSITY_DEBUG);
				// 2.1: Expected
				$expected = $outputRunner->run($inp . "\n");
				// 2.2: Submission
				try{
					$sub = $subRunner->run($inp . "\n", $data['limits']);
				}catch(\Symfony\Component\Process\Exception\RuntimeException $e){
					$this->client->writeln('<error>Case '.$no.' timeout. Aborting</error>');
					$output[] = 'T';
					$correct = 2;
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
				if(trim($expectedOut) == trim($subOut)){
					$output[] = 'P';
				}else{
					$output[] = 'F';
					$correct = 0;
				}
			}
			// 3: Cleanup
			$this->client->writeln('<info>Cleaning up...</info>', OutputInterface::VERBOSITY_DEBUG);
			$outputRunner->cleanup();
			$subRunner->cleanup();
			// 4: Submit
			$this->client->submit($job, array(
				'correct' => $correct,
				'result' => implode('', $output),
				'time' => array(
					'average' => array_sum($total_time) / count($input),
					'max' => max($total_time),
					'min' => min($total_time)
				),
				'compile' => $compileMsg
			));
		}

		return true;
	}
}