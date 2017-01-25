# Laravel Emq Queue

The Expanded Queue Driver For Xiaomi Elastic Message Queue in Laravel 5

[中文版](./readme_zh.md)

## Installation

- composer

`composer require huami/aos-laravel-emq-queue`

- check your `config/app` file, append `Huami\HFEx\EmqQueue\EmqQueueServiceProvider::class` to `providers`

```
'providers' => [
    ...
    Huami\HFEx\EmqQueue\EmqQueueServiceProvider::class,
],
```

## Configuration

- add emq settings in `app/queue.php` file

```
<?php

return [
    ...
    'connections' => [
        ...
        'emq' => [
            'driver' => 'emq',
            'key' => 'your-public-key',
            'secret' => 'your-secret-key',
            'host' => 'https://awsbj0.emq.api.xiaomi.com',
            'queue' => 'your-queue-name',
            'develop_id' => 'your-queue-developer-id',
        ],
    ]
]
```

- change `QUEUE_DRIVER` to emq in `.env`

```
...
QUEUE_DRIVER=emq
...
```

## License

Released under the MIT License, see LICENSE.
