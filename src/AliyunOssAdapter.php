<?php namespace Aobo\OSS;

use OSS\OssClient;
use OSS\Core\OssException;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\NotSupportingVisibilityTrait;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\Util;

class AliyunOssAdapter extends AbstractAdapter
{
    /**
     * @var array
     */
    protected static $resultMap = [
    'Body'           => 'raw_contents',
    'Content-Length' => 'size',
    'ContentType'    => 'mimetype',
    'Size'           => 'size',
    'StorageClass'   => 'storage_class',
    ];

    /**
     * @var array
     */
    protected static $metaOptions = [
    'CacheControl',
    'Expires',
    'ServerSideEncryption',
    'Metadata',
    'ACL',
    'ContentType',
    'ContentDisposition',
    'ContentLanguage',
    'ContentEncoding',
    ];

    protected static $metaMap = [
    'CacheControl'         => 'Cache-Control',
    'Expires'              => 'Expires',
    'ServerSideEncryption' => 'x-oss-server-side-encryption',
    'Metadata'             => 'x-oss-metadata-directive',
    'ACL'                  => 'x-oss-object-acl',
    'ContentType'          => 'Content-Type',
    'ContentDisposition'   => 'Content-Disposition',
    'ContentLanguage'      => 'response-content-language',
    'ContentEncoding'      => 'Content-Encoding',
    ];


    /**
     * @var string bucket name
     */
    protected $bucket;

    /**
     * @var Aliyun Oss Client
     */
    protected $client;

    /**
     * @var array default options[
     *            Multipart=128 Mb - After what size should multipart be used
     *            ]
     */
    protected $options = [
    'Multipart'   => 128
    ];

    /**
     * Constructor.
     *
     * @param OssClient     $client
     * @param string        $bucket
     * @param string        $prefix
     * @param array         $options
     */
    public function __construct(
        OssClient $client,
        $bucket,
        $prefix = null,
        array $options = []
        ) {
        $this->client  = $client;
        $this->bucket  = $bucket;
        $this->setPathPrefix($prefix);
        $this->options = array_merge($this->options, $options);
    }

    /**
     * Get the OssClient bucket.
     *
     * @return string
     */
    public function getBucket()
    {
        return $this->bucket;
    }

    /**
     * Get the OssClient instance.
     *
     * @return OssClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * {@inheritdoc}
     */
    public function has($path)
    {
        $location = $this->applyPathPrefix($path);

        return $this->client->doesObjectExist($this->bucket, $location);
    }

    /**
     * {@inheritdoc}
     */
    public function write($path, $contents, Config $config)
    {
        $bucket  = $this->bucket;
        $options = $this->getOptions($this->options, $config);

        try{
            $this->client->putObject($bucket, $path, $contents, $options);
        } catch(OssException $e) {
            printf(__FUNCTION__ . ": FAILED\n");
            printf($e->getMessage() . "\n");
            return;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function writeStream($path, $resource, Config $config)
    {
        $bucket  = $this->bucket;
        $options = $this->getOptions($this->options, $config);

        $multipartLimit = $this->mbToBytes($options['Multipart']);
        $size = Util::getStreamSize($resource);
        $contents = fread($resource, $size);

        if ($size > $multipartLimit) {
            printf(__FUNCTION__ . ": OVER LIMIT\n");
            printf($e->getMessage() . "\n");
            return;
        } else {
            try{
                $this->client->putObject($bucket, $path, $contents, $options);
            } catch(OssException $e) {
                printf(__FUNCTION__ . ": FAILED\n");
                printf($e->getMessage() . "\n");
                return;
            }
        }

    }

    /**
     * {@inheritdoc}
     */
    public function update($path, $contents, Config $config)
    {
        if (! $config->has('visibility') && ! $config->has('ACL')) {
            $config->set(static::$metaMap['ACL'], $this->getObjectACL($path));
        }
        // $this->delete($path);
        return $this->write($path, $contents, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function updateStream($path, $resource, Config $config)
    {
        if (! $config->has('visibility') && ! $config->has('ACL')) {
            $config->set('ACL', $this->getObjectACL($path));
        }
        // $this->delete($path);
        return $this->writeStream($path, $resource, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function read($path)
    {
        $result = $this->readObject($path);
        $result['contents'] = (string) $result['raw_contents'];
        unset($result['raw_contents']);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function readStream($path)
    {
        $result = $this->readObject($path);
        $result['stream'] = $result['raw_contents'];
        rewind($result['stream']);
        // Ensure the EntityBody object destruction doesn't close the stream
        $result['raw_contents']->detachStream();
        unset($result['raw_contents']);

        return $result;
    }

    /**
     * Read an object from the OssClient.
     *
     * @param string $path
     *
     * @return array
     */
    protected function readObject($path)
    {
        $options = [];
        $bucket = $this->bucket;
        $object = $path;

        $result = $this->client->getObject($bucket, $object, $options);

        return $this->normalizeResponse($result);
    }

    /**
     * {@inheritdoc}
     */
    public function rename($path, $newpath)
    {
        $this->copy($path, $newpath);
        $this->delete($path);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function copy($path, $newpath, $options = NULL)
    {
        $bucket = $this->bucket;

        try{
            $this->client->copyObject($bucket, $path, $bucket, $newpath, $options = NULL);
        } catch (OssException $e) {
            printf(__FUNCTION__ . ": FAILED\n");
            printf($e->getMessage() . "\n");
            return;
        }

        return true;
    }

    /**
     * 修改Object Meta
     * 利用copyObject接口的特性：当目的object和源object完全相同时，表示修改object的meta信息
     *
     * @param OssClient $ossClient OssClient实例
     * @param string $bucket 存储空间名称
     * @return null
     */
    public function modifyMetaForObject($path, $options = NULL)
    {
        $bucket = $this->bucket;
        $fromBucket = $toBucket = $bucket;
        $fromObject = $toObject = $path;

        $copyOptions = $this->getOptions($options);
        try {
            $this->client->copyObject($fromBucket, $fromObject, $toBucket, $toObject, $copyOptions);
        } catch (OssException $e) {
            printf(__FUNCTION__ . ": FAILED\n");
            printf($e->getMessage() . "\n");
            return;
        }
        // print(__FUNCTION__ . ": OK" . "\n");
        return true;
    }


    /**
     * {@inheritdoc}
     */
    public function delete($path)
    {
        $bucket = $this->bucket;
        $object = $path;

        try{
            $this->client->deleteObject($bucket, $object);
        }catch (OssException $e) {
            printf(__FUNCTION__ . ": FAILED\n");
            printf($e->getMessage() . "\n");
            return;
        }

        return ! $this->has($path);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDir($path)
    {
        $prefix = rtrim($this->applyPathPrefix($path), '/').'/';
        $bucket = $this->bucket;
        $dir = $this->listDirObjects($path, true);

        if(count($dir['objects']) > 0 ){

            foreach($dir['objects'] as $object)
            {
                $objects[] = $object['Key'];
            }

            try {
                $this->client->deleteObjects($bucket, $objects);
            } catch (OssException $e) {
                printf(__FUNCTION__ . ": FAILED\n");
                printf($e->getMessage() . "\n");
                return;
            }

        }

        try {
            $this->client->deleteObject($bucket, $path);
        } catch (OssException $e) {
            printf(__FUNCTION__ . ": FAILED\n");
            printf($e->getMessage() . "\n");
            return;
        }

        return true;
    }


    /**
     * 列举文件夹内文件列表；可递归获取子文件夹；
     */
    public function listDirObjects($dirname = '', $recursive =  false)
    {
        $bucket = $this->bucket;

        $delimiter = '/';
        $nextMarker = '';
        $maxkeys = 1000;

        $options = array(
            'delimiter' => $delimiter,
            'prefix'    => $dirname,
            'max-keys'  => $maxkeys,
            'marker'    => $nextMarker,
            );

        try {
            $listObjectInfo = $this->client->listObjects($bucket, $options);
        } catch (OssException $e) {
            printf(__FUNCTION__ . ": FAILED\n");
            printf($e->getMessage() . "\n");
            return;
        }
        // var_dump($listObjectInfo);
        $objectList = $listObjectInfo->getObjectList(); // 文件列表
        $prefixList = $listObjectInfo->getPrefixList(); // 目录列表

        if (!empty($objectList)) {
            foreach ($objectList as $objectInfo) {

                $object['Prefix']       = $dirname;
                $object['Key']          = $objectInfo->getKey();
                $object['LastModified'] = $objectInfo->getLastModified();
                $object['eTag']         = $objectInfo->getETag();
                $object['Type']         = $objectInfo->getType();
                $object['Size']         = $objectInfo->getSize();
                $object['StorageClass'] = $objectInfo->getStorageClass();

                $dir['objects'][] = $object;
            }
        }else{
            $dir["objects"] = [];
        }

        if (!empty($prefixList)) {
            foreach ($prefixList as $prefixInfo) {
                $dir['prefix'][] = $prefixInfo->getPrefix();
            }

        }else{
            $dir['prefix'] = [];
        }


        if($recursive){

            foreach( $dir['prefix'] as $pfix){
                $next = [];
                $next  =  $this->listDirObjects($pfix , $recursive);

                $dir["objects"] = array_merge($dir['objects'], $next["objects"]);
            }

        }

        return $dir;
    }

    /**
     * {@inheritdoc}
     */
    public function createDir($path, Config $config)
    {

        $bucket = $this->bucket;

        try {
            $this->client->createObjectDir($bucket, $path);
        } catch (OssException $e) {
            printf(__FUNCTION__ . ": FAILED\n");
            printf($e->getMessage() . "\n");
            return;
        }

        return ['path' => $path, 'type' => 'dir'];
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($path)
    {
        $bucket = $this->bucket;
        $object = $path;

        try {
            $objectMeta = $this->client->getObjectMeta($bucket, $object);
        } catch (OssException $e) {
            printf(__FUNCTION__ . ": FAILED\n");
            printf($e->getMessage() . "\n");
            return;
        }

        return $objectMeta;
    }

    /**
     * {@inheritdoc}
     */
    public function getMimetype($path)
    {
        $object = $this->getMetadata($path);
        $object['mimetype'] = $object['content-type'];
        return $object;
    }

    /**
     * {@inheritdoc}
     */
    public function getSize($path)
    {
        $object = $this->getMetadata($path);
        $object['size'] = $object['content-length'];
        return $object;
    }

    /**
     * {@inheritdoc}
     */
    public function getTimestamp($path)
    {
        $object = $this->getMetadata($path);
        $object['timestamp'] = $object['date'];
        return $object;
    }

    /**
     * {@inheritdoc}
     */
    public function getVisibility($path)
    {
        $bucket = $this->bucket;

        try {
            $res['visibility'] = $this->client->getBucketAcl($bucket);
        } catch (OssException $e) {
            printf(__FUNCTION__ . ": FAILED\n");
            printf($e->getMessage() . "\n");
            return;
        }
        return $res;
    }

    /**
     * The the ACL visibility.
     *
     * @param string $path
     *
     * @return string
     */
    protected function getObjectACL($path)
    {
        $metadata = $this->getVisibility($path);

        return $metadata['visibility'] === AdapterInterface::VISIBILITY_PUBLIC ? 'public-read' : 'private';
    }

    /**
     * {@inheritdoc}
     */
    public function setVisibility($path, $visibility)
    {
        $bucket = $this->bucket;
        $acl = ( $visibility === AdapterInterface::VISIBILITY_PUBLIC ) ? 'public-read' : 'private';

        $this->client->putBucketAcl($bucket,$acl);

        return compact('visibility');
    }

    /**
     * {@inheritdoc}
     */
    public function listContents($dirname = '', $recursive = false)
    {

        $bucket = $this->bucket;
        $dir = $this->listDirObjects($dirname, true);
        $contents = $dir["objects"];

        $result = array_map([$this, 'normalizeResponseOri'], $contents);
        $result = array_filter($result, function ($value) {
            return $value['path'] !== false;
        });

        return Util::emulateDirectories($result);
    }

    /**
     * Normalize a result from AWS.
     *
     * @param array  $object
     * @param string $path
     *
     * @return array file metadata
     */
    protected function normalizeResponseOri(array $object, $path = null)
    {
        $result = ['path' => $path ?: $this->removePathPrefix(isset($object['Key']) ? $object['Key'] : $object['Prefix'])];
        $result['dirname'] = Util::dirname($result['path']);

        if (isset($object['LastModified'])) {
            $result['timestamp'] = strtotime($object['LastModified']);
        }

        if (substr($result['path'], -1) === '/') {
            $result['type'] = 'dir';
            $result['path'] = rtrim($result['path'], '/');

            return $result;
        }

        $result = array_merge($result, Util::map($object, static::$resultMap), ['type' => 'file']);

        return $result;
    }

    /**
     * Normalize a result from AWS.
     *
     * @param array  $object
     * @param string $path
     *
     * @return array file metadata
     */
    protected function normalizeResponse($content)
    {


        $result['raw_contents'] = $content;
        $result = array_merge($result, ['type' => 'file']);

        return $result;
    }

    /**
     * Get options for a OSS call. done
     *
     * @param array  $options
     *
     * @return array OSS options
     */
    protected function getOptions(array $options = [], Config $config = null)
    {
        $options = array_merge($this->options, $options);

        if ($config) {
            $options = array_merge($options, $this->getOptionsFromConfig($config));
        }

        return array(OssClient::OSS_HEADERS => $options);
    }

    /**
     * Retrieve options from a Config instance. done
     *
     * @param Config $config
     *
     * @return array
     */
    protected function getOptionsFromConfig(Config $config)
    {
        $options = [];

        foreach (static::$metaOptions as $option) {
            if (! $config->has($option)) {
                continue;
            }
            $options[static::$metaMap[$option]] = $config->get($option);
        }

        if ($visibility = $config->get('visibility')) {
            // For local reference
            // $options['visibility'] = $visibility;
            // For external reference
            $options['x-oss-object-acl'] = $visibility === AdapterInterface::VISIBILITY_PUBLIC ? 'public-read' : 'private';
        }

        if ($mimetype = $config->get('mimetype')) {
            // For local reference
            // $options['mimetype'] = $mimetype;
            // For external reference
            $options['Content-Type'] = $mimetype;
        }

        return $options;
    }

    /**
     * Convert megabytes to bytes.
     *
     * @param int $megabytes
     *
     * @return int
     */
    protected function mbToBytes($megabytes)
    {
        return $megabytes * 1024 * 1024;
    }

}
