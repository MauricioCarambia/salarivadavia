<ul class="nav nav-pills nav-sidebar flex-column"
data-widget="treeview"
role="menu"
data-accordion="false">

<li class="nav-item">
<a href="./?seccion=turnos_profesional&nc=<?php echo $rand; ?>"
class="nav-link <?php if (strpos($seccion, 'turnos_profesional') !== false) echo 'active'; ?>">

<i class="nav-icon fa fa-calendar"></i>
<p>Turnos del día</p>

</a>
</li>

<li class="nav-item">
<a href="./?seccion=historia_pacientes&nc=<?php echo $rand; ?>"
class="nav-link <?php if (strpos($seccion, 'historia_pacientes') !== false) echo 'active'; ?>">

<i class="nav-icon fa fa-folder-open"></i>
<p>Historia clínica</p>

</a>
</li>

<li class="nav-item">
<a href="./secciones/logout.php?nc=<?php echo $rand; ?>"
class="nav-link <?php if (strpos($seccion, 'salir') !== false) echo 'active'; ?>">

<i class="nav-icon fa fa-sign-out"></i>
<p>Salir</p>

</a>
</li>

</ul>