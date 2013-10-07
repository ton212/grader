<?php
namespace Grader\Client\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AddTask extends Command{
	public function __construct($client){
		parent::__construct();
		$this->client = $client;
	}
	protected function configure(){
		$this->setName('add')
			->setDescription('Create task in queue')
			->addArgument('runner', InputArgument::REQUIRED, 'Task runner extension')
			->addArgument('code', InputArgument::REQUIRED, 'Code as path to file')
			->addOption('input', null, InputOption::VALUE_OPTIONAL, 'stdin')
			->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Run type (run/input)', 'run')
			->addOption('to', null, InputOption::VALUE_OPTIONAL, 'Save to (answer/expected)')
			->addOption('limit-mem', null, InputOption::VALUE_OPTIONAL, 'Memory limit in MB')
			->addOption('limit-time', null, InputOption::VALUE_OPTIONAL, 'Time limit in seconds')
			->addOption('tube', null, InputOption::VALUE_OPTIONAL, 'Beanstalkd tube name', 'grader')
			->addOption('return-tube', null, InputOption::VALUE_OPTIONAL, 'Beanstalkd return tube name', 'grader-out')
			->addOption('priority', null, InputOption::VALUE_OPTIONAL, 'Task priority. 0 = least, 50 = webui submission', 10)
			->addOption('ttr', null, InputOption::VALUE_OPTIONAL, 'Time the task could be hold by a worker in second', 10)
			->addOption('delay', null, InputOption::VALUE_OPTIONAL, 'Seconds to wait before job become ready', 0);
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$this->client->beanstalk->useTube($input->getOption('tube'));
		$taskId = $this->client->beanstalk->put(
			json_encode(array(
				'type' => $input->getOption('type'),
				'lang' => $input->getArgument('runner'),
				'input' => $input->getOption('input'),
				'to' => $input->getOption('to'),
				'code' => file_get_contents($input->getArgument('code')),
				'limits' => array(
					'mem' => $input->getOption('limit-mem'),
					'time' => $input->getOption('limit-time'),
				)
			)),
			$input->getOption('priority'),
			$input->getOption('delay'), 
			$input->getOption('ttr')
		);
		$output->writeln('Task <info>'.$taskId.'</info> created.');
	}
}