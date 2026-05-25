<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: /shadowwatch/dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken($_POST['_csrf'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($username) < 3 || strlen($username) > 30) {
        $error = 'Username must be 3-30 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username can only contain letters, numbers, and underscores.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $result = registerUser($username, $email, $password);
        if ($result['success']) {
            $success = 'Account created! You can now login.';
        } else {
            $error = $result['message'];
        }
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — ShadowWatch SOC</title>
    <link rel="stylesheet" href="/shadowwatch/assets/css/app.css">
</head>
<body>
    <div class="auth-bg-grid"></div>
    <div class="auth-page">
        <div class="auth-card">
            <div class="auth-logo">
                <div class="auth-logo-icon">⬡</div>
                <div class="auth-title">SHADOWWATCH</div>
                <div class="auth-subtitle">ANALYST REGISTRATION</div>
            </div>

            <?php if ($error): ?>
                <div class="alert-bar error">✕ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert-bar success">✓ <?= htmlspecialchars($success) ?>
                    <a href="/shadowwatch/login.php" style="color:var(--accent-cyan);margin-left:8px;">Login →</a>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">

                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control"
                           placeholder="analyst_name"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           autocomplete="username" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control"
                           placeholder="you@company.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           autocomplete="email" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control"
                           placeholder="Min 8 characters"
                           autocomplete="new-password" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control"
                           placeholder="Repeat password"
                           autocomplete="new-password" required>
                </div>

                <div style="background:rgba(0,212,255,0.05);border:1px solid var(--border);border-radius:4px;padding:10px;margin-bottom:16px;font-family:var(--font-mono);font-size:11px;color:var(--text-secondary);">
                    ℹ New accounts start as <span style="color:var(--accent-cyan)">Junior Analyst</span>. Level up by resolving alerts and creating incidents.
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px;">
                    ▶ CREATE ANALYST ACCOUNT
                </button>
            </form>

            <div style="text-align:center;margin-top:16px;">
                <a href="/shadowwatch/login.php" style="color:var(--text-secondary);font-size:13px;">
                    Already enlisted? <span style="color:var(--accent-cyan)">Login</span>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
