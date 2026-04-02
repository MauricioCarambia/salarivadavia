<?php

// =============================
// RENDER MENU (SIN PERMISOS)
// =============================
function renderMenu($menuItems, $seccion, $rand)
{
    foreach ($menuItems as $item) {

        // =============================
        // SUBMENU
        // =============================
        if (isset($item['submenu'])) {

            $submenuActive = false;

            foreach ($item['submenu'] as $sub) {
                if ($seccion === $sub['seccion']) {
                    $submenuActive = true;
                    break;
                }
            }
            ?>

            <li class="nav-item <?= $submenuActive ? 'menu-open' : '' ?>">

                <a href="#" class="nav-link <?= $submenuActive ? 'active' : '' ?>">
                    <i class="nav-icon <?= $item['icon'] ?>"></i>
                    <p>
                        <?= $item['label'] ?>
                        <i class="right fas fa-angle-right"></i>
                    </p>
                </a>

                <ul class="nav nav-treeview">

                    <?php foreach ($item['submenu'] as $sub):

                        $subActive = ($seccion === $sub['seccion']);
                        ?>

                        <li class="nav-item">
                            <a href="./?seccion=<?= $sub['seccion'] ?>&nc=<?= $rand ?>"
                               class="nav-link <?= $subActive ? 'active' : '' ?>">
                                <i class="nav-icon <?= $sub['icon'] ?? 'far fa-circle' ?>"></i>
                                <p><?= $sub['label'] ?></p>
                            </a>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </li>

        <?php
        } else {

            $isActive = isset($item['seccion']) && ($seccion === $item['seccion']);
            $url = $item['url'] ?? "./?seccion={$item['seccion']}&nc={$rand}";
            ?>

            <li class="nav-item">

                <a href="<?= $url ?>" class="nav-link <?= $isActive ? 'active' : '' ?>">
                    <i class="nav-icon <?= $item['icon'] ?>"></i>
                    <p><?= $item['label'] ?></p>
                </a>

            </li>

        <?php
        }
    }
}


// =============================
// MENÚ PROFESIONAL
// =============================
if (isset($_SESSION["tipo"]) && $_SESSION["tipo"] === 'profesional') {

    $menuItems = [

        [
            'seccion'=>'turnos_profesional',
            'label'=>'Turnos del día',
            'icon'=>'fas fa-calendar-day'
        ],

        [
            'seccion'=>'historia_pacientes',
            'label'=>'Historia clínica',
            'icon'=>'fas fa-folder-open'
        ],

        [
            'seccion'=>'salir',
            'label'=>'Salir',
            'icon'=>'fas fa-sign-out-alt',
            'url'=>'./secciones/logout.php'
        ]

    ];

?>

<ul class="nav nav-pills nav-sidebar flex-column"
    data-widget="treeview"
    role="menu"
    data-accordion="false">

    <?php renderMenu($menuItems, $seccion, $rand); ?>

</ul>

<?php } ?>