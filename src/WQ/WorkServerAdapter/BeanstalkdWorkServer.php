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
    private $lastWatched = ["default"];  // By default, all clients are watching the "default" tube. We don't want that.


    /**
     * Constructor.
     * Takes an already-configured {@see Pheanstalk} instance to work with.
     * Does not attempt to establish a connection itself --
     * use the {@see connect()} factory method for that instead.
     *
     * @param Pheanstalk $ph
     */
    public function __construct(Pheanstalk $ph)
    {
        $this->ph = $ph;
    }

    /**
     * Factory method.
     * This will create a new {@see Pheanstalk} instance by itself.
     *
     * See {@see Pheanstalk::__construct} for the parameter descriptions.
     *
     * @param string $host
     * @param int $port
     * @param int|null $connectTimeout
     * @return self
     */
    public static function connect(
        string $host = "localhost",
        int $port = PheanstalkInterface::DEFAULT_PORT,
        int $connectTimeout = null
    ): self {
        return new self (new Pheanstalk ($host, $port, $connectTimeout));
    }


    public function getNextQueueEntry($workQueue, int $timeout = self::DEFAULT_TIMEOUT): ?QueueEntry
    {
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
                $jobHandle->getId());
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
    private function enterWorkQueues(array $workQueues)
    {
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

    public function storeJob(string $workQueue, Job $job, int $delay = 0): void
    {
        $serializedJob = serialize($job);

        $this->ph->putInTube($workQueue, $serializedJob, PheanstalkInterface::DEFAULT_PRIORITY, $delay);
    }

    public function buryEntry(QueueEntry $entry): void
    {
        $this->ph->bury($entry->getHandle());
    }

    public function requeueEntry(QueueEntry $entry, int $delay, string $workQueue = null): void
    {
        // Sadly, we cannot use Beanstalk's RELEASE function here --
        // the Job's serialization may have changed.

        $queue = $workQueue ?? $entry->getWorkQueue();

        $this->storeJob($queue, $entry->getJob(), $delay);
        $this->ph->delete($entry->getHandle());
    }

    public function deleteEntry(QueueEntry $entry): void
    {
        $this->ph->delete($entry->getHandle());
    }

}
