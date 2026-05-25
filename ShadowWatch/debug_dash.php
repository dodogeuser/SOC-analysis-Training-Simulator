<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$_SESSION['user_id'] = 1; // fake login as admin
echo "Session OK<br>";

$user = getCurrentUser();
echo "User: " . $user['username'] . "<br>";

echo "Getting level progress...<br>";
$lp = getLevelProgress($user['score']);
echo "Level: " . $lp['level'] . "<br>";

echo "Getting alerts...<br>";
$open = getOpenAlertsCount();
echo "Open alerts: $open<br>";

echo "All good!";