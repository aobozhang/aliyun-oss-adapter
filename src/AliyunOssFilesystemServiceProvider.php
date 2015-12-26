<?php

namespace Aobo\OSS;

use Storage;
use League\Flysystem\Filesystem;
use OSS\OssClient;
use Aobo\OSS\AliyunOssAdapter;
use Illuminate\Support\ServiceProvider;

class AliyunOssFilesystemServiceProvider extends ServiceProvider {

    /**
     * 服务提供者加是否延迟加载.
     *
     * @var bool
     */
    protected $defer = true;

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

    /**
     * 获取由提供者提供的服务.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }

}
