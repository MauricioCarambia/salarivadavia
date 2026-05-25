<?php
// Incluir la conexión a la base de datos
require_once __DIR__ . "/sistema/inc/db.php";

// Consulta para obtener TODAS las noticias, ordenadas por fecha descendente
$stmt = $pdo->query("SELECT * FROM articulos ORDER BY created_at DESC");
$articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Todas las Noticias | Sala Rivadavia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/x-icon" href="sistema/images/sala.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Enlace a tu archivo CSS externo -->
    <link rel="stylesheet" href="sistema/styles/style.css">
</head>

<body class="bodynoticia">

    <nav class="nave">
        <a href="index.php" class="logo-container">
            <img src="sistema/images/logo_blanco.png" class="logo-img" alt="Logo Sala Rivadavia">
            <span class="nav-brand">Sala Rivadavia</span>
        </a>
        <div class="nav-right">
            <div class="nav-socials">
                <a href="https://www.facebook.com/la.sala.rivadavia" class="fb" target="_blank">
                    <i class="fab fa-facebook-f"></i>
                </a>
                <a href="https://www.instagram.com/lasalarivadavia" class="ig" target="_blank">
                    <i class="fab fa-instagram"></i>
                </a>
                <a href="https://wa.me/5491122436786?text=Hola!%20Quisiera%20consultar%20por%20" target="_blank" class="wa-link">
                    <i class="fab fa-whatsapp"></i>
                </a>
            </div>
            <div class="nav-links">
                <a href="index.php">Inicio</a>
                <a href="noticias.php">Noticias</a>
            </div>
        </div>
    </nav>

    <!-- Header con el estilo degradado del CSS -->
    <header class="header">
        <h1>Historial de Noticias</h1>
        <p>Explora todas las publicaciones y eventos pasados de la Sala Rivadavia.</p>
    </header>

    <main class="container">
        <section class="gridNoticias">
            <?php foreach ($articulos as $articulo): ?>
                <article class="cards">
                    <?php if ($articulo['imagen']): ?>
                        <div class="cards-img-wrapper">
                           
                            <img src="sistema/uploads/<?= htmlspecialchars($articulo['imagen']) ?>" alt="<?= htmlspecialchars($articulo['titulo']) ?>">
                        </div>
                    <?php endif; ?>
                    
                    <div class="cards-body">
                        <span class="fecha">
                            <i class="far fa-calendar-alt"></i>
                            <?= date('d/m/Y', strtotime($articulo['created_at'])) ?>
                        </span>
                        
                        <h3><?= htmlspecialchars($articulo['titulo']) ?></h3>
                        
                        <p><?= htmlspecialchars(substr($articulo['texto'], 0, 120)) ?>...</p>
                        
                        <div class="cards-footer">
                            <a href="articulo.php?id=<?= $articulo['id'] ?>" class="read-more">
                                Leer más <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    </main>

    <footer class="main-footer">
        <div class="footer-grid">
            <div class="footer-col footer-about">
                <img src="sistema/images/logo_blanco.png" alt="Logo Sala Rivadavia" class="logo-img" style="margin-bottom:20px">
                <p>Promoviendo la cultura y el encuentro vecinal desde nuestra Sociedad de Fomento. Un espacio de todos.</p>
                <div class="footer-social-big">
                    <a href="https://www.facebook.com/la.sala.rivadavia" class="fb" title="Facebook" target="_blank"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://www.instagram.com/lasalarivadavia" class="ig" title="Instagram" target="_blank"><i class="fab fa-instagram"></i></a>
                    <a href="https://wa.me/5491122436786?text=Hola!%20Quisiera%20consultar%20por%20" target="_blank" class="wa-link"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>

            <div class="footer-col">
                <h4>Contacto</h4>
                <div class="footer-contact-info">
                    <p><i class="fas fa-map-marker-alt"></i> Av. Eva Peron 695, Temperley</p>
                    <p><a href="tel:+541139894325" class="contact-link"><i class="fas fa-phone-alt"></i> 3989-4325</a></p>
                    <p><a href="tel:+541139912183" class="contact-link"><i class="fas fa-phone-alt"></i> 3991-2183</a></p>
                    <p><a href="https://wa.me/5491122436786" target="_blank" class="contact-link"><i class="fab fa-whatsapp"></i> +54 9 11 2243-6786</a></p>
                </div>
            </div>

            <div class="footer-col">
                <h4>Navegación</h4>
                <ul class="footer-links">
                    <li><a href="index.php">Inicio</a></li>
                    <li><a href="noticias.php">Noticias</a></li>
                </ul>
                <div class="footer-map">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3277.2179926191807!2d-58.390543387912835!3d-34.77528706646768!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x95bcd2d93ade4b85%3A0xe66d5172dcf019b1!2sSala%20de%20Fomento%20Bernardino%20Rivadavia%20atenci%C3%B3n%20primaria!5e0!3m2!1ses!2sar!4v1777510807241!5m2!1ses!2sar" loading="lazy"></iframe>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> Sala Rivadavia - Sociedad de Fomento. <br>Temperley, Buenos Aires, Argentina.</p>
        </div>
    </footer>
</body>

</html>