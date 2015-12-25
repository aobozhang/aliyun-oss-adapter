
# Use AliyunOss as Laravel Storage;

Aliyun oss driver for Laravel5.0+, also support flysystem adapter.

## inspire by [orzcc/aliyun-oss](https://github.com/orzcc/aliyun-oss)

## Installation

This package can be installed through Composer.
```bash
composer require aobozhang/aliyun-oss-adapter
```

## Usage

This service provider must be registered.

```bash
// config/app.php

'providers' => [
    '...',
    'Aobo\OSS\AliyunOssServiceProvider::class',
];
```


add config:

```bash
// config/filesystem.php.

'oss' => [
    'driver'        => 'oss',
	'access_id'    	=> 'Your oss access id',  //env('OSS_ACCESS_ID,'default string');
	'access_key' 	=> 'Your oss access key',  //env('OSS_ACCESS_KEY,'default string');
	'bucket' 	=> 'Your project bucket on oss',  //env('OSS_BUCKET,'default string');
	'endpoint'    	=> '' // eg. oss-cn-beijing.aliyuncs.com !!without 'http://' in OSS SDK 2.0+
],
```

change default to oss
```bash
'default' => 'oss';
```

```php

use Storage;

//...

Strorage::[everything in doc]
```
[filesystem](https://laravel.com/docs/5.2/filesystem)