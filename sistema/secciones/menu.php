<?php

require_once __DIR__ . '/../inc/permisos.php';

// =============================
// RENDER MENU
// =============================
function renderMenu($menuItems, $seccion, $rand)
{
    foreach ($menuItems as $item) {

        // 🔥 VALIDAR PERMISO DEL ITEM
        if (isset($item['permiso']) && !tieneAcceso($item['permiso'])) {
            continue;
        }

        // =============================
        // SUBMENU
        // =============================
        if (isset($item['submenu'])) {

            $submenuVisible = [];
            $submenuActive = false;

            foreach ($item['submenu'] as $sub) {

                // 🔥 VALIDAR PERMISO SUBMENU
                if (isset($sub['permiso']) && !tieneAcceso($sub['permiso'])) {
                    continue;
                }

                $submenuVisible[] = $sub;

                if ($seccion === $sub['seccion']) {
                    $submenuActive = true;
                }
            }

            // 🔥 SI NO HAY ITEMS VISIBLES NO MOSTRAR EL MENÚ
            if (empty($submenuVisible))
                continue;
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

                    <?php foreach ($submenuVisible as $sub):

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

            $isLogout = isset($item['url']); // 👉 si tiene URL, es acción (logout)

$isActive = (!$isLogout && isset($item['seccion']) && $seccion === $item['seccion']);

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
// MENÚ (CON PERMISOS)
// =============================
$menuItems = [

    ['seccion' => 'inicio', 'label' => 'Inicio', 'icon' => 'fas fa-home', 'permiso' => 'inicio'],

    ['seccion' => 'turnos', 'label' => 'Turnos', 'icon' => 'fas fa-calendar', 'permiso' => 'turnos'],

    ['seccion' => 'pacientes', 'label' => 'Pacientes', 'icon' => 'fas fa-user', 'permiso' => 'pacientes'],

    ['seccion' => 'profesionales', 'label' => 'Profesionales', 'icon' => 'fas fa-user-md', 'permiso' => 'profesionales'],

    ['seccion' => 'socios', 'label' => 'Socios', 'icon' => 'fas fa-handshake', 'permiso' => 'socios'],

    [
        'label' => 'Lista de espera',
        'icon' => 'fas fa-clock',
        'permiso' => 'lista_espera',
        'submenu' => [
            ['seccion' => 'lista_psicologia_adulto', 'label' => 'Psicología Adulto', 'icon' => 'fas fa-user', 'permiso' => 'lista_espera'],
            ['seccion' => 'lista_psicologia_menores', 'label' => 'Psicología Menores', 'icon' => 'fas fa-child', 'permiso' => 'lista_espera'],
            ['seccion' => 'lista_psicopedagogia', 'label' => 'Psicopedagogía', 'icon' => 'fas fa-brain', 'permiso' => 'lista_espera'],
            ['seccion' => 'lista_fonoaudiologia', 'label' => 'Fonoaudiología', 'icon' => 'fas fa-comment-medical', 'permiso' => 'lista_espera'],
            ['seccion' => 'lista_kinesiologia', 'label' => 'Kinesiología', 'icon' => 'fas fa-dumbbell', 'permiso' => 'lista_espera']
        ]
    ],

    ['seccion' => 'estadisticas', 'label' => 'Estadísticas', 'icon' => 'fas fa-chart-bar', 'permiso' => 'estadisticas'],

    [
        'label' => 'Estudios',
        'icon' => 'fas fa-vial',
        'permiso' => 'estudios',
        'submenu' => [
            ['seccion' => 'estudios_laboratorio', 'label' => 'Laboratorio', 'icon' => 'fas fa-flask', 'permiso' => 'estudios'],
            ['seccion' => 'estudios_cardiologia', 'label' => 'Cardiología del Sur', 'icon' => 'fas fa-heartbeat', 'permiso' => 'estudios']
        ]
    ],

    [
    'label' => 'Caja',
    'icon' => 'fas fa-cash-register',
    'permiso' => 'caja',
    'submenu' => [
        [
            'seccion' => 'cajas',
            'label' => 'Abrir/Cerrar caja', // listado de todas las cajas
            'icon' => 'fas fa-boxes', 
            'permiso' => 'caja'
        ],
        [
            'seccion' => 'caja',
            'label' => 'Caja Diaria', // caja abierta / control rápido
            'icon' => 'fas fa-cash-register', 
            'permiso' => 'caja'
        ],
        [
            'seccion' => 'movimientos_caja',
            'label' => 'Ingresos/Egresos', // caja abierta / control rápido
            'icon' => 'fas fa-cash-register', 
            'permiso' => 'caja'
        ],
        [
            'seccion' => 'partes_resumen',
            'label' => 'Partes', // caja abierta / control rápido
            'icon' => 'fas fa-cash-register', 
            'permiso' => 'caja'
        ]
    ]
],

    ['seccion' => 'historia_pacientes', 'label' => 'Historia clínica', 'icon' => 'fas fa-folder-open', 'permiso' => 'historia_pacientes'],

  [
    'seccion' => 'pagos',
    'label' => 'Pagos profesionales',
    'icon' => 'fas fa-money-bill-alt', // 💰 pagos
    'permiso' => 'pagos'
],
[
    'label' => 'Administrar pagos',
    'icon' => 'fas fa-chart-pie', // ⚙️ configuración
    'permiso' => 'administrar_pagos',
    'submenu' => [
        [
            'seccion' => 'practicas',
            'label' => 'Prácticas',
            'icon' => 'fas fa-stethoscope', // 🩺 médico
            'permiso' => 'administrar_pagos'
        ],
        [
            'seccion' => 'practica_precio',
            'label' => 'Precios',
            'icon' => 'fas fa-dollar-sign', // 💲 precios
            'permiso' => 'administrar_pagos'
        ],
        [
            'seccion' => 'practica_reparto',
            'label' => 'Reglas',
            'icon' => 'fas fa-percentage', // 📊 reparto
            'permiso' => 'administrar_pagos'
        ]
    ]
],
[
    'label' => 'Arrastre',
    'icon' => 'fas fa-arrows-alt', // ⚙️ panel/configuración
    'permiso' => 'arrastre',
    'submenu' => [
        [
            'seccion' => 'arrastre',
            'label' => 'Arrastre',
            'icon' => 'fas fa-random', // 🔄 movimiento/transferencia
            'permiso' => 'arrastre'
        ],
        [
            'seccion' => 'egresos',
            'label' => 'Egresos',
            'icon' => 'fas fa-file-invoice-dollar', // 📄💸 gastos
            'permiso' => 'arrastre'
        ],
        [
            'seccion' => 'articulos',
            'label' => 'Artículos',
            'icon' => 'fas fa-boxes', // 📦 inventario
            'permiso' => 'arrastre'
        ]
    ]
],

    ['seccion' => 'empleado', 'label' => 'Administrar Empleados', 'icon' => 'fas fa-cogs', 'permiso' => 'administrar empleados'],

    ['seccion' => 'auditoria', 'label' => 'Auditoría', 'icon' => 'fas fa-history', 'permiso' => 'auditoria'],

    ['seccion' => 'autorizaciones_ip', 'label' => 'Accesos remotos', 'icon' => 'fas fa-globe', 'permiso' => 'autorizaciones_ip'],

    ['seccion' => 'salir', 'label' => 'Salir', 'icon' => 'fas fa-sign-out-alt', 'url' => './secciones/logout.php']

];

?>

<ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

    <?php
    $seccion = $_GET['seccion'] ?? 'inicio';
    $rand = $_GET['nc'] ?? time();
    renderMenu($menuItems, $seccion, $rand);
    ?>

</ul>