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
    /**
     * @var \EMQ\Client\EMQClient
     */
    protected $queueClient;

    /**
     * @var \EMQ\Client\EMQClient
     */
    protected $messageClient;

    protected $default;

    protected $developId;

    protected $tag;

    public function __construct(EMQClient $queueClient, EMQClient $messageClient, $default, $developId, $tag)
    {
        $this->queueClient = $queueClient;
        $this->messageClient = $messageClient;
        $this->default = $default;
        $this->developId = $developId;
        $this->tag = $tag;
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
        if(!is_null($this->tag)) {
            $req['tagName'] = $this->tag;
        }
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
        return $this->pushRaw($this->createPayload($job, $queue ?: $this->default, $data), $queue);
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
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  string  $job
     * @param  mixed   $data
     * @param  string  $queue
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->laterRaw($delay, $this->createPayload($job, $queue ?: $this->default, $data), $queue);
    }

    /**
     * Push a raw job onto the queue after a delay.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  string  $payload
     * @param  string  $queue
     * @return mixed
     */
    public function laterRaw($delay, $payload, $queue = null)
    {
        $req = [
            'queueName' => $this->getQueue($queue),
            'messageBody' => $payload,
            'delaySeconds' => $this->secondsUntil($delay),
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
        if(!is_null($this->tag)) {
            $req['tagName'] = $this->tag;
        }
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

    public function getQueue($queue = null)
    {
        $prefix = $this->developId.'/';
        if($queue && Str::startsWith($queue, $prefix)) {
            return $queue;
        }

        return $prefix.($queue ?: $this->default);
    }

    public function getEmqQueueClient()
    {
        return $this->queueClient;
    }

    public function getEmqMessageClient()
    {
        return $this->messageClient;
    }
}
