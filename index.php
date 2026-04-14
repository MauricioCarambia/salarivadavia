<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sala Rivadavia</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        body{
            font-family: Arial;
            margin:0;
        }

        .hero{
            background:#007bff;
            color:white;
            padding:80px 20px;
            text-align:center;
        }

        .btn{
            background:white;
            color:#007bff;
            padding:10px 20px;
            border-radius:5px;
            text-decoration:none;
            font-weight:bold;
        }

        .section{
            padding:40px;
            text-align:center;
        }
    </style>
</head>
<body>

    <div class="hero">
        <h1>Sala Bernardino Rivadavia</h1>
        <p>Atención médica integral</p>

        <!-- 🔐 BOTÓN AL SISTEMA -->
       <a href="sistema/login.php" class="btn">Ingresar</a>
<a href="sistema/" class="btn">Panel</a>
    </div>

    <?php
require_once "sistema/inc/db.php";

$stmt = $pdo->query("
    SELECT * FROM articulos 
    WHERE activo = 1 
    ORDER BY fecha DESC 
    LIMIT 5
");

$articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="section">
    <h2>Noticias</h2>

    <?php foreach ($articulos as $a): ?>

        <div style="margin-bottom:30px;">

            <h3><?= htmlspecialchars($a['titulo']) ?></h3>

            <?php if ($a['imagen']): ?>
                <img src="sistema/uploads/<?= $a['imagen'] ?>" style="max-width:300px;">
            <?php endif; ?>

            <p><?= nl2br(htmlspecialchars($a['texto'])) ?></p>

        </div>

    <?php endforeach; ?>

</div>

</body>
</html>