<?php

namespace Huami\HFEx\EmqQueue\Test;

use PHPUnit\Framework\TestCase;
use Mockery as m;
use EMQ\Client\EMQClient;
use Huami\HFEx\EmqQueue\EmqQueue;
use Huami\HFEx\EmqQueue\Jobs\EmqJob;
use Illuminate\Container\Container;
use EMQ\Message\SendMessageRequest;
use EMQ\Message\SendMessageResponse;
use EMQ\Message\ReceiveMessageRequest;
use EMQ\Message\ReceiveMessageResponse;
use EMQ\Queue\GetTagInfoRequest;
use EMQ\Queue\GetTagInfoResponse;
use EMQ\Queue\QueueState;
use Carbon\Carbon;

class EmqQueueTest extends TestCase
{
    protected $queueClient;

    protected $messageClient;

    protected $developId = 12345;

    protected $queueName = 'emq_test';

    protected $tagName = 'test_tag';

    protected $queueUrl;

    public function setUp()
    {
        $this->queueClient = m::mock(EMQClient::class);
        $this->messageClient = m::mock(EMQClient::class);
        $this->queueUrl = $this->developId.'/'.$this->queueName;

        $this->mockedJob = 'foo';
        $this->mockedData = ['data'];
        $this->mockedPayload = json_encode(['job' => $this->mockedJob, 'data' => $this->mockedData]);
        $this->mockedDelay = 10;
        $this->mockedMessageId = 'b35ae239-7252-449c-928e-2dd9870d0b15';
        $this->mockedReceiptHandle = 'dd198c246cf210ef149cace99531c75a-451b49822c786799d4c5c5c6dbc03a505b54c71f88da0051a9667da152d17343';

        $this->mockedSendMessageResponseModel = new SendMessageResponse([
            'messageID' => $this->mockedMessageId,
            'bodyMd5' => md5($this->mockedPayload),
            'bodyLength' => strlen($this->mockedPayload),
        ]);

        $this->mockedReceiveMessageResponseModel = [
            new ReceiveMessageResponse([
                'messageID' => md5($this->mockedPayload),
                'receiptHandle' => $this->mockedReceiptHandle,
                'messageBody' => $this->mockedPayload,
                'attributes' => [],
            ]),
        ];

        $this->mockedReceiveEmptyMessageResponseModel = [];

        $this->mockedQueueAttributesResponseModel = new GetTagInfoResponse([
            'tagState' => new QueueState([
                'approximateMessageNumber' => 1,
            ]),
        ]);
    }

    public function testPopProperlyPopsJobOffOfEmq()
    {
        $queue = $this->getMockBuilder(EmqQueue::class)
                    ->setMethods(['getQueue'])
                    ->setConstructorArgs([
                        $this->queueClient,
                        $this->messageClient,
                        $this->queueName,
                        $this->developId,
                        $this->tagName
                    ])
                    ->getMock();

        $queue->setContainer(m::mock(Container::class));
        $queue->expects($this->once())
            ->method('getQueue')
            ->with($this->queueName)
            ->will($this->returnValue($this->queueUrl));

        $this->messageClient->shouldReceive('receiveMessage')
                    ->once()
                    ->with(ReceiveMessageRequest::class)
                    ->andReturn($this->mockedReceiveMessageResponseModel);

        $result = $queue->pop($this->queueName);
        $this->assertInstanceOf(EmqJob::class, $result);
    }

    public function testPopProperlyHandlesEmptyMessage()
    {
        $queue = $this->getMockBuilder(EmqQueue::class)
                    ->setMethods(['getQueue'])
                    ->setConstructorArgs([
                        $this->queueClient,
                        $this->messageClient,
                        $this->queueName,
                        $this->developId,
                        $this->tagName
                    ])
                    ->getMock();
        $queue->setContainer(m::mock(Container::class));

        $queue->expects($this->once())
            ->method('getQueue')
            ->with($this->queueName)
            ->will($this->returnValue($this->queueUrl));

        $this->messageClient->shouldReceive('receiveMessage')
                    ->once()
                    ->with(ReceiveMessageRequest::class)
                    ->andReturn($this->mockedReceiveEmptyMessageResponseModel);

        $result = $queue->pop($this->queueName);
        $this->assertNull($result);
    }

    public function testDelayedPushWithDateTimeProperlyPushesJobOntoEmq()
    {
        $now = Carbon::now();
        $queue = $this->getMockBuilder(EmqQueue::class)
                    ->setMethods(['createPayload', 'secondsUntil', 'getQueue'])
                    ->setConstructorArgs([
                        $this->queueClient,
                        $this->messageClient,
                        $this->queueName,
                        $this->developId,
                        $this->tagName
                    ])
                    ->getMock();

        $queue->expects($this->once())
            ->method('createPayload')
            ->with($this->mockedJob, $this->queueName, $this->mockedData)
            ->will($this->returnValue($this->mockedPayload));

        $queue->expects($this->once())
            ->method('secondsUntil')
            ->with($now)
            ->will($this->returnValue(5));

        $queue->expects($this->once())
            ->method('getQueue')
            ->with($this->queueName)
            ->will($this->returnValue($this->queueUrl));

        $this->messageClient->shouldReceive('sendMessage')
            ->once()
            ->with(SendMessageRequest::class)
            ->andReturn($this->mockedSendMessageResponseModel);

        $id = $queue->later($now->addSeconds(5), $this->mockedJob, $this->mockedData, $this->queueName);
        $this->assertEquals($this->mockedMessageId, $id);
    }

    public function testDelayedPushProperlyPushesJobOntoEmq()
    {
        $queue = $this->getMockBuilder(EmqQueue::class)
                    ->setMethods(['createPayload', 'secondsUntil', 'getQueue'])
                    ->setConstructorArgs([
                        $this->queueClient,
                        $this->messageClient,
                        $this->queueName,
                        $this->developId,
                        $this->tagName
                    ])
                    ->getMock();

        $queue->expects($this->once())
            ->method('createPayload')
            ->with($this->mockedJob, $this->queueName, $this->mockedData)
            ->will($this->returnValue($this->mockedPayload));

        $queue->expects($this->once())
            ->method('secondsUntil')
            ->with($this->mockedDelay)
            ->will($this->returnValue($this->mockedDelay));

        $queue->expects($this->once())
            ->method('getQueue')
            ->with($this->queueName)
            ->will($this->returnValue($this->queueUrl));

        $this->messageClient->shouldReceive('sendMessage')
            ->once()
            ->with(SendMessageRequest::class)
            ->andReturn($this->mockedSendMessageResponseModel);

        $id = $queue->later($this->mockedDelay, $this->mockedJob, $this->mockedData, $this->queueName);
        $this->assertEquals($this->mockedMessageId, $id);
    }

    public function testPushProperlyPushesJobOntoEmq()
    {
        $queue = $this->getMockBuilder(EmqQueue::class)
                    ->setMethods(['createPayload', 'getQueue'])
                    ->setConstructorArgs([
                        $this->queueClient,
                        $this->messageClient,
                        $this->queueName,
                        $this->developId,
                        $this->tagName
                    ])
                    ->getMock();

        $queue->expects($this->once())
            ->method('createPayload')
            ->with($this->mockedJob, $this->queueName, $this->mockedData)
            ->will($this->returnValue($this->mockedPayload));

        $queue->expects($this->once())
            ->method('getQueue')
            ->with($this->queueName)
            ->will($this->returnValue($this->queueUrl));

        $this->messageClient->shouldReceive('sendMessage')
            ->once()
            ->with(SendMessageRequest::class)
            ->andReturn($this->mockedSendMessageResponseModel);

        $id = $queue->push($this->mockedJob, $this->mockedData, $this->queueName);
        $this->assertEquals($this->mockedMessageId, $id);
    }

    public function testSizeProperlyReadsEmqQueueSize()
    {
        $queue = $this->getMockBuilder(EmqQueue::class)
                    ->setMethods(['getQueue'])
                    ->setConstructorArgs([
                        $this->queueClient,
                        $this->messageClient,
                        $this->queueName,
                        $this->developId,
                        $this->tagName
                    ])
                    ->getMock();

        $queue->expects($this->once())
            ->method('getQueue')
            ->with($this->queueName)
            ->will($this->returnValue($this->queueUrl));

        $this->queueClient->shouldReceive('getTagInfo')
            ->once()
            ->with(GetTagInfoRequest::class)
            ->andReturn($this->mockedQueueAttributesResponseModel);

        $size = $queue->size($this->queueName);
        $this->assertEquals($size, 1);
    }

    public function testGetQueueProperlyResolvesUrlWithPrefix()
    {
        $queue = new EmqQueue($this->queueClient, $this->messageClient, $this->queueName, $this->developId, $this->tagName);
        $this->assertEquals($this->queueUrl, $queue->getQueue(null));
        $queueUrl = $this->developId.'/test';
        $this->assertEquals($queueUrl, $queue->getQueue('test'));
    }

    public function testGetQueueProperlyResolvesUrlWithoutPrefix()
    {
        $queue = new EmqQueue($this->queueClient, $this->messageClient, $this->queueName, $this->developId, $this->tagName);
        $this->assertEquals($this->queueUrl, $queue->getQueue(null));
        $queueUrl = $this->developId.'/test';
        $this->assertEquals($queueUrl, $queue->getQueue($queueUrl));
    }

    public function tearDown()
    {
        m::close();
    }
}
