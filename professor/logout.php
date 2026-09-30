<?php
require_once __DIR__ . '/professor_language.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();


// ======================================================
// REMOVE PROFESSOR SESSION DATA
// ======================================================

unset($_SESSION['professor_id']);
unset($_SESSION['professor_username']);
unset($_SESSION['professor_name']);


// ======================================================
// REGENERATE SESSION
// ======================================================

session_regenerate_id(true);


// ======================================================
// RETURN TO PROFESSOR LOGIN
// ======================================================

header('Location: login.php?lang=' . rawurlencode(currentLanguage()));
exit;