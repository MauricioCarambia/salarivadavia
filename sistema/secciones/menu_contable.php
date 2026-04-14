        <ul class="nav" id="side-menu">
          <li <?php if(strpos($seccion,'socios') !== false){echo 'class="active"';}?>>
            <a href="./?seccion=socios&nc=<?php echo $rand;?>"> <span class="nav-label">Socios</span></a>
          </li>
          <li <?php if(strpos($seccion,'salir') !== false){echo 'class="active"';}?>>
            <a href="./secciones/logout.php?nc=<?php echo $rand;?>"> <span class="nav-label">Salir</span></a>
          </li>
        </ul>