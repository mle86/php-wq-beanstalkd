# WQ-Beanstalkd  (`mle86/wq-beanstalkd`)

This package contains the PHP class
`mle86\WQ\WorkServerAdapter\`**`BeanstalkdWorkServer`**.

It supplements the
[**mle86/wq**](https://github.com/mle86/php-wq) package
by implementing its `WorkServerAdapter` interface.

It connects to a Beanstalkd server
using the [pda/pheanstalk](https://github.com/pda/pheanstalk) package
by Paul Annesley.


# Version and Compatibility

This is
**version 1.0.0**
of `mle86/wq-beanstalkd`.

It was developed for
version 1.0.0
of `mle86/wq`
and should be compatible
with all of its future 1.x versions as well.


# Installation

```
$ composer require mle86/wq-beanstalkd
```


# Class reference

`class mle86\WQ\WorkServerAdapter\`**`BeanstalkdWorkServer`** `implements WorkServerAdapter`

`getNextQueueEntry()` uses the `RESERVE` command,
`buryEntry()` uses the `BURY` command,
`storeJob()` and `requeueEntry()` use the `PUT` command,
and `deleteEntry()` uses the `DELETE` command.

*Work Queues* are Beanstalkd's “tubes”.

* <code>public function <b>__construct</b> (Pheanstalk $pheanstalk)<code>  
    Constructor.
    Takes an already-configured `Pheanstalk` instance to work with.
    Does not attempt to establish a connection itself –
    use the `connect()` factory method for that instead.
* <code>public static function <b>connect</b> (string $host = "localhost", int $port = PheanstalkInterface::DEFAULT_PORT, int $connectTimeout = null)<code>  
    Factory method.
    See [Pheanstalk::__construct](https://github.com/pda/pheanstalk/blob/master/src/Pheanstalk.php)
    for the parameter descriptions.

Interface methods
which are documented in the [`WorkServerAdapter`](https://github.com/mle86/php-wq#workserveradapter-interface) interface:

* <code>public function <b>storeJob</b> (string $workQueue, Job $job, int $delay = 0)</code>
* <code>public function <b>getNextQueueEntry</b> ($workQueue, int $timeout = DEFAULT_TIMEOUT) : ?QueueEntry</code>
* <code>public function <b>buryEntry</b> (QueueEntry $entry)</code>
* <code>public function <b>requeueEntry</b> (QueueEntry $entry, int $delay, string $workQueue = null)</code>
* <code>public function <b>deleteEntry</b> (QueueEntry $entry)</code>


# Usage example

```php
<?php
use mle86\WQ\WorkServerAdapter\BeanstalkdWorkServer;
use mle86\WQ\WorkProcessor;
use mle86\WQ\Job\Job;

$processor = new WorkProcessor( BeanstalkdWorkServer::connect("localhost") );

while (true) {
    $processor->executeNextJob("mail", function(Job $job) {
        $job->...;
    });
}
```

This executes all jobs available in the local Beanstalkd server's “`mail`” tube, forever.
It will however abort if one of the jobs throws an exception –
you might want to add a logging try-catch block around the `executeNextJob()` call
as shown in [WQ's “Minimal Example”](https://github.com/mle86/php-wq#minimal-example).

