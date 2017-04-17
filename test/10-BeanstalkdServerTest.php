<?php
namespace mle86\WQ\Tests;

use mle86\WQ\WorkServerAdapter\WorkServerAdapter;
use mle86\WQ\WorkServerAdapter\BeanstalkdWorkServer;
use Pheanstalk\PheanstalkInterface;

require_once __DIR__.'/../vendor/mle86/wq/test/helper/AbstractWorkServerAdapterTest.php';

class BeanstalkdServerTest
	extends AbstractWorkServerAdapterTest
{

	public function checkEnvironment () {
		$this->checkInDocker();
	}

	public function getWorkServerAdapter () : WorkServerAdapter {
		return new BeanstalkdWorkServer ("localhost");
	}

}

