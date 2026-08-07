<?php
// Front door: send visitors to the app (dashboard if signed in, else login).
require __DIR__ . '/../src/auth.php';
header('Location: ' . (current_user() ? 'dashboard.php' : 'login.php'));
exit;
