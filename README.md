
# Use AliyunOss as Laravel Storage

[![Latest Stable Version](https://poser.pugx.org/aobozhang/aliyun-oss-adapter/v/stable)](https://packagist.org/packages/aobozhang/aliyun-oss-adapter) [![Total Downloads](https://poser.pugx.org/aobozhang/aliyun-oss-adapter/downloads)](https://packagist.org/packages/aobozhang/aliyun-oss-adapter) [![Latest Unstable Version](https://poser.pugx.org/aobozhang/aliyun-oss-adapter/v/unstable)](https://packagist.org/packages/aobozhang/aliyun-oss-adapter) [![License](https://poser.pugx.org/aobozhang/aliyun-oss-adapter/license)](https://packagist.org/packages/aobozhang/aliyun-oss-adapter)

Aliyun oss driver for Laravel5.0+, also support flysystem adapter.

### inspire by [orzcc/aliyun-oss](https://github.com/orzcc/aliyun-oss)  


## Usage

```php

use Storage;

//...

Strorage::[everything in doc]

```
> more infomation 更多信息:
>
> [Laravel wiki](https://laravel.com/docs/5.2/filesystem)
>
> [Aobo's  wiki](https://github.com/aobozhang/aliyun-oss-adapter/wiki)
>


## Installation

This package can be installed through Composer.
```bash
composer require aobozhang/aliyun-oss-adapter
```

## Configuration

This service provider must be registered.

```bash
// config/app.php

'providers' => [
    ...,
    Aobo\OSS\AliyunOssFilesystemServiceProvider::class,
];
```


add config:

```bash
// config/filesystem.php.

        'oss' => [
            'driver'     => 'oss',
            'access_id'  =>  env('OSS_ACCESS_ID','your id'),
            'access_key' =>  env('OSS_ACCESS_KEY','your key'),
            'bucket'     =>  env('OSS_BUCKET','your bucket'),
            'endpoint'   =>  env('OSS_ENDPOINT','your endpoint'),  
            		// eg. oss-cn-beijing.aliyuncs.com !!without 'http://' in OSS SDK 2.0+
        ],

```

change default to oss
```bash
'default' => 'oss';
```
