<?php
namespace mle86\WQ\Tests;

use mle86\WQ\WorkServerAdapter\WorkServerAdapter;
use mle86\WQ\WorkServerAdapter\BeanstalkdWorkServer;
use mle86\WQ\Job\QueueEntry;
use Pheanstalk\Exception\ConnectionException;
use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

require_once __DIR__.'/../vendor/mle86/wq/test/helper/AbstractWorkServerAdapterTest.php';

class BeanstalkdServerTest
	extends AbstractWorkServerAdapterTest
{

	public function checkEnvironment () {
		$this->checkInDocker();

		$this->assertTrue((getenv('BEANSTALKD_PORT') !== false),
			"No BEANSTALKD_PORT ENV variable found! Is this test running in the test container?");
		$this->assertGreaterThan(1024, getenv('BEANSTALKD_PORT'),
			"Invalid BEANSTALKD_PORT ENV variable!");
		$this->assertNotEquals(PheanstalkInterface::DEFAULT_PORT , getenv('BEANSTALKD_PORT'),
			"BEANSTALKD_PORT ENV variable should NOT be set to the default Beanstalkd port! " .
			"This prevents the test scripts from accidentally running on the host system.");

		$e = null;
		try {
			(new BeanstalkdWorkServer (new Pheanstalk ("localhost", PheanstalkInterface::DEFAULT_PORT)))
				->getNextQueueEntry("@this-should-not-exist-29743984375345", BeanstalkdWorkServer::NOBLOCK);
		} catch (ConnectionException $e) {
			// ok
		}
		$this->assertInstanceOf(\Exception::class, $e,
			"We managed to get a Beanstalkd connection on the Beanstalk default port! " .
			"This should not be possible inside the test container.");
	}

	public function getWorkServerAdapter () : WorkServerAdapter {
		return BeanstalkdWorkServer::connect("localhost", (int)getenv('BEANSTALKD_PORT'));
	}

	public function additionalTests (WorkServerAdapter $ws) {
		$this->checkDefaultTube($ws);
	}

	/**
	 * By default, all Beanstalkd clients are WATCHing the implicit "default" tube.
	 * Make sure we're NOT doing that.
	 *
	 * @param WorkServerAdapter $ws
	 */
	protected function checkDefaultTube (WorkServerAdapter $ws) {
		$queue_name = "default";

		$j = new SimpleJob (7660);
		$ws->storeJob($queue_name, $j);

		// new connection!
		$ws = $this->getWorkServerAdapter();

		// This is a new connection. It has never heard of any Job instance nor any queue names.
		// It should be unable to retrieve that job by polling an unrelated queue name.
		$ret = $ws->getNextQueueEntry(["unrelated-empty-queue-403165009"], $ws::NOBLOCK);
		$this->assertNull($ret,
			"We're still subscribed to Beanstalkd's implicit '{$queue_name}' tube! \n" .
			"(We successfully retrieved a job in the '{$queue_name}' tube by polling a completely different tube)");

		// ...but if we really want, we can definitely poll it:
		$ret = $ws->getNextQueueEntry(["unrelated-empty-queue-403165009", $queue_name], $ws::NOBLOCK);
		$this->assertInstanceOf(QueueEntry::class, $ret,
			"We were unable to poll a job from Beanstalkd's implicit '{$queue_name}' tube " .
			"after first ignoring and then re-watching it!");
		$this->assertSame($j->getMarker(), $ret->getJob()->getMarker(),
			"We got an UNEXPECTED job from the '{$queue_name}' tube!");
		$this->assertSame($queue_name, $ret->getWorkQueue(),
			"The job retrieved from the '{$queue_name}' tube contains an incorrect origin reference!");
	}

}

