<?php
require_once __DIR__ . '/vendor/autoload.php';

use Aws\S3\S3Client;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$s3Client = new S3Client([
    'region'      => $_ENV['AWS_S3_REGION_NAME'],
    'version'     => 'latest',
    'credentials' => [
        'key'    => $_ENV['AWS_ACCESS_KEY_ID'],
        'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
        'token'  => $_ENV['AWS_SESSION_TOKEN'] ?? null,  // Solo para AWS Academy
    ],
]);

$bucketName = $_ENV['AWS_STORAGE_BUCKET_NAME'];