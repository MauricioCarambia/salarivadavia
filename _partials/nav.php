<?php
// Uso: include __DIR__ . '/../_partials/nav.php';
// Requiere que $paginaActual esté definida: 'inicio' | 'noticias'
$paginaActual = $paginaActual ?? '';
?>
<nav class="pub-nav" id="pubNav">
    <a href="index.php" class="pub-nav__brand">
        <img src="sistema/images/logo_blanco.png" alt="Sala Rivadavia" class="pub-nav__logo" id="navLogo">
        <span class="pub-nav__nombre">Sala Rivadavia</span>
    </a>

    <button class="pub-nav__toggle" id="navToggle" aria-label="Menú">
        <span></span><span></span><span></span>
    </button>

    <div class="pub-nav__links" id="navLinks">
        <a href="index.php"      class="pub-nav__link <?= $paginaActual === 'inicio'   ? 'active' : '' ?>">Inicio</a>
        <a href="noticias.php"   class="pub-nav__link <?= $paginaActual === 'noticias' ? 'active' : '' ?>">Noticias</a>
        <a href="https://wa.me/5491122436786?text=Hola!%20Quisiera%20consultar%20por%20"
           target="_blank" rel="noopener" class="pub-nav__cta">
            <i class="fab fa-whatsapp"></i> Consultar
        </a>
    </div>
</nav>
