<?php
require_once __DIR__ . "/../inc/db.php";

header('Content-Type: application/json');

try {

    $id     = $_POST['id'] ?? null;
    $titulo = trim($_POST['titulo'] ?? '');
    $texto  = trim($_POST['texto'] ?? '');

    if (!$titulo) {
        throw new Exception("Título requerido");
    }

    $imagenNombre = null;

    // ==========================
    // 📸 SUBIR IMAGEN
    // ==========================
    if (!empty($_FILES['imagen']['name'])) {

        $dir = __DIR__ . "/../uploads/";

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        $nombreSeguro = time() . "_" . uniqid() . "." . $ext;

        $ruta = $dir . $nombreSeguro;

        if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta)) {
            throw new Exception("Error al subir imagen");
        }

        $imagenNombre = $nombreSeguro;
    }

    // ==========================
    // 📝 INSERT / UPDATE
    // ==========================
    if ($id) {

        // 👉 si no sube imagen, no la pisa
        if ($imagenNombre) {
            $stmt = $pdo->prepare("
                UPDATE articulos 
                SET titulo=?, texto=?, imagen=? 
                WHERE id=?
            ");
            $stmt->execute([$titulo, $texto, $imagenNombre, $id]);

        } else {
            $stmt = $pdo->prepare("
                UPDATE articulos 
                SET titulo=?, texto=? 
                WHERE id=?
            ");
            $stmt->execute([$titulo, $texto, $id]);
        }

    } else {

        $stmt = $pdo->prepare("
            INSERT INTO articulos (titulo, texto, imagen, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$titulo, $texto, $imagenNombre]);
    }

    echo json_encode(['success' => true]);

} catch (Throwable $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}