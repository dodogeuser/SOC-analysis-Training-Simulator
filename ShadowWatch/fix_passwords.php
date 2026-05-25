<?php
require_once 'includes/db.php';

$hash = password_hash('password', PASSWORD_DEFAULT);

db()->execute("UPDATE users SET password_hash = ?", [$hash]);

echo "Done! All users now have password: password<br>";
echo "Hash used: " . $hash . "<br>";
echo "<br><a href='login.php'>Go to Login</a>";

// Delete this file after running!