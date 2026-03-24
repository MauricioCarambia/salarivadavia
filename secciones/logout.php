<?php
session_set_cookie_params(0, '/');
session_name ("turnos"); 
session_cache_limiter ("private");
session_start();
session_destroy();
$rand = mt_rand();
header ("location: ../login.php");
?>