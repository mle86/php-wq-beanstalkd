### `BeanstalkdWorkServer` class

`class mle86\WQ\WorkServerAdapter\`**`BeanstalkdWorkServer`** `implements WorkServerAdapter`

It connects to a Beanstalkd server
using the [pda/pheanstalk](https://github.com/pda/pheanstalk) package
by Paul Annesley.

`getNextQueueEntry()` uses the `RESERVE` command,
`buryEntry()` uses the `BURY` command,
`storeJob()` and `requeueEntry()` use the `PUT` command,
and `deleteEntry()` uses the `DELETE` command.

*Work Queues* are Beanstalkd's “tubes”.

* `public function` **`__construct`** `(string $host = "localhost", int $port = PheanstalkInterface::DEFAULT_PORT, int $connectTimeout = null)`  
    See [Pheanstalk::__construct](https://github.com/pda/pheanstalk/blob/master/src/Pheanstalk.php).

