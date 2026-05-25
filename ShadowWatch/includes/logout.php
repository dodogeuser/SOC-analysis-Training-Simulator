<?php
require_once __DIR__ . '/auth.php';
logoutUser();
header('Location: /shadowwatch/login.php?msg=logged_out');
exit;
