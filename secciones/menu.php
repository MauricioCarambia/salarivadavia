<?php

function renderMenu($menuItems, $seccion, $rand)
{
    foreach ($menuItems as $item) {

        // Detectar activo en item normal
        $isActive = isset($item['seccion']) && ($seccion === $item['seccion']);

        // MENU CON SUBMENU
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

<?php if(isset($item['badge'])): ?>
<span class="badge badge-primary right"><?= $item['badge'] ?></span>
<?php endif; ?>

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

<p>
<?= $sub['label'] ?>

<?php if(isset($sub['badge'])): ?>
<span class="badge badge-warning right"><?= $sub['badge'] ?></span>
<?php endif; ?>

</p>

</a>

</li>

<?php endforeach; ?>

</ul>

</li>

<?php

        } else {

            $url = $item['url'] ?? "./?seccion={$item['seccion']}&nc={$rand}";
?>

<li class="nav-item">

<a href="<?= $url ?>" class="nav-link <?= $isActive ? 'active' : '' ?>">

<i class="nav-icon <?= $item['icon'] ?>"></i>

<p>
<?= $item['label'] ?>

<?php if(isset($item['badge'])): ?>
<span class="badge badge-info right"><?= $item['badge'] ?></span>
<?php endif; ?>

</p>

</a>

</li>

<?php
        }
    }
}

$menuItems = [

['seccion'=>'inicio','label'=>'Inicio','icon'=>'fas fa-home'],
['seccion'=>'turnos','label'=>'Turnos','icon'=>'fas fa-calendar'],
['seccion'=>'pacientes','label'=>'Pacientes','icon'=>'fas fa-user'],
['seccion'=>'profesionales','label'=>'Profesionales','icon'=>'fas fa-user-md'],
['seccion'=>'socios','label'=>'Socios','icon'=>'fas fa-handshake'],

[
'label'=>'Lista de espera',
'icon'=>'fas fa-clock',
'submenu'=>[
    ['seccion'=>'lista_espera_psico_mayores','label'=>'Psicología Adulto','icon'=>'fas fa-user'],
    ['seccion'=>'lista_espera_psico_menores','label'=>'Psicología Menores','icon'=>'fas fa-child'],
    ['seccion'=>'lista_espera_psicopedagogia','label'=>'Psicopedagogía','icon'=>'fas fa-brain'],
    ['seccion'=>'lista_espera_fono','label'=>'Fonoaudiología','icon'=>'fas fa-comment-medical'],
    ['seccion'=>'lista_espera_kine','label'=>'Kinesiología','icon'=>'fas fa-dumbbell']
]
],

['seccion'=>'estadisticas','label'=>'Estadísticas','icon'=>'fas fa-chart-bar'],

[
'label'=>'Estudios',
'icon'=>'fas fa-vial',
'submenu'=>[
    ['seccion'=>'estudios_laboratorio','label'=>'Laboratorio','icon'=>'fas fa-flask'],
    ['seccion'=>'estudios_cardiologia','label'=>'Cardiología del Sur','icon'=>'fas fa-heartbeat']
]
],

['seccion'=>'caja','label'=>'Caja','icon'=>'fas fa-cash-register'],
['seccion'=>'historia_pacientes','label'=>'Historia clínica','icon'=>'fas fa-folder-open'],
['seccion'=>'pagos','label'=>'Pagos profesionales','icon'=>'fas fa-credit-card'],
['seccion'=>'empleado','label'=>'Administrar','icon'=>'fas fa-cogs'],
['seccion'=>'salir','label'=>'Salir','icon'=>'fas fa-sign-out-alt','url'=>'./secciones/logout.php']

];
?>

<ul class="nav nav-pills nav-sidebar flex-column"
data-widget="treeview"
role="menu"
data-accordion="false">

<?php renderMenu($menuItems, $seccion, $rand); ?>

</ul>

