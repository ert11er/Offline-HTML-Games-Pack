<?php
/**
 * Server-side cached frontend and game-list endpoint.
 *
 * Route / to the cached index.html and /list.json to the cached game list.
 * Apache users can use the accompanying .htaccess file to route these paths
 * here. Cache files are kept in the server's temporary directory, not in the
 * client's browser.
 */

const UPSTREAM_INDEX_URL = 'https://raw.githubusercontent.com/ert11er/Offline-HTML-Games-Pack/master/index.html';
const UPSTREAM_LIST_URL = 'https://raw.githubusercontent.com/ert11er/Offline-HTML-Games-Pack/master/list.json';
const CACHE_TTL = 300; // Refresh upstream files every five minutes.

function readCache(string $path): ?string
{
    if (!is_file($path)) {
        return null;
    }

    $contents = @file_get_contents($path);
    return $contents === false ? null : $contents;
}

function fetchUpstream(string $url): ?string
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'follow_location' => 1,
            'user_agent' => 'Offline-HTML-Games-Pack server cache',
        ],
    ]);

    $contents = @file_get_contents($url, false, $context);
    if ($contents === false || trim($contents) === '') {
        return null;
    }

    // Ensure the upstream response was successful; do not cache error pages.
    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
            $status = (int) $matches[1];
        }
    }
    if ($status !== 200) {
        return null;
    }

    return $contents;
}

function storeCache(string $path, string $contents): bool
{
    $temporaryPath = $path . '.' . uniqid('', true) . '.tmp';
    if (@file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
        @unlink($temporaryPath);
        return false;
    }

    $stored = @rename($temporaryPath, $path);
    if (!$stored) {
        @unlink($temporaryPath);
    }
    return $stored;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET' && $method !== 'HEAD') {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit('Method not allowed');
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path !== '/' && $path !== '/list.json') {
    http_response_code(404);
    exit('Not found');
}

// Prevent browsers and intermediary proxies from keeping a client-side copy.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$cacheDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR
    . 'offline-games-' . hash('sha256', __DIR__);
if (!is_dir($cacheDirectory)) {
    @mkdir($cacheDirectory, 0700, true);
}

$listCachePath = $cacheDirectory . DIRECTORY_SEPARATOR . 'list.json';
$htmlCachePath = $cacheDirectory . DIRECTORY_SEPARATOR . 'load.html';
$lock = @fopen($cacheDirectory . DIRECTORY_SEPARATOR . 'refresh.lock', 'c');
if ($lock !== false) {
    @flock($lock, LOCK_EX);
}

$list = readCache($listCachePath);
$html = readCache($htmlCachePath);
$cacheFresh = $list !== null
    && $html !== null
    && (time() - (int) @filemtime($listCachePath)) < CACHE_TTL
    && (time() - (int) @filemtime($htmlCachePath)) < CACHE_TTL;

if (!$cacheFresh) {
    $latestList = fetchUpstream(UPSTREAM_LIST_URL);
    $latestHtml = fetchUpstream(UPSTREAM_INDEX_URL);

    if ($latestList !== null && $latestHtml !== null && is_array(json_decode($latestList, true))) {
        // Make the fetched page use this server's local list endpoint.
        $latestHtml = str_replace(UPSTREAM_LIST_URL, '/list.json', $latestHtml);

        if (storeCache($listCachePath, $latestList) && storeCache($htmlCachePath, $latestHtml)) {
            $list = $latestList;
            $html = $latestHtml;
        }
    }
}

if (is_resource($lock)) {
    @flock($lock, LOCK_UN);
    fclose($lock);
}

if ($path === '/') {
    if ($html === null) {
        http_response_code(502);
        exit('Unable to download index.html and no cached copy is available.');
    }
    header('Content-Type: text/html; charset=utf-8');
    if ($method !== 'HEAD') {
        echo $html;
    }
    exit;
}

if ($list === null) {
    http_response_code(502);
    exit('Unable to download list.json and no cached copy is available.');
}

header('Content-Type: application/json; charset=utf-8');
if ($method !== 'HEAD') {
    echo $list;
}
