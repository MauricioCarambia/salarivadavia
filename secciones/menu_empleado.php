<?php
$rand = mt_rand();
$seccion = $_GET['seccion'] ?? 'home';
$accesos_usuario = $_SESSION['accesos'] ?? [];

// verificar acceso
function accesoPermitido($id_acceso, $accesos_usuario) {
    return in_array($id_acceso, $accesos_usuario);
}
// Definir los menús con su id de acceso correspondiente
$menuItems = [
    ['seccion' => 'inicio', 'label' => 'Inicio', 'icon' => 'fa fa-home', 'acceso_id' => 1],
    ['seccion' => 'turnos', 'label' => 'Turnos', 'icon' => 'fa fa-calendar', 'acceso_id' => 2],
    ['seccion' => 'pacientes', 'label' => 'Pacientes', 'icon' => 'fa fa-user', 'acceso_id' => 3],
    ['seccion' => 'profesionales', 'label' => 'Profesionales', 'icon' => 'fa fa-suitcase', 'acceso_id' => 4],
    ['seccion' => 'socios', 'label' => 'Socios', 'icon' => 'fas fa-handshake', 'acceso_id' => 5],
    ['seccion' => 'lista_espera', 'label' => 'Lista espera', 'icon' => 'fa fa-list', 'acceso_id' => 6],
    ['seccion' => 'estudios', 'label' => 'Estudios', 'icon' => 'fa fa-flask', 'acceso_id' => 7],
    ['seccion' => 'caja', 'label' => 'Caja', 'icon' => 'fa fa-bar-chart', 'acceso_id' => 8],
    ['seccion' => 'historia_pacientes', 'label' => 'Historia clínica', 'icon' => 'fa fa-list-alt', 'acceso_id' => 9],
    ['seccion' => 'pagos', 'label' => 'Pagos a profesionales', 'icon' => 'fa fa-money', 'acceso_id' => 10],
    ['seccion' => 'empleado', 'label' => 'Administrar', 'icon' => 'fa fa-plus-circle', 'acceso_id' => 11],
    ['seccion' => 'salir', 'label' => 'Salir', 'icon' => 'fa fa-sign-out', 'url' => './secciones/logout.php']
];
?>


<ul class="nav nav-pills nav-sidebar flex-column"
data-widget="treeview"
role="menu"
data-accordion="false">

<?php foreach ($menuItems as $item): ?>

<?php
    // si no tiene acceso se oculta
    if (isset($item['acceso_id']) && !accesoPermitido($item['acceso_id'], $accesos_usuario)) {
        continue;
    }

    $isActive = strpos($seccion, $item['seccion']) !== false;
    $url = $item['url'] ?? "./?seccion={$item['seccion']}&nc={$rand}";
?>

<li class="nav-item">

<a href="<?= $url ?>" class="nav-link <?= $isActive ? 'active' : '' ?>">

<i class="nav-icon <?= $item['icon'] ?>"></i>

<p><?= $item['label'] ?></p>

</a>

</li>

<?php endforeach; ?>

</ul>