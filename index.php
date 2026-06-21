<?php
require_once __DIR__ . "/sistema/inc/db.php";

// Última noticia para el hero
$stmtHero = $pdo->query("SELECT * FROM articulos ORDER BY created_at DESC LIMIT 1");
$hero = $stmtHero->fetch(PDO::FETCH_ASSOC);

// Siguientes 4 para la grilla
$stmtGrid = $pdo->query("SELECT * FROM articulos ORDER BY created_at DESC LIMIT 5");
$todos = $stmtGrid->fetchAll(PDO::FETCH_ASSOC);
$gridCards = $hero ? array_slice($todos, 1, 4) : $todos;

$imgPlaceholder = "https://images.unsplash.com/photo-1504711432869-00d0211995a7?auto=format&fit=crop&q=80&w=900";
$heroImg = (!empty($hero['imagen'])) ? "sistema/uploads/" . htmlspecialchars($hero['imagen']) : $imgPlaceholder;

$paginaActual = 'inicio';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sala Rivadavia — Sociedad de Fomento</title>
    <meta name="description" content="Sala de Fomento Bernardino Rivadavia. Salud, cultura y comunidad en Temperley, Buenos Aires.">
    <link rel="icon" type="image/x-icon" href="sistema/images/sala.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="public.css">
</head>
<body class="pub">

<?php include __DIR__ . '/_partials/nav.php'; ?>

<!-- ================================================
     HERO — última noticia
================================================ -->
<?php if ($hero): ?>
<section class="pub-hero">
    <img class="pub-hero__bg" src="<?= $heroImg ?>" alt="<?= htmlspecialchars($hero['titulo']) ?>">
    <div class="pub-hero__overlay"></div>
    <div class="pub-hero__content">
        <span class="pub-hero__tag"><i class="fas fa-newspaper"></i> Última noticia</span>
        <h1 class="pub-hero__title"><?= htmlspecialchars($hero['titulo']) ?></h1>
        <p class="pub-hero__excerpt"><?= htmlspecialchars(mb_substr(strip_tags($hero['texto']), 0, 180)) ?>…</p>
        <div class="pub-hero__meta">
            <span><i class="far fa-calendar-alt"></i> <?= date('d \d\e F, Y', strtotime($hero['created_at'])) ?></span>
        </div>
        <div style="display:flex;gap:14px;flex-wrap:wrap;">
            <a href="articulo.php?id=<?= $hero['id'] ?>" class="pub-btn pub-btn--primary">
                Leer nota completa <i class="fas fa-arrow-right"></i>
            </a>
            <a href="noticias.php" class="pub-btn pub-btn--outline">
                Ver todas las noticias
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ================================================
     GRID — últimas noticias
================================================ -->
<?php if (!empty($gridCards)): ?>
<section class="pub-section pub-section--alt">
    <div style="max-width:1200px;margin:0 auto;">
        <div class="pub-section__head reveal">
            <div>
                <p class="pub-section__label">Noticias recientes</p>
                <h2>Lo que está <em>pasando</em></h2>
                <p class="pub-section__sub">Las novedades más recientes de nuestra institución</p>
            </div>
            <a href="noticias.php" class="pub-link-all">
                Ver todas las noticias <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <div class="pub-grid pub-grid--<?= count($gridCards) >= 4 ? '4' : count($gridCards) ?>">
            <?php foreach ($gridCards as $i => $a):
                $img = (!empty($a['imagen'])) ? "sistema/uploads/" . htmlspecialchars($a['imagen']) : $imgPlaceholder;
            ?>
            <article class="pub-card pub-card--small reveal reveal-delay-<?= min($i + 1, 4) ?>">
                <div class="pub-card__img">
                    <img src="<?= $img ?>" alt="<?= htmlspecialchars($a['titulo']) ?>" loading="lazy">
                    <span class="pub-card__tag">Novedad</span>
                </div>
                <div class="pub-card__body">
                    <div class="pub-card__date">
                        <i class="far fa-calendar-alt"></i>
                        <?= date('d M Y', strtotime($a['created_at'])) ?>
                    </div>
                    <h3 class="pub-card__title"><?= htmlspecialchars($a['titulo']) ?></h3>
                    <p class="pub-card__excerpt"><?= htmlspecialchars(mb_substr(strip_tags($a['texto']), 0, 120)) ?>…</p>
                    <div class="pub-card__footer">
                        <a href="articulo.php?id=<?= $a['id'] ?>" class="pub-card__read">
                            Leer más <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <div class="reveal" style="text-align:center;margin-top:48px;">
            <a href="noticias.php" class="pub-btn pub-btn--dark" style="display:inline-flex;">
                Ver todas las noticias <i class="fas fa-newspaper"></i>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ================================================
     SOBRE NOSOTROS
================================================ -->
<div class="pub-about">
    <div class="pub-about__text reveal">
        <p class="pub-about__label">Nuestra institución</p>
        <h2>Un espacio para <em>todos</em></h2>
        <p>La Sala de Fomento Bernardino Rivadavia es una institución comunitaria dedicada a promover la salud, la cultura y el encuentro vecinal en Temperley, Buenos Aires.</p>
        <p>Brindamos atención médica primaria y espacios de participación para que toda la comunidad pueda acceder a los servicios que necesita.</p>

        <div class="pub-about__items">
            <div class="pub-about__item">
                <i class="fas fa-heartbeat"></i>
                <h4>Atención Primaria</h4>
                <p>Consultas médicas y controles preventivos</p>
            </div>
            <div class="pub-about__item">
                <i class="fas fa-users"></i>
                <h4>Comunidad</h4>
                <p>Actividades vecinales y encuentros</p>
            </div>
            <div class="pub-about__item">
                <i class="fas fa-phone-alt"></i>
                <h4>Contacto</h4>
                <p>3989-4325 / 3991-2183</p>
            </div>
            <div class="pub-about__item">
                <i class="fas fa-map-marker-alt"></i>
                <h4>Dirección</h4>
                <p>Av. Eva Perón 695, Temperley</p>
            </div>
        </div>
    </div>

    <div class="pub-about__map reveal reveal-delay-1">
        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3277.2179926191807!2d-58.390543387912835!3d-34.77528706646768!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x95bcd2d93ade4b85%3A0xe66d5172dcf019b1!2sSala%20de%20Fomento%20Bernardino%20Rivadavia%20atenci%C3%B3n%20primaria!5e0!3m2!1ses!2sar!4v1777510807241!5m2!1ses!2sar"
            loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </div>
</div>

<?php include __DIR__ . '/_partials/footer.php'; ?>

<script>
// Nav
const nav = document.getElementById('pubNav');
window.addEventListener('scroll', () => nav.classList.toggle('scrolled', window.scrollY > 20), { passive: true });
document.getElementById('navToggle').addEventListener('click', () => document.getElementById('navLinks').classList.toggle('open'));

// Scroll reveal
const observer = new IntersectionObserver((entries) => {
    entries.forEach((e, i) => {
        if (e.isIntersecting) {
            e.target.classList.add('visible');
            observer.unobserve(e.target);
        }
    });
}, { threshold: 0.12 });

document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
</script>
</body>
</html>
