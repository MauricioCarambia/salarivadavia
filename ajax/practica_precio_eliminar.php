<?php
session_start();
require_once "../inc/db.php";

$id = $_POST['id'];

$stmt = $pdo->prepare("DELETE FROM practicas_precios WHERE id=?");
$stmt->execute([$id]);

echo json_encode(['success'=>true]);