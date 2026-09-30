<?php

session_start();


// Remove trainee session data

unset($_SESSION['trainee_id']);


// Regenerate session ID

session_regenerate_id(true);


// Return to registration/login page

header('Location: register.php');

exit;
