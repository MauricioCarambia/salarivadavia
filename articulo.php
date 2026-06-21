<?php
require_once __DIR__ . "/sistema/inc/db.php";

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

$stmt = $pdo->prepare("SELECT * FROM articulos WHERE id = ?");
$stmt->execute([$id]);
$articulo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$articulo) {
    header("Location: noticias.php");
    exit;
}

// Artículos relacionados (excluye el actual)
$stmtRel = $pdo->prepare("SELECT * FROM articulos WHERE id != ? ORDER BY created_at DESC LIMIT 3");
$stmtRel->execute([$id]);
$relacionados = $stmtRel->fetchAll(PDO::FETCH_ASSOC);

$imgPlaceholder = "https://images.unsplash.com/photo-1504711432869-00d0211995a7?auto=format&fit=crop&q=80&w=900";
$heroImg = (!empty($articulo['imagen'])) ? "sistema/uploads/" . htmlspecialchars($articulo['imagen']) : $imgPlaceholder;

$meses = ["","Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre"];
$fechaObj = strtotime($articulo['created_at']);
$fechaLarga = date('d', $fechaObj) . ' de ' . $meses[(int)date('m', $fechaObj)] . ', ' . date('Y', $fechaObj);

$paginaActual = 'noticias';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($articulo['titulo']) ?> | Sala Rivadavia</title>
    <meta name="description" content="<?= htmlspecialchars(mb_substr(strip_tags($articulo['texto']), 0, 160)) ?>">
    <!-- Open Graph -->
    <meta property="og:title"       content="<?= htmlspecialchars($articulo['titulo']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars(mb_substr(strip_tags($articulo['texto']), 0, 160)) ?>">
    <meta property="og:image"       content="<?= $heroImg ?>">
    <meta property="og:type"        content="article">
    <link rel="icon" type="image/x-icon" href="sistema/images/sala.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="public.css">
</head>
<body class="pub">

<!-- Barra de progreso de lectura -->
<div class="pub-progress" id="readProgress"></div>

<?php include __DIR__ . '/_partials/nav.php'; ?>

<!-- HERO del artículo -->
<div class="pub-article-hero">
    <img class="pub-article-hero__img" src="<?= $heroImg ?>" alt="<?= htmlspecialchars($articulo['titulo']) ?>">
    <div class="pub-article-hero__overlay"></div>
    <div class="pub-article-hero__content">
        <span class="pub-article-hero__tag"><i class="fas fa-newspaper"></i> Noticia</span>
        <h1 class="pub-article-hero__title"><?= htmlspecialchars($articulo['titulo']) ?></h1>
        <div class="pub-article-hero__meta">
            <span><i class="far fa-calendar-alt"></i> <?= $fechaLarga ?></span>
            <span><i class="fas fa-building"></i> Sala Rivadavia</span>
        </div>
    </div>
</div>

<!-- CUERPO DEL ARTÍCULO -->
<div class="pub-article-wrap">

    <a href="noticias.php" class="pub-article-back">
        <i class="fas fa-arrow-left"></i> Volver a noticias
    </a>

    <div class="pub-article-body" id="articleBody">
        <?= nl2br(htmlspecialchars($articulo['texto'])) ?>
    </div>

    <!-- COMPARTIR -->
    <div class="pub-share">
        <h4>¿Te gustó esta nota? Compartila</h4>
        <div class="pub-share__btns">
            <button class="pub-share__btn pub-share__btn--fb" onclick="shareF()">
                <i class="fab fa-facebook-f"></i> Facebook
            </button>
            <button class="pub-share__btn pub-share__btn--wa" onclick="shareW()">
                <i class="fab fa-whatsapp"></i> WhatsApp
            </button>
            <button class="pub-share__btn pub-share__btn--copy" onclick="copyLink()">
                <i class="fas fa-link"></i> Copiar enlace
            </button>
        </div>
        <p class="pub-share__toast" id="shareToast">¡Enlace copiado!</p>
    </div>

</div>

<!-- ARTÍCULOS RELACIONADOS -->
<?php if (!empty($relacionados)): ?>
<section class="pub-related">
    <div style="max-width:1200px;margin:0 auto;">
        <h3>Otras noticias</h3>
        <div class="pub-grid pub-grid--3">
            <?php foreach ($relacionados as $r):
                $rImg = (!empty($r['imagen'])) ? "sistema/uploads/" . htmlspecialchars($r['imagen']) : $imgPlaceholder;
            ?>
            <article class="pub-card pub-card--small reveal">
                <div class="pub-card__img">
                    <img src="<?= $rImg ?>" alt="<?= htmlspecialchars($r['titulo']) ?>" loading="lazy">
                    <span class="pub-card__tag">Novedad</span>
                </div>
                <div class="pub-card__body">
                    <div class="pub-card__date">
                        <i class="far fa-calendar-alt"></i>
                        <?= date('d M Y', strtotime($r['created_at'])) ?>
                    </div>
                    <h3 class="pub-card__title"><?= htmlspecialchars($r['titulo']) ?></h3>
                    <p class="pub-card__excerpt"><?= htmlspecialchars(mb_substr(strip_tags($r['texto']), 0, 120)) ?>…</p>
                    <div class="pub-card__footer">
                        <a href="articulo.php?id=<?= $r['id'] ?>" class="pub-card__read">
                            Leer más <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/_partials/footer.php'; ?>

<script>
// Barra de progreso de lectura
const progress = document.getElementById('readProgress');
const body     = document.getElementById('articleBody');
window.addEventListener('scroll', () => {
    const top  = body.getBoundingClientRect().top + window.scrollY;
    const h    = body.offsetHeight;
    const pct  = Math.min(100, Math.max(0, (window.scrollY - top + window.innerHeight * .5) / h * 100));
    progress.style.width = pct + '%';
}, { passive: true });

// Nav
const nav = document.getElementById('pubNav');
window.addEventListener('scroll', () => nav.classList.toggle('scrolled', window.scrollY > 20), { passive: true });
document.getElementById('navToggle').addEventListener('click', () => document.getElementById('navLinks').classList.toggle('open'));

// Scroll reveal
const obs = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); } });
}, { threshold: 0.1 });
document.querySelectorAll('.reveal').forEach(el => obs.observe(el));

// Compartir
function shareF() {
    window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(location.href));
}
function shareW() {
    window.open('https://api.whatsapp.com/send?text=' + encodeURIComponent(document.title + ' ' + location.href));
}
function copyLink() {
    navigator.clipboard.writeText(location.href).then(() => {
        const t = document.getElementById('shareToast');
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 2500);
    });
}
</script>
</body>
</html>
