<?php
// Incluir la conexión a la base de datos
require_once __DIR__ . "/sistema/inc/db.php";

// AJUSTE: Se agregó "LIMIT 4" para traer solo las últimas 4 noticias
$stmt = $pdo->query("SELECT * FROM articulos ORDER BY created_at DESC LIMIT 4");
$articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Noticias | Sala Rivadavia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/x-icon" href="sistema/images/sala.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="sistema/styles/style.css">

    <style>
        /* AJUSTE: Forzamos la grilla a tener solo 2 columnas en pantallas grandes */
        @media (min-width: 992px) {
            .grid {
                grid-template-columns: repeat(2, 1fr) !important;
                max-width: 900px; /* Estrechamos un poco el contenedor para que no queden tan anchas */
                margin-left: auto;
                margin-right: auto;
            }
        }
        
        /* Aseguramos que en tablets y móviles se vea de a 1 si el espacio es poco */
        @media (max-width: 991px) {
            .grid {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</head>

<body class="bodynoticia">

    <nav class="nave">
        <a href="index.php" class="logo-container">
            <img src="sistema/images/logo_blanco.png" alt="Logo Sala Rivadavia" class="logo-img">
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
                <a href="https://wa.me/5491122436786?text=Hola!%20Quisiera%20consultar%20por%20" target="_blank" class="contact-link wa-link">
                    <i class="fab fa-whatsapp"></i>
                </a>
            </div>
            <div class="nav-links">
                <a href="index.php">Inicio</a>
                <a href="noticias.php">Noticias</a>
            </div>
        </div>
    </nav>

    <header class="header">
        <h1>Últimas Noticias</h1>
        <p>Mantente al día con las novedades más recientes de nuestra institución.</p>
    </header>

    <main class="container">
        <div class="grid">
            <?php foreach ($articulos as $a): ?>
                <article class="cards">
                    <div class="cards-img-wrapper">
                        <span class="category-tag">Novedad</span>
                        <?php if (!empty($a['imagen'])): ?>
                            <img src="sistema/uploads/<?= htmlspecialchars($a['imagen']) ?>" alt="<?= htmlspecialchars($a['titulo']) ?>">
                        <?php else: ?>
                            <img src="https://images.unsplash.com/photo-1504711432869-00d0211995a7?auto=format&fit=crop&q=80&w=600" alt="Default">
                        <?php endif; ?>
                    </div>

                    <div class="cards-body">
                        <div class="fecha">
                            <i class="far fa-calendar-alt"></i>
                            <?= date('d M, Y', strtotime($a['created_at'])) ?>
                        </div>
                        <h3><?= htmlspecialchars($a['titulo']) ?></h3>
                        <p><?= substr(strip_tags($a['texto']), 0, 110) ?>...</p>
                        <div class="cards-footer">
                            <a href="articulo.php?id=<?= $a['id'] ?>" class="read-more">
                                Leer más <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </main>

  <footer class="main-footer">
        <div class="footer-grid">
            <!-- Columna 1: Sobre nosotros -->
            <div class="footer-col footer-about">
                <img src="sistema/images/logo_blanco.png" alt="Logo Sala Rivadavia" class="logo-img">
                <p>Promoviendo la cultura y el encuentro vecinal desde nuestra Sociedad de Fomento. Un espacio de todos.</p>

                <div class="footer-contact-info">
                    <h4>Contacto</h4>
                    <p><i class="fas fa-map-marker-alt"></i> <strong>Dirección:</strong> Av. Eva Peron 695, Temperley</p>

                    <!-- Enlace para llamar directamente -->
                    <p>
                        <a href="tel:+541139894325" class="contact-link">
                            <i class="fas fa-phone-alt"></i> <strong>Tel:</strong> 3989-4325
                        </a>
                    </p>
                    <p>
                        <a href="tel:+541139912183" class="contact-link">
                            <i class="fas fa-phone-alt"></i> <strong>Tel:</strong> 3991-2183
                        </a>
                    </p>

                    <!-- Enlace directo a WhatsApp -->
                    <p>
                        <a href="https://wa.me/5491122436786?text=Hola!%20Quisiera%20consultar%20por%20" target="_blank" class="contact-link wa-link">
                            <i class="fab fa-whatsapp"></i> <strong>WhatsApp:</strong> +54 9 11 2243-6786
                        </a>
                    </p>
                </div>
            </div>

            <!-- Columna 2: Navegación -->
            <div class="footer-col">
                <h4>Navegación</h4>
                <ul class="footer-links">
                    <li><a href="index.php">Inicio</a></li>
                    <li><a href="noticias.php">Noticias</a></li>
                </ul>
                <div class="footer-social-big" style="margin-top: 0; margin-bottom: 15px;">
                    <a href="https://www.facebook.com/la.sala.rivadavia" class="fb" title="Facebook" target="_blank">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://www.instagram.com/lasalarivadavia" class="ig" title="Instagram" target="_blank">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="https://wa.me/5491122436786?text=Hola!%20Quisiera%20consultar%20por%20" target="_blank" class="contact-link wa-link">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                </div>
            </div>

            <!-- Columna 3: Redes y Mapa -->
            <div class="footer-col">
                <h4>Ubicación</h4>


                <div class="footer-map">
                    <!-- REEMPLAZA EL SRC CON TU URL DE GOOGLE MAPS -->
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3277.2179926191807!2d-58.390543387912835!3d-34.77528706646768!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x95bcd2d93ade4b85%3A0xe66d5172dcf019b1!2sSala%20de%20Fomento%20Bernardino%20Rivadavia%20atenci%C3%B3n%20primaria!5e0!3m2!1ses!2sar!4v1777510807241!5m2!1ses!2sar" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> Sala Rivadavia - Sociedad de Fomento. <br>
                <small>Temperley, Buenos Aires, Argentina.</small>
            </p>
        </div>
    </footer>

</body>
</html>