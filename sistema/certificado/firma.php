<?php

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$request = $data['request'] ?? '';

if (empty($request)) {
    echo json_encode([
        'success' => false,
        'message' => 'Request vacío'
    ]);
    exit;
}

/* =================================
    🔐 RUTA CLAVE PRIVADA
================================= */
$privateKeyPath = __DIR__ . '/private-key.pem';

if (!file_exists($privateKeyPath)) {

    echo json_encode([
        'success' => false,
        'message' => 'No existe private-key.pem',
        'path' => $privateKeyPath
    ]);

    exit;
}

/* =================================
    🔑 LEER CLAVE
================================= */
$privateKeyContent = file_get_contents($privateKeyPath);

$privateKey = openssl_pkey_get_private($privateKeyContent);

if (!$privateKey) {

    echo json_encode([
        'success' => false,
        'message' => 'No se pudo cargar la clave privada'
    ]);

    exit;
}

/* =================================
    ✍️ FIRMAR
================================= */
$signature = '';

$ok = openssl_sign(
    $request,
    $signature,
    $privateKey,
    OPENSSL_ALGO_SHA256
);

if (!$ok) {

    echo json_encode([
        'success' => false,
        'message' => 'Error al firmar'
    ]);

    exit;
}

/* =================================
    ✅ RESPUESTA
================================= */
echo json_encode([
    'success' => true,
    'signature' => base64_encode($signature)
]);

openssl_free_key($privateKey);