<?php
$privateKeyPath = __DIR__ . '/private-key.pem';

if (!file_exists($privateKeyPath)) {
    http_response_code(500);
    echo "ERROR: No existe private-key.pem";
    exit;
}

$privateKey = file_get_contents($privateKeyPath);

$data = json_decode(file_get_contents("php://input"), true);
$request = $data['request'] ?? '';

openssl_sign($request, $signature, $privateKey, OPENSSL_ALGO_SHA256);

echo base64_encode($signature);