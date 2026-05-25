<?php
require_once 'includes/db.php';

$user = db()->fetchOne("SELECT id, username, password_hash, is_active FROM users WHERE username = 'admin'");

echo "<pre>";
echo "User found: " . ($user ? "YES" : "NO") . "\n";
if ($user) {
    echo "ID: " . $user['id'] . "\n";
    echo "Username: " . $user['username'] . "\n";
    echo "is_active: " . $user['is_active'] . "\n";
    echo "Hash: " . $user['password_hash'] . "\n";
    echo "password_verify test: " . (password_verify('password', $user['password_hash']) ? "PASS ✓" : "FAIL ✗") . "\n";
}
echo "</pre>";