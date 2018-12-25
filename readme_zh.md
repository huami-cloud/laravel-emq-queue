# Laravel Emq Queue

基于 Laravel Queue 的 Xiaomi Elastic Message Queue 扩展

## 安装

- 使用 composer 安装

`composer require huami/aos-laravel-emq-queue`

- 修改 `config/app` 文件，在 `providers` 数组内追加如下内容:

```
'providers' => [
    ...
    Huami\HFEx\EmqQueue\EmqQueueServiceProvider::class,
],
```

## 配置

- 修改 `app/queue.php` 文件，增加 emq 相关配置，示例如下

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
            'tag' => 'test_tag',
        ],
    ]
]
```

- 修改 `.env` 文件，编辑 `QUEUE_DRIVER`, 示例如下

```
...
QUEUE_DRIVER=emq
...
```

## License

Released under the MIT License, see LICENSE.
