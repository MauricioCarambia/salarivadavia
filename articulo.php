<?php
require_once __DIR__ . "/sistema/inc/db.php";

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM articulos WHERE id=?");
$stmt->execute([$id]);

$articulo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$articulo) {
    header("Location: noticias.php"); // Redirigir en lugar de morir feo
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($articulo['titulo']) ?> | Sala Rivadavia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/x-icon" href="sistema/images/sala.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="sistema/styles/style.css">

</head>

<body class="bodynoticia">

    <nav class="nave">
        <a href="index.php" class="btn-back">
            <i class="fas fa-chevron-left"></i> Volver a noticias
        </a>

        <div class="nav-right">
            <img src="sistema/images/logo_blanco.png" height="40" alt="Logo">
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

    <article class="article-container">
        <header class="article-header">
            <span class="meta-info">Compromiso & Comunidad</span>
            <h1><?= htmlspecialchars($articulo['titulo']) ?></h1>

            <div class="date-share">
                <span>
                    <i class="far fa-calendar-alt"></i>
                    <?php
                    $meses = ["", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
                    $fechaObj = strtotime($articulo['created_at']);
                    $dia = date('d', $fechaObj);
                    $mes = $meses[(int)date('m', $fechaObj)];
                    $anio = date('Y', $fechaObj);
                    echo "$dia de $mes, $anio";
                    ?>
                </span>

            </div>
        </header>

        <?php if (!empty($articulo['imagen'])): ?>

            <img
                class="featured-image"
                src="/sistema/uploads/<?= urlencode($articulo['imagen']) ?>"
                alt="<?= htmlspecialchars($articulo['titulo']) ?>">

        <?php endif; ?>

        <div class="article-content">
            <p><?= nl2br(htmlspecialchars($articulo['texto'])) ?></p>
        </div>

        <footer class="article-footer">
            <p>¿Te gustó esta noticia? Compártela:</p>
            <div class="share-buttons">
                <!-- Facebook -->
                <i class="fab fa-facebook" onclick="window.open('https://www.facebook.com/sharer/sharer.php?u='+encodeURIComponent(window.location.href))"></i>

                <!-- Twitter / X -->
                <i class="fab fa-twitter"></i>

                <!-- WhatsApp -->
                <i class="fab fa-whatsapp" onclick="window.open('https://api.whatsapp.com/send?text='+encodeURIComponent(window.location.href))"></i>

                <!-- Instagram (Acción: Copiar Enlace) -->
                <i class="fab fa-instagram" id="share-instagram" title="Copiar enlace para Instagram"></i>

                <!-- Copiar al portapapeles -->
                <i class="fas fa-link" id="copy-link" title="Copiar enlace"></i>
            </div>
            <div id="copy-message" style="display:none; font-size: 0.8rem; color: var(--accent); margin-top: 10px;">
                ¡Enlace copiado al portapapeles!
            </div>
        </footer>
    </article>

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
<script>
    document.getElementById('share-instagram').addEventListener('click', copyToClipboard);
    document.getElementById('copy-link').addEventListener('click', copyToClipboard);

    function copyToClipboard() {
        const el = document.createElement('textarea');
        el.value = window.location.href;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);

        // Mostrar mensaje de éxito
        const msg = document.getElementById('copy-message');
        msg.style.display = 'block';
        setTimeout(() => {
            msg.style.display = 'none';
        }, 2000);
    }
</script>