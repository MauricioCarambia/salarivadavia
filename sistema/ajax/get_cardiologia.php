<?php
require_once '../inc/db.php';

$id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM cardiologia_sur WHERE Id = :id");
$stmt->execute([':id' => $id]);

echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));