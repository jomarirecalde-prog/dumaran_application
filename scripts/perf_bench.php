<?php
/**
 * HTTP performance benchmark (TTFB + total time)
 * Usage: php scripts/perf_bench.php [base_url]
 */
declare(strict_types=1);

$base = $argv[1] ?? 'http://localhost/dumaran_hiring/';

$pages = [
    'login' => $base . 'shared/login.php',
    'start' => $base . 'start.php',
];

function measureUrl(string $url, int $runs = 3): array {
    $ttfb = [];
    $total = [];
    for ($i = 0; $i < $runs; $i++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HEADER => true,
        ]);
        $start = microtime(true);
        $raw = curl_exec($ch);
        $elapsed = (microtime(true) - $start) * 1000;
        $info = curl_getinfo($ch);
        curl_close($ch);
        if ($raw === false) {
            continue;
        }
        $ttfb[] = ($info['starttransfer_time'] ?? 0) * 1000;
        $total[] = $elapsed;
    }
    if ($ttfb === []) {
        return ['error' => 'curl failed', 'url' => $url];
    }
    return [
        'url' => $url,
        'ttfb_ms_avg' => round(array_sum($ttfb) / count($ttfb), 2),
        'total_ms_avg' => round(array_sum($total) / count($total), 2),
        'size_bytes' => $info['size_download'] ?? 0,
        'http_code' => $info['http_code'] ?? 0,
    ];
}

$out = [];
foreach ($pages as $name => $url) {
    $out[$name] = measureUrl($url);
}

echo json_encode($out, JSON_PRETTY_PRINT) . PHP_EOL;
