<?php
require_once __DIR__ . "/sistema/inc/db.php";

$busqueda  = trim($_GET['q'] ?? '');
$pagina    = max(1, (int)($_GET['pag'] ?? 1));
$porPagina = 9;

// 1. Contar total primero
if ($busqueda !== '') {
    $like = "%$busqueda%";
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM articulos WHERE titulo LIKE ? OR texto LIKE ?");
    $stmtTotal->execute([$like, $like]);
} else {
    $stmtTotal = $pdo->query("SELECT COUNT(*) FROM articulos");
}
$total     = (int)$stmtTotal->fetchColumn();
$totalPags = max(1, (int)ceil($total / $porPagina));

// 2. Redirigir si la página pedida supera el total
if ($pagina > $totalPags) {
    $qs = '?pag=' . $totalPags . ($busqueda ? '&q=' . urlencode($busqueda) : '');
    header("Location: noticias.php$qs");
    exit;
}

// 3. Query de datos con offset correcto
$offset = ($pagina - 1) * $porPagina;
if ($busqueda !== '') {
    $stmt = $pdo->prepare("SELECT * FROM articulos WHERE titulo LIKE ? OR texto LIKE ? ORDER BY created_at DESC LIMIT $porPagina OFFSET $offset");
    $stmt->execute([$like, $like]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM articulos ORDER BY created_at DESC LIMIT $porPagina OFFSET $offset");
    $stmt->execute();
}

$articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$featured   = $articulos[0] ?? null;
$resto      = array_slice($articulos, 1);

$imgPlaceholder = "https://images.unsplash.com/photo-1504711432869-00d0211995a7?auto=format&fit=crop&q=80&w=900";

$paginaActual = 'noticias';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Noticias | Sala Rivadavia</title>
    <link rel="icon" type="image/x-icon" href="sistema/images/sala.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="public.css">
</head>
<body class="pub">

<?php include __DIR__ . '/_partials/nav.php'; ?>

<!-- HEADER -->
<div class="pub-page-header">
    <h1>Noticias</h1>
    <p>Todas las novedades y comunicados de la Sala Rivadavia</p>

    <form class="pub-search" method="get" action="noticias.php">
        <div class="pub-search__wrap">
            <input class="pub-search__input" type="text" name="q"
                   value="<?= htmlspecialchars($busqueda) ?>"
                   placeholder="Buscar noticia...">
            <button class="pub-search__btn" type="submit">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </form>
</div>

<!-- CONTENIDO -->
<main class="pub-section" style="max-width:1200px;margin:0 auto;">

    <?php if ($total === 0): ?>
        <div class="pub-no-results">
            <i class="fas fa-search"></i>
            <p>No se encontraron noticias<?= $busqueda ? ' para "<strong>' . htmlspecialchars($busqueda) . '</strong>"' : '' ?>.</p>
            <?php if ($busqueda): ?>
                <a href="noticias.php" class="pub-btn pub-btn--primary" style="display:inline-flex;margin-top:20px;">Ver todas</a>
            <?php endif; ?>
        </div>

    <?php else: ?>

        <?php if ($busqueda): ?>
            <p class="pub-count">
                <?= $total ?> resultado<?= $total === 1 ? '' : 's' ?> para
                "<strong><?= htmlspecialchars($busqueda) ?></strong>"
                — <a href="noticias.php" style="color:var(--pub-accent);">limpiar</a>
            </p>
        <?php endif; ?>

        <!-- ARTÍCULO DESTACADO -->
        <?php if ($featured && $pagina === 1 && $busqueda === ''):
            $fImg = (!empty($featured['imagen'])) ? "sistema/uploads/" . htmlspecialchars($featured['imagen']) : $imgPlaceholder;
        ?>
        <article class="pub-card pub-card--horizontal" style="margin-bottom:40px;">
            <div class="pub-card__img">
                <img src="<?= $fImg ?>" alt="<?= htmlspecialchars($featured['titulo']) ?>" loading="lazy">
                <span class="pub-card__tag">Destacado</span>
            </div>
            <div class="pub-card__body">
                <div class="pub-card__date">
                    <i class="far fa-calendar-alt"></i>
                    <?= date('d \d\e F, Y', strtotime($featured['created_at'])) ?>
                </div>
                <h2 class="pub-card__title"><?= htmlspecialchars($featured['titulo']) ?></h2>
                <p class="pub-card__excerpt" style="-webkit-line-clamp:5;">
                    <?= htmlspecialchars(mb_substr(strip_tags($featured['texto']), 0, 320)) ?>…
                </p>
                <div class="pub-card__footer">
                    <a href="articulo.php?id=<?= $featured['id'] ?>" class="pub-card__read">
                        Leer nota completa <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </article>
        <?php
            $listado = $resto;
        else:
            $listado = $articulos;
        endif; ?>

        <!-- GRILLA -->
        <?php if (!empty($listado)): ?>
        <div class="pub-grid pub-grid--3" id="gridNoticias">
            <?php foreach ($listado as $a):
                $img = (!empty($a['imagen'])) ? "sistema/uploads/" . htmlspecialchars($a['imagen']) : $imgPlaceholder;
            ?>
            <article class="pub-card pub-card--small reveal">
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
                    <p class="pub-card__excerpt"><?= htmlspecialchars(mb_substr(strip_tags($a['texto']), 0, 140)) ?>…</p>
                    <div class="pub-card__footer">
                        <a href="articulo.php?id=<?= $a['id'] ?>" class="pub-card__read">
                            Leer más <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- PAGINACIÓN -->
        <?php if ($totalPags > 1):
            $qs = $busqueda ? '&q=' . urlencode($busqueda) : '';
        ?>
        <nav class="pub-pagination" aria-label="Páginas">
            <a href="noticias.php?pag=<?= max(1, $pagina-1) . $qs ?>"
               class="pub-page-btn <?= $pagina <= 1 ? 'disabled' : '' ?>"
               <?= $pagina <= 1 ? 'tabindex="-1"' : '' ?>>
                <i class="fas fa-chevron-left"></i>
            </a>

            <?php for ($p = 1; $p <= $totalPags; $p++): ?>
                <a href="noticias.php?pag=<?= $p . $qs ?>"
                   class="pub-page-btn <?= $p === $pagina ? 'active' : '' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>

            <a href="noticias.php?pag=<?= min($totalPags, $pagina+1) . $qs ?>"
               class="pub-page-btn <?= $pagina >= $totalPags ? 'disabled' : '' ?>"
               <?= $pagina >= $totalPags ? 'tabindex="-1"' : '' ?>>
                <i class="fas fa-chevron-right"></i>
            </a>
        </nav>
        <?php endif; ?>

    <?php endif; ?>
</main>

<?php include __DIR__ . '/_partials/footer.php'; ?>

<script>
const nav = document.getElementById('pubNav');
window.addEventListener('scroll', () => nav.classList.toggle('scrolled', window.scrollY > 20), { passive: true });
document.getElementById('navToggle').addEventListener('click', () => document.getElementById('navLinks').classList.toggle('open'));

const obs = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); } });
}, { threshold: 0.1 });
document.querySelectorAll('.reveal').forEach(el => obs.observe(el));
</script>
</body>
</html>
