<?php

const UPSTREAM_INDEX_URL = 'https://raw.githubusercontent.com/ert11er/Offline-HTML-Games-Pack/master/index.html';
const UPSTREAM_LIST_URL = 'https://raw.githubusercontent.com/ert11er/Offline-HTML-Games-Pack/master/list.json';
const CACHE_TTL = 300;

function getCache(string $path): ?string
{
    if (!is_file($path)) {
        return null;
    }

    $contents = @file_get_contents($path);
    return $contents === false ? null : $contents;
}

function download(string $url): ?string
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'follow_location' => 1,
            'ignore_errors' => false,
            'user_agent' => 'Offline-Games-Server/1.0',
        ],
    ]);

    $contents = @file_get_contents($url, false, $context);

    if ($contents === false || trim($contents) === '') {
        return null;
    }

    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
            $status = (int) $matches[1];
        }
    }

    return $status === 200 ? $contents : null;
}

function saveCache(string $path, string $contents): bool
{
    $temporary = $path . '.' . uniqid('', true) . '.tmp';

    if (@file_put_contents($temporary, $contents, LOCK_EX) === false) {
        @unlink($temporary);
        return false;
    }

    if (!@rename($temporary, $path)) {
        @unlink($temporary);
        return false;
    }

    return true;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method !== 'GET' && $method !== 'HEAD') {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit('Method not allowed');
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($requestPath !== '/' && $requestPath !== '/list.json') {
    http_response_code(404);
    exit('Not found');
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$cacheDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR
    . 'offline-games-' . hash('sha256', __DIR__);

if (!is_dir($cacheDirectory) && !@mkdir($cacheDirectory, 0700, true)) {
    http_response_code(500);
    exit('Unable to create the cache directory');
}

$listCache = $cacheDirectory . DIRECTORY_SEPARATOR . 'list.json';
$htmlCache = $cacheDirectory . DIRECTORY_SEPARATOR . 'load.html';

$lock = @fopen($cacheDirectory . DIRECTORY_SEPARATOR . 'refresh.lock', 'c');
if ($lock !== false) {
    @flock($lock, LOCK_EX);
}

$list = getCache($listCache);
$html = getCache($htmlCache);

$listFresh = $list !== null
    && (time() - (int) @filemtime($listCache)) < CACHE_TTL;
$htmlFresh = $html !== null
    && (time() - (int) @filemtime($htmlCache)) < CACHE_TTL;

if (!$listFresh || !$htmlFresh) {
    $latestList = $listFresh ? $list : download(UPSTREAM_LIST_URL);
    $latestHtml = $htmlFresh ? $html : download(UPSTREAM_INDEX_URL);

    if ($latestList !== null
        && is_array(json_decode($latestList, true))
        && $latestHtml !== null
    ) {
        $latestHtml = str_replace(UPSTREAM_LIST_URL, '/list.json', $latestHtml);
        $latestHtml = str_replace(
            'https://raw.githubusercontent.com/ert11er/Offline-HTML-Games-Pack/master/list.json',
            '/list.json',
            $latestHtml
        );

        if (saveCache($listCache, $latestList)) {
            $list = $latestList;
        }

        if (saveCache($htmlCache, $latestHtml)) {
            $html = $latestHtml;
        }
    }
}

if (is_resource($lock)) {
    @flock($lock, LOCK_UN);
    fclose($lock);
}

if ($requestPath === '/') {
    if ($html === null) {
        http_response_code(502);
        exit('Unable to download index.html and no cached copy is available');
    }

    header('Content-Type: text/html; charset=utf-8');

    if ($method !== 'HEAD') {
        echo $html;
    }

    exit;
}

if ($list === null) {
    http_response_code(502);
    exit('Unable to download list.json and no cached copy is available');
}

header('Content-Type: application/json; charset=utf-8');

if ($method !== 'HEAD') {
    echo $list;
}
