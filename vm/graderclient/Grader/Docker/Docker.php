<?php

namespace Grader\Docker;

class Docker{
	private $guzzle;
	public function __construct($host="http://127.0.0.1:4242/"){
		$this->guzzle = new \Guzzle\Http\Client($host);
		$this->guzzle->setUserAgent('GraderClient/1.0 (Guzzle/'.\Guzzle\Common\Version::VERSION.') on '.gethostname());
	}
}