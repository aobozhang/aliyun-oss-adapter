<?php

namespace Aobo\OSS;

use Storage;
use League\Flysystem\Filesystem;
use OSS\OssClient;
use Aobo\OSS\AliyunOssAdapter;
use Illuminate\Support\ServiceProvider;

class AliyunOssFilesystemServiceProvider extends ServiceProvider {

    public function boot()
    {
        Storage::extend('oss', function($app, $config)
        {
            $accessId  = $config['access_id'];
            $accessKey = $config['access_key'];
            $endPoint  = $config['endpoint'];
            $bucket    = $config['bucket'];

            $client  = new OssClient($accessId, $accessKey, $endPoint);
            $adapter = new AliyunOssAdapter($client, $bucket);

            return new Filesystem($adapter);

        });

    }

    public function register()
    {
        //
    }

}
