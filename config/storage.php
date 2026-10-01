<?php

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

require_once __DIR__ . '/bootstrap.php';

function ultrack_storage_base_dir(string $bucket): string
{
    $base = __DIR__ . '/../old/' . trim($bucket, '/');
    if (!is_dir($base)) {
        mkdir($base, 0777, true);
    }

    return $base;
}

function ultrack_storage_supported_mime(string $mime): bool
{
    $allowed = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    return in_array($mime, $allowed, true);
}

function ultrack_storage_prepare_image(string $sourcePath): string
{
    $info = getimagesize($sourcePath);
    if ($info === false) {
        throw new RuntimeException('Fichier image invalide.');
    }

    $mime = $info['mime'];
    if (!ultrack_storage_supported_mime($mime)) {
        throw new RuntimeException('Type MIME non autorisé.');
    }

    $image = null;
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $image = imagecreatefrompng($sourcePath);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($sourcePath);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($sourcePath);
            break;
    }

    if ($image === false || $image === null) {
        throw new RuntimeException('Impossible de décoder l’image.');
    }

    $width = imagesx($image);
    $height = imagesy($image);
    $max = 1600;
    if ($width > $max || $height > $max) {
        $ratio = min($max / $width, $max / $height);
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        $image = $resized;
    }

    $tmp = tempnam(sys_get_temp_dir(), 'ultrack_');
    if ($tmp === false) {
        throw new RuntimeException('Impossible de créer un fichier temporaire.');
    }

    switch ($mime) {
        case 'image/jpeg':
            imagejpeg($image, $tmp, 75);
            break;
        case 'image/png':
            imagepng($image, $tmp, 8);
            break;
        case 'image/webp':
            imagewebp($image, $tmp, 75);
            break;
        case 'image/gif':
            imagegif($image, $tmp);
            break;
    }

    imagedestroy($image);

    return $tmp;
}

function ultrack_storage_s3_enabled(): bool
{
    return !empty(ultrack_env('MINIO_ENDPOINT', ''))
        && !empty(ultrack_env('MINIO_ACCESS_KEY', ''))
        && !empty(ultrack_env('MINIO_SECRET_KEY', ''))
        && class_exists('Aws\\S3\\S3Client');
}

function ultrack_storage_put(string $bucket, array $file, string $prefix = 'file'): string
{
    $tmpName = $file['tmp_name'] ?? null;
    if (!is_string($tmpName) || (!is_uploaded_file($tmpName) && !is_file($tmpName))) {
        throw new InvalidArgumentException('Fichier upload invalide.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpName);
    if (!ultrack_storage_supported_mime($mime)) {
        throw new InvalidArgumentException('Type de fichier non pris en charge.');
    }

    $source = ultrack_storage_prepare_image($tmpName);
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        default => 'bin',
    };

    $filename = sprintf('%s_%s.%s', $prefix, uniqid('', true), $ext);

    if (ultrack_storage_s3_enabled()) {
        $client = new Aws\S3\S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'endpoint' => ultrack_env('MINIO_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => ultrack_env('MINIO_ACCESS_KEY'),
                'secret' => ultrack_env('MINIO_SECRET_KEY'),
            ],
        ]);

        $objectKey = $bucket . '/' . $filename;
        $client->putObject([
            'Bucket' => ultrack_env('MINIO_BUCKET', $bucket),
            'Key' => $objectKey,
            'SourceFile' => $source,
            'ContentType' => $mime,
            'ACL' => 'private',
        ]);

        if (is_file($source) && $source !== $tmpName) {
            @unlink($source);
        }

        return $filename;
    }

    $dir = ultrack_storage_base_dir($bucket);
    $target = $dir . DIRECTORY_SEPARATOR . $filename;

    if (!@copy($source, $target) && !@rename($source, $target)) {
        unlink($source);
        throw new RuntimeException('Impossible d’enregistrer l’image sur le stockage.');
    }

    if (is_file($source) && $source !== $target) {
        @unlink($source);
    }

    return $filename;
}

function ultrack_storage_delete(string $bucket, string $filename): bool
{
    if ($filename === '' || $filename === null) {
        return true;
    }

    $path = ultrack_storage_base_dir($bucket) . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($path)) {
        return true;
    }

    return unlink($path);
}

function ultrack_storage_url(string $bucket, string $filename): string
{
    if (ultrack_storage_s3_enabled()) {
        $client = new Aws\S3\S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'endpoint' => ultrack_env('MINIO_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => ultrack_env('MINIO_ACCESS_KEY'),
                'secret' => ultrack_env('MINIO_SECRET_KEY'),
            ],
        ]);

        try {
            return $client->getObjectUrl(ultrack_env('MINIO_BUCKET', $bucket), ltrim($bucket . '/' . $filename, '/'), time() + 300);
        } catch (Throwable $e) {
            // fall back to local path when no public URL is configured
        }
    }

    $base = rtrim(ultrack_env('APP_URL', ''), '/');
    if ($base === '') {
        return '/old/' . trim($bucket, '/') . '/' . rawurlencode($filename);
    }

    return $base . '/old/' . trim($bucket, '/') . '/' . rawurlencode($filename);
}

function storage_exists(string $bucket, string $filename): bool
{
    if (ultrack_storage_s3_enabled()) {
        $client = new Aws\S3\S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'endpoint' => ultrack_env('MINIO_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => ultrack_env('MINIO_ACCESS_KEY'),
                'secret' => ultrack_env('MINIO_SECRET_KEY'),
            ],
        ]);

        try {
            return $client->doesObjectExist(ultrack_env('MINIO_BUCKET', $bucket), ltrim($bucket . '/' . $filename, '/'));
        } catch (Throwable $e) {
            return false;
        }
    }

    return file_exists(ultrack_storage_base_dir($bucket) . DIRECTORY_SEPARATOR . $filename);
}

function storage_get(string $bucket, string $filename): ?string
{
    if (ultrack_storage_s3_enabled()) {
        $client = new Aws\S3\S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'endpoint' => ultrack_env('MINIO_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => ultrack_env('MINIO_ACCESS_KEY'),
                'secret' => ultrack_env('MINIO_SECRET_KEY'),
            ],
        ]);

        try {
            $result = $client->getObject([
                'Bucket' => ultrack_env('MINIO_BUCKET', $bucket),
                'Key' => ltrim($bucket . '/' . $filename, '/'),
            ]);

            if (isset($result['Body'])) {
                $body = $result['Body'];
                return (string) $body;
            }
        } catch (Throwable $e) {
            return null;
        }

        return null;
    }

    $path = ultrack_storage_base_dir($bucket) . DIRECTORY_SEPARATOR . $filename;
    return is_file($path) ? file_get_contents($path) : null;
}

function storage_delete(string $bucket, string $filename): bool
{
    if (ultrack_storage_s3_enabled()) {
        $client = new Aws\S3\S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'endpoint' => ultrack_env('MINIO_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => ultrack_env('MINIO_ACCESS_KEY'),
                'secret' => ultrack_env('MINIO_SECRET_KEY'),
            ],
        ]);

        try {
            $client->deleteObject([
                'Bucket' => ultrack_env('MINIO_BUCKET', $bucket),
                'Key' => ltrim($bucket . '/' . $filename, '/'),
            ]);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    return ultrack_storage_delete($bucket, $filename);
}

function storage_presign(string $bucket, string $filename, int $ttl = 300): string
{
    if (ultrack_storage_s3_enabled()) {
        $client = new Aws\S3\S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'endpoint' => ultrack_env('MINIO_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => ultrack_env('MINIO_ACCESS_KEY'),
                'secret' => ultrack_env('MINIO_SECRET_KEY'),
            ],
        ]);

        try {
            $request = $client->getCommand('GetObject', [
                'Bucket' => ultrack_env('MINIO_BUCKET', $bucket),
                'Key' => ltrim($bucket . '/' . $filename, '/'),
            ]);

            $presignedRequest = $client->createPresignedRequest($request, '+' . (int) $ttl . ' seconds');
            return (string) $presignedRequest->getUri();
        } catch (Throwable $e) {
            return ultrack_storage_url($bucket, $filename);
        }
    }

    return ultrack_storage_url($bucket, $filename);
}

function storage_put(string $bucket, array $file, string $prefix = 'file'): string
{
    return ultrack_storage_put($bucket, $file, $prefix);
}

function storage_url(string $bucket, string $filename): string
{
    return ultrack_storage_url($bucket, $filename);
}
