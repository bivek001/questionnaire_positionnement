<?php
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/admin_language.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);

session_regenerate_id(true);

header('Location: login.php?lang=' . rawurlencode(currentLanguage()));

exit;