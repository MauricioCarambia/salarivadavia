<?php
$data = json_decode(file_get_contents("php://input"), true);
$request = $data['request'] ?? '';

$privateKey = openssl_pkey_get_private(file_get_contents("certificado/private-key.pem"));

openssl_sign($request, $signature, $privateKey, OPENSSL_ALGO_SHA256);

echo base64_encode($signature);