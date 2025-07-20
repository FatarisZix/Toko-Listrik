<?php
session_start();
session_destroy();
header('Location: /Toko-Listrik/');
exit();
?>