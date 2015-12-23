<?php namespace Aobo\OSS;

use Storage;
use OSS\OssClient;
use OSS\Core\OssException;

use League\Flysystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Aobo\OSS\AliyunOssAdapter;

class AliyunOssServiceProvider extends ServiceProvider {

 	public function boot()
    {
        Storage::extend('oss', function($app, $config)
        {
            $client = new OSSClient($config['access_id'], $config['access_key'], $config['endpoint']);
            
            return new Filesystem(new AliyunOssAdapter($client));

        });
    }

    public function register()
    {
        //
    }
}
