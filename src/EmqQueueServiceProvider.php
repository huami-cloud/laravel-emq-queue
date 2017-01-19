<?php

namespace Huami\HFEx\EmqQueue;

use Illuminate\Support\ServiceProvider;

class EmqQueueServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->afterResolving('queue', function ($queue) {
            $queue->addConnector('emq', function () {
                return new Connectors\EmqConnector();
            });
        });
    }
}
