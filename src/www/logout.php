<?php
require_once(__DIR__ . '/includes/Security.php');
require_once(__DIR__ . '/includes/Session.php');
require_once(__DIR__ . '/database/users_db.php');

Security::configureSession();

$db = new UsersDB();
$session = new Session($db);

// Destroy session properly
$session->destroy();

// Redirect to login page
header("Location: index.php");
exit;
