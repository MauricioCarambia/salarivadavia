<?php
require_once __DIR__ . '/../inc/db.php';

$id = $_POST['id'] ?? 0;

try {

    $sql = $pdo->prepare("
    UPDATE cardiologia_sur SET
        apellido = :apellido,
        nombre = :nombre,
        documento = :documento,
        celular = :celular,
        nacimiento = :nacimiento,
        domicilio = :domicilio,
        obra_social = :obra_social,
        estudio = :estudio,
        valor = :valor,
        cobrado = :cobrado,
        turno = :turno,
        aviso = :aviso
    WHERE id = :id
");

    $sql->execute([
        ':apellido' => $_POST['apellido'],
        ':nombre' => $_POST['nombre'],
        ':documento' => $_POST['documento'],
        ':celular' => $_POST['celular'],
        ':nacimiento' => $_POST['nacimiento'],   // ✅ CORRECTO
        ':domicilio' => $_POST['domicilio'],     // ✅ CORRECTO
        ':obra_social' => $_POST['obra_social'],
        ':estudio' => $_POST['estudio'],
        ':valor' => $_POST['valor'],
        ':cobrado' => $_POST['cobrado'],
        ':turno' => $_POST['turno'],
        ':aviso' => $_POST['aviso'],
        ':id' => $id
    ]);

    echo json_encode(['ok' => true]);

} catch (PDOException $e) {

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}