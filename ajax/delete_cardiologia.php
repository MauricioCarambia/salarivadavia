<?php
require_once '../inc/db.php';

if(!empty($_POST['ids'])){

$ids = $_POST['ids'];

$in = str_repeat('?,',count($ids)-1).'?';

$stmt = $pdo->prepare("DELETE FROM cardiologia_sur WHERE Id IN ($in)");

$stmt->execute($ids);

}