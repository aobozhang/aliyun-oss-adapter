```title
Service Provider NOT READY YET - Aliyun OSS adapter Done 
```
```
inspire by [orzcc/aliyun-oss](https://github.com/orzcc/aliyun-oss)
```

# Aliyun OSS adapter
Aliyun oss for Laravel5.1, also support flysystem adapter.

## Installation

This package can be installed through Composer.
```bash
composer require aobozhang/aliyun-oss-adapter:@dev
```

## Usage

```php
use Aobo\OSS\AliyunOssAdapter;
use League\Flysystem\Filesystem;
use OSS\OssClient;

$client     = new OssClient( $accessId, $accessKey, $endPoint);
$adapter    = new AliyunOssAdapter($client, $bucket );
$filesystem = new Filesystem($adapter);

$filesystem-><every thing in filesystem>
```
