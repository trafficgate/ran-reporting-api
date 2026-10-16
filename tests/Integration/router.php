<?php

/**
 * Router for the PHP built-in web server used by DownloaderHttpTest.
 *
 * The first path segment selects the scenario:
 *
 *   /ok/...              200 with the sample report
 *   /status/<code>/...   <code> with a body that echoes the request URI
 *   /flaky/<n>/...       503 for the first <n> requests, then 200 (state in RAN_TEST_STATE_DIR)
 *   /slow/...            waits 1 second, then 200
 *   absolute-form URI    answered like a forward proxy: 200 with X-Proxied-For
 */
$sample = __DIR__ . '/../data/signature-orders.csv';
$uri    = $_SERVER['REQUEST_URI'];

if (preg_match('#^https?://#i', $uri) === 1) {
    header('X-Proxied-For: ' . strtok($uri, '?'));
    header('Content-Type: text/csv');
    readfile($sample);

    return;
}

$segments = explode('/', ltrim((string) parse_url($uri, PHP_URL_PATH), '/'));

switch ($segments[0] ?? '') {
    case 'ok':
        header('Content-Type: text/csv');
        readfile($sample);

        break;

    case 'status':
        http_response_code((int) ($segments[1] ?? 500));
        header('Content-Type: text/plain');
        echo 'Rejected ' . $uri;

        break;

    case 'flaky':
        $counter = getenv('RAN_TEST_STATE_DIR') . '/flaky-counter';
        $count   = (int) @file_get_contents($counter) + 1;
        file_put_contents($counter, (string) $count);

        if ($count <= (int) ($segments[1] ?? 0)) {
            http_response_code(503);
            echo 'Try again';

            break;
        }

        header('Content-Type: text/csv');
        readfile($sample);

        break;

    case 'slow':
        sleep(1);
        header('Content-Type: text/csv');
        readfile($sample);

        break;

    default:
        http_response_code(404);
}
