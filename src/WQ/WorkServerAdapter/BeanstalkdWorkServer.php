<?php
namespace mle86\WQ\WorkServerAdapter;

use mle86\WQ\Exception\UnserializationException;
use mle86\WQ\Job\Job;
use mle86\WQ\Job\QueueEntry;
use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

/**
 * This adapter class implements the {@see WorkServerAdapter} interface.
 *
 * It connects to a Beanstalkd server.
 *
 * *Work Queues* are Beanstalkd's “tubes”.
 *
 * {@see getNextQueueEntry()} uses the RESERVE command,
 * {@see buryEntry()} uses the BURY command,
 * {@see storeJob()} and {@see requeueEntry()} use the PUT command,
 * and {@see deleteEntry()} uses the DELETE command.
 *
 * @see https://github.com/pda/pheanstalk  Uses the pda/pheanstalk package by Paul Annesley
 */
class BeanstalkdWorkServer
	implements WorkServerAdapter
{

	/** @var Pheanstalk */
	private $ph;
	/** @var array|null */
	private $lastWatched = [];


	public function __construct (string $host = "localhost", int $port = PheanstalkInterface::DEFAULT_PORT, int $connectTimeout = null) {
		$this->ph = new Pheanstalk ($host, $port, $connectTimeout);
	}

	/**
	 * This takes the next job from the named work queue
	 * and returns it.
	 *
	 * @param string $workQueue The name of the Work Queue to poll.
	 * @param int $timeout How many seconds to wait for a job to arrive, if none is available immediately.
	 *                        Set this to NOBLOCK if the method should return immediately.
	 *                        Set this to BLOCK if the call should block until a job becomes available, no matter how long it takes.
	 * @return QueueEntry  Returns the next job in the work queue,
	 *                     or NULL if no job was available after waiting for $timeout seconds.
	 * @throws UnserializationException
	 */
	public function getNextQueueEntry (string $workQueue, int $timeout = self::DEFAULT_TIMEOUT) : ?QueueEntry {
		if ($timeout === WorkServerAdapter::FOREVER) {
			// Beanstalkd has no real "forever" timeout option. This should be long enough...
			$timeout = 60 * 60 * 24 * 365 * 10;
		}

		$this->enterWorkQueues((array)$workQueue);

		$jobHandle = $this->ph->reserve($timeout);
		if (!$jobHandle) {
			// timeout, no job available
			return null;
		}

		if (is_scalar($workQueue)) {
			// Ok, we know where this job came from, as we were polling only one tube:
			$fromWorkQueue = $workQueue;
		} elseif (is_array($workQueue) && count($workQueue) === 1) {
			// Ditto, just a different call syntax
			$fromWorkQueue = reset($workQueue);
		} else {
			// We polled multiple tubes at once,
			// so we'll have to ask the server about the job's origin:
			$fromWorkQueue = ($this->ph->statsJob($jobHandle))['tube'];
		}

		try {
			return QueueEntry::fromSerializedJob(
				$jobHandle->getData(),
				$fromWorkQueue,
				$jobHandle,
				$jobHandle->getId() );
		} catch (UnserializationException $e) {
			$this->ph->bury($jobHandle);
			throw $e;
		}
	}

	/**
	 * Make sure we're WATCHing the correct tubes
	 * and nothing else.
	 *
	 * @param string[] $workQueues
	 */
	private function enterWorkQueues (array $workQueues) {
		$watch_tubes  = array_diff($workQueues, $this->lastWatched);
		$ignore_tubes = array_diff($this->lastWatched, $workQueues);

		foreach ($watch_tubes as $t) {
			$this->ph->watch($t);
		}
		foreach ($ignore_tubes as $t) {
			$this->ph->ignore($t);
		}

		$this->lastWatched = $workQueues;
	}

	/**
	 * Stores a job in the work queue for later processing.
	 *
	 * @param string $workQueue The name of the Work Queue to store the job in.
	 * @param Job $job The job to store.
	 * @param int $delay  The job delay in seconds after which it will become available to {@see getNextQueueEntry()}.
	 *                    Set to zero (default) for jobs which should be processed as soon as possible.
	 */
	public function storeJob (string $workQueue, Job $job, int $delay = 0) {
		$serializedJob = serialize($job);

		$this->ph->putInTube($workQueue, $serializedJob, PheanstalkInterface::DEFAULT_PRIORITY, $delay);
	}

	/**
	 * Buries an existing job
	 * so that it won't be returned by {@see getNextQueueEntry()} again
	 * but is still present in the system for manual inspection.
	 *
	 * This is what happens to failed jobs.
	 *
	 * @param QueueEntry $entry
	 */
	public function buryEntry (QueueEntry $entry) {
		$this->ph->bury($entry->getHandle());
	}

	/**
	 * Re-queues an existing job
	 * so that it can be returned by {@see getNextQueueEntry()}
	 * again at some later time.
	 * A {@see $delay} is required
	 * to prevent the job from being returned right after it was re-queued.
	 *
	 * This is what happens to failed jobs which can still be re-queued for a retry.
	 *
	 * @param QueueEntry $entry The job to re-queue. The instance should not be used anymore after this call.
	 * @param int $delay The job delay in seconds. It will become available for {@see getNextQueueEntry()} after this delay.
	 * @param string|null $workQueue By default, to job is re-queued into its original Work Queue ({@see QueueEntry::getWorkQueue}).
	 *                                With this parameter, a different Work Queue can be chosen.
	 */
	public function requeueEntry (QueueEntry $entry, int $delay, string $workQueue = null) {
		// Sadly, we cannot use Beanstalk's RELEASE function here --
		// the Job's serialization may have changed.

		$queue = $workQueue ?? $entry->getWorkQueue();

		$this->storeJob($queue, $entry->getJob(), $delay);
		$this->ph->delete($entry->getHandle());
	}

	/**
	 * Permanently deletes a job entry for its work queue.
	 *
	 * This is what happens to finished jobs.
	 *
	 * @param QueueEntry $entry The job to delete.
	 */
	public function deleteEntry (QueueEntry $entry) {
		$this->ph->delete($entry->getHandle());
	}

}
