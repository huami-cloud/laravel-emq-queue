<?php

namespace Huami\HFEx\EmqQueue\Connectors;

use Illuminate\Queue\Connectors\ConnectorInterface;
use Huami\HFEx\EmqQueue\EmqQueue;
use RPC\Auth\Credential;
use RPC\Auth\UserType;
use EMQ\Client\EMQClientFactory;

class EmqConnector implements ConnectorInterface
{
    public function connect(array $config)
    {
        $config = $this->getDefaultConfiguration($config);

        $credential = new Credential([
            'type' => UserType::APP_SECRET,
            'secretKeyId' => $config['key'],
            'secretKey' => $config['secret']
        ]);
        $clientFactory = new EMQClientFactory($credential);

        $queueClient = $clientFactory->newQueueClient(
                                    $config['host'],
                                    $config['http']['timeout'],
                                    $config['http']['connect_timeout']
                                );

        $messageClient = $clientFactory->newMessageClient(
                                    $config['host'],
                                    $config['http']['timeout'],
                                    $config['http']['connect_timeout']
                                );

        return new EmqQueue(
            $queueClient,
            $messageClient,
            $config['queue'],
            $config['develop_id'],
            $config['tag'] ?? null
        );
    }

    /**
     * Get the default configuration for EMQ.
     *
     * @param  array  $config
     * @return array
     */
    protected function getDefaultConfiguration(array $config)
    {
        return array_merge([
            'http' => [
                'timeout' => 60000/* ms */,
                'connect_timeout' => 30000/* ms */,
            ],
        ], $config);
    }
}
