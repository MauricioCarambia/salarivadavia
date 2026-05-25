        <?php if(!isset($rand)) { $rand = rand(1000, 9999); } ?>
        <ul class="nav" id="side-menu">
          <li <?php if(isset($seccion) && strpos($seccion,'socios') !== false){echo 'class="active"';}?>>
            <a href="./?seccion=socios&nc=<?php echo $rand;?>"> <span class="nav-label">Socios</span></a>
          </li>
          <li <?php if(isset($seccion) && strpos($seccion,'salir') !== false){echo 'class="active"';}?>>
            <a href="./secciones/logout.php?nc=<?php echo $rand;?>"> <span class="nav-label">Salir</span></a>
          </li>
        </ul>