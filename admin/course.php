<?php
require_once 'auth.php';
require_once '../config/database.php';
require_once 'admin_language.php';
require_once '../includes/account_security.php';
ux_csrf_check();
$puProfessor=false;
require '../includes/course_workspace.php';
