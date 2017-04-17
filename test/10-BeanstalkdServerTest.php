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
		$this->assertNotEquals(PheanstalkInterface::DEFAULT_PORT , $_ENV['BEANSTALKD_PORT'],
			"BEANSTALKD_PORT ENV variable should NOT be set to the default Beanstalkd port! " .
			"This prevents the test scripts from accidentally running on the host system.");

		$e = null;
		try {
			(new BeanstalkdWorkServer ("localhost", PheanstalkInterface::DEFAULT_PORT))
				->getNextQueueEntry("@this-should-not-exist-29743984375345", BeanstalkdWorkServer::NOBLOCK);
		} catch (\Pheanstalk\Exception\ConnectionException $e) {
			// ok
		}
		$this->assertInstanceOf(\Exception::class, $e,
			"We managed to get a Beanstalkd connection on the Beanstalk default port! " .
			"This should not be possible inside the test container.");
	}

	public function getWorkServerAdapter () : WorkServerAdapter {
		return new BeanstalkdWorkServer ("localhost", (int)$_ENV['BEANSTALKD_PORT']);
	}

}

