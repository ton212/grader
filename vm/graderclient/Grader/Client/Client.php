<?php
namespace Grader\Client;

use Symfony\Component\Console\Output\OutputInterface;

class Client{
	public $console;
	public $beanstalk;
	public $guzzle;
	private $key = '';

	public function __construct(){
		$this->guzzle = new \Guzzle\Http\Client();
		$this->guzzle->setUserAgent('GraderClient/1.0 (Guzzle/'.\Guzzle\Common\Version::VERSION.') on '.gethostname());
	}

	public function setConsole($console){
		$this->console = $console;
		$this->register_console();
	}

	public function setBeanstalk($ip){
		$this->beanstalk = new \Pheanstalk\Pheanstalk($ip);
	}

	public function setGuzzle($url){
		$this->guzzle->setBaseUrl($url);
	}

	public function setKey($key){
		$this->key = $key;
	}

	public function loop($output, $callback){
		while(true){
			$this->writeln('<comment>New round of event loop</comment>', OutputInterface::VERBOSITY_DEBUG);
			$job = $this->beanstalk->reserve();
			if($job){
				$output->writeln('<info>Got job '.$job->getId().'</info>');
				$this->writeln($job->getData(), OutputInterface::VERBOSITY_DEBUG);
				if(call_user_func($callback, $job)){
					$this->beanstalk->delete($job);
					$this->writeln('<info>Job '.$job->getId().' finished.</info>');
				}else{
					$this->writeln('<error>Job '.$job->getId().' returned false, reusing job.</error>');
				}
			}
		}
	}

	public function writeln($text, $verbose=OutputInterface::VERBOSITY_NORMAL){
		if($this->output->getVerbosity() >= $verbose){
			$this->output->writeln($text);
			return true;
		}
		return false;
	}

	/**
	 * Turn in the job
	 * @return \Guzzle\Http\Message\Response
	 */
	public function submit(\Pheanstalk\Job $job, array $output){
		$output['id'] = $job->getId();

		$data = json_decode($job->getData(), true);
		foreach(array('result_id') as $copy){
			if(isset($data[$copy])){
				$output[$copy] = $data[$copy];
			}
		}

		$output['key'] = $this->key;

		$request = $this->guzzle->post('result', array(
			'Content-Type' => 'application/json'
		), json_encode($output));

		try{
			$response = $request->send();
		}catch(\Guzzle\Http\Exception\ServerErrorResponseException $e){
			$this->writeln('<error>Error submitting job '.$job->getId().' '.json_encode($output).'</error>');
			$this->writeln($e->getResponse());
			return false;
		}

		if(!$this->writeln('<info>Submitted job '.$job->getId().'</info> <comment>'.json_encode($output).'</comment>', OutputInterface::VERBOSITY_DEBUG)){
			$this->writeln('<info>Submitted job '.$job->getId().'</info>');
		}
		
		return $response;
	}

	public function getClass($cls){
		$reflection = new \ReflectionClass($cls);
		return $reflection->newInstance();
	}

	private function register_console(){
		$this->console->add(new Command\Grader($this));
		$this->console->add(new Command\AddTask($this));
	}
}