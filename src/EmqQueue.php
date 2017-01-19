<?php

namespace Huami\HFEx\EmqQueue;

use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use EMQ\Client\EMQClient;
use EMQ\Message\SendMessageRequest;
use EMQ\Message\ReceiveMessageRequest;
use EMQ\Queue\GetTagInfoRequest;
use Illuminate\Support\Str;

class EmqQueue extends Queue implements QueueContract
{
    protected $queueClient;

    protected $messageClient;

    protected $default;

    protected $developId;

    public function __construct(EMQClient $queueClient, EMQClient $messageClient, $default, $developId)
    {
        $this->queueClient = $queueClient;
        $this->messageClient = $messageClient;
        $this->default = $default;
        $this->developId = $developId;
    }

    /**
     * Get the size of the queue.
     *
     * @param  string  $queue
     * @return int
     */
    public function size($queue = null)
    {
        $req = [
            'queueName' => $this->getQueue($queue),
        ];
        $tagInfo = $this->queueClient->getTagInfo(new GetTagInfoRequest($req));
        return (int) $tagInfo->tagState->approximateMessageNumber;
    }

    /**
     * Push a new job onto the queue.
     *
     * @param  string  $job
     * @param  mixed   $data
     * @param  string  $queue
     * @return mixed
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue);
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param  string  $payload
     * @param  string  $queue
     * @param  array   $options
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        $req = [
            'queueName' => $this->getQueue($queue),
            'messageBody' => $payload,
        ];
        $response = $this->messageClient->sendMessage(new SendMessageRequest($req));

        return $response->messageID;
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param  \DateTime|int  $delay
     * @param  string  $job
     * @param  mixed   $data
     * @param  string  $queue
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        $req = [
            'queueName' => $this->getQueue($queue),
            'messageBody' => $this->createPayload($job, $data),
            'delaySeconds' => $this->getSeconds($delay),
        ];
        $response = $this->messageClient->sendMessage(new SendMessageRequest($req));

        return $response->messageID;
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param  string  $queue
     * @return \Illuminate\Contracts\Queue\Job|null
     */
    public function pop($queue = null)
    {
        $queue = $this->getQueue($queue);
        $req = [
            'queueName' => $queue,
            'maxReceiveMessageNumber' => 1,
            'maxReceiveMessageWaitSeconds' => 0
        ];
        $response = $this->messageClient->receiveMessage(new ReceiveMessageRequest($req));
        if(count($response) > 0) {
            $message = $response[0];
            return new Jobs\EmqJob($this->container, $this->messageClient, $queue, [
                'messageID' => $message->messageID,
                'receiptHandle' => $message->receiptHandle,
                'messageBody' => $message->messageBody,
                'attributes' => $message->attributes,
            ]);
        }
    }

    protected function getQueue($queue = null)
    {
        $prefix = $this->developId.'/';
        if($queue && Str::startsWith($queue, $prefix)) {
            return $queue;
        }

        return $prefix.($queue ?: $this->default);
    }
}
