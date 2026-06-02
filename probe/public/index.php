<?php

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($requestUri === '/health/live' || $requestUri === '/health/ready') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'ok',
        'service' => 'probe',
        'time' => gmdate('c'),
    ]);
    exit;
}

http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['error' => 'Not found']);
