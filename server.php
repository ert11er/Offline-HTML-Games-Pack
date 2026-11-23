<?php
/**
 * Cached proxy for this repository's list.txt.
 * Upload this file to a PHP-enabled server and request it to receive the list.
 */

const LIST_URL = 'https://raw.githubusercontent.com/ert11er/Offline-HTML-Games-Pack/master/list.txt';
const CACHE_TTL = 300; // Refresh the cached list every five minutes.

function readCache(string $path): ?string
{
    if (!is_file($path)) {
        return null;
    }

    $contents = @file_get_contents($path);
    return $contents === false ? null : $contents;
}

function downloadList(): ?string
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'follow_location' => 1,
            'user_agent' => 'Offline-HTML-Games-Pack list cache',
        ],
    ]);

    $contents = @file_get_contents(LIST_URL, false, $context);
    if ($contents === false) {
        return null;
    }

    // Do not cache an error page or an empty response as the list.
    if (trim($contents) === '') {
        return null;
    }

    return $contents;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit('Method not allowed');
}

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=' . CACHE_TTL);

$cachePath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR
    . 'offline-games-list-' . hash('sha256', __DIR__) . '.txt';
$cached = readCache($cachePath);

if ($cached !== null && (time() - @filemtime($cachePath)) < CACHE_TTL) {
    echo $cached;
    exit;
}

// Lock refreshes so simultaneous requests do not all fetch the upstream list.
$lock = @fopen($cachePath . '.lock', 'c');
if ($lock !== false) {
    @flock($lock, LOCK_EX);

    // Another request may have refreshed the cache while we waited.
    $cached = readCache($cachePath);
    if ($cached !== null && (time() - @filemtime($cachePath)) < CACHE_TTL) {
        @flock($lock, LOCK_UN);
        fclose($lock);
        echo $cached;
        exit;
    }
}

$latest = downloadList();
if ($latest !== null) {
    $temporaryPath = $cachePath . '.' . uniqid('', true) . '.tmp';
    if (@file_put_contents($temporaryPath, $latest, LOCK_EX) !== false) {
        @rename($temporaryPath, $cachePath);
    }
    @unlink($temporaryPath);

    if (is_resource($lock)) {
        @flock($lock, LOCK_UN);
        fclose($lock);
    }

    echo $latest;
    exit;
}

if (is_resource($lock)) {
    @flock($lock, LOCK_UN);
    fclose($lock);
}

// Keep serving a previously cached copy during an upstream outage.
if ($cached !== null) {
    header('Warning: 110 - "Response is stale"');
    echo $cached;
    exit;
}

http_response_code(502);
exit('Unable to download list.txt and no cached copy is available.');
