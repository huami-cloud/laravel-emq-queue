<?php

namespace Huami\HFEx\EmqQueue\Jobs;

use EMQ\Client\EMQClient;
use Illuminate\Container\Container;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Contracts\Queue\Job as JobContract;
use EMQ\Message\DeleteMessageRequest;
use EMQ\Message\ChangeMessageVisibilityRequest;

class EmqJob extends Job implements JobContract
{
    protected $messageClient;

    protected $job;

    /**
     * Create a new job instance.
     *
     * @param  \Illuminate\Container\Container  $container
     * @param  \EMQ\Client\EMQClient  $messageClient
     * @param  string  $queue
     * @param  array   $job
     * @return void
     */
    public function __construct(Container $container,
                                EMQClient $messageClient,
                                $queue,
                                array $job)
    {
        $this->messageClient = $messageClient;
        $this->job = $job;
        $this->queue = $queue;
        $this->container = $container;
    }

    /**
     * Get the raw body string for the job.
     *
     * @return string
     */
    public function getRawBody()
    {
        return $this->job['messageBody'];
    }

    /**
     * Delete the job from the queue.
     *
     * @return void
     */
    public function delete()
    {
        parent::delete();

        $req = [
            'queueName' => $this->queue,
            'receiptHandle' => $this->job['receiptHandle'],
        ];
        $this->messageClient->deleteMessage(new DeleteMessageRequest($req));
    }

    /**
     * Release the job back into the queue.
     *
     * @param  int   $delay
     * @return void
     */
    public function release($delay = 0)
    {
        parent::release($delay);

        $req = [
            'queueName' => $this->queue,
            'receiptHandle' => $this->job['receiptHandle'],
            'invisibilitySeconds' => $delay,
        ];
        $this->messageClient->changeMessageVisibilitySeconds(new ChangeMessageVisibilityRequest($req));
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts()
    {
        return (int) $this->job['attributes']['receiveCount'];
    }

    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId()
    {
        return $this->job['messageID'];
    }

    public function getEmqJob()
    {
        return $this->job;
    }
}
