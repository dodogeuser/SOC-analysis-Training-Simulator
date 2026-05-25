<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /shadowwatch/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: /shadowwatch/dashboard.php?error=unauthorized');
        exit;
    }
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isSeniorAnalyst() {
    return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'senior_analyst']);
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return db()->fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
}

function loginUser($username, $password) {
    $user = db()->fetchOne("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1", [$username, $username]);
    if (!$user) return ['success' => false, 'message' => 'Invalid credentials'];
    if (!password_verify($password, $user['password_hash'])) {
        logActivity($user['id'], 'login_failed', 'Failed login attempt from ' . getClientIp());
        return ['success' => false, 'message' => 'Invalid credentials'];
    }
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['login_time'] = time();
    regenerateSession();
    db()->execute("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
    logActivity($user['id'], 'login', 'Successful login from ' . getClientIp());
    return ['success' => true, 'user' => $user];
}

function registerUser($username, $email, $password) {
    $existing = db()->fetchOne("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
    if ($existing) return ['success' => false, 'message' => 'Username or email already taken'];
    if (strlen($password) < 8) return ['success' => false, 'message' => 'Password must be at least 8 characters'];
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $id = db()->insert("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)", [$username, $email, $hash]);
    logActivity($id, 'register', 'New account registered');
    return ['success' => true, 'user_id' => $id];
}

function logoutUser() {
    if (isLoggedIn()) {
        logActivity($_SESSION['user_id'], 'logout', 'User logged out');
    }
    destroySession();
}

function getClientIp() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function logActivity($userId, $action, $details = '') {
    try {
        db()->execute("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)",
            [$userId, $action, $details, getClientIp()]);
    } catch (Exception $e) { /* silent */ }
}
