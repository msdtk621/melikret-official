<?php
require '_inc.php';
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;
