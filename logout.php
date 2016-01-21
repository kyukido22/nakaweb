<?php
session_start();
session_destroy();
session_start();
$_SESSION['errortext']='Your session timed out.';
header('Location: login.php');
?>
