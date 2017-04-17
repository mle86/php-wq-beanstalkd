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

		$this->assertArrayHasKey('BEANSTALKD_PORT', $_ENV,
			"No BEANSTALKD_PORT ENV variable found! Is this test running in the test container?");
		$this->assertGreaterThan(1024, $_ENV['BEANSTALKD_PORT'],
			"Invalid BEANSTALKD_PORT ENV variable!");
	}

	public function getWorkServerAdapter () : WorkServerAdapter {
		return new BeanstalkdWorkServer ("localhost", (int)$_ENV['BEANSTALKD_PORT']);
	}

}

