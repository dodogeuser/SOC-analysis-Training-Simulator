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
    $password = $_POST['password'] ?? '';
    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password.';
    } else {
        $result = loginUser($username, $password);
        if ($result['success']) {
            $redirect = $_GET['redirect'] ?? '/shadowwatch/dashboard.php';
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out') {
    $success = 'You have been logged out successfully.';
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — ShadowWatch SOC</title>
    <link rel="stylesheet" href="/shadowwatch/assets/css/app.css">
    <style>
        .demo-creds {
            background: rgba(0,212,255,0.05);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 12px;
            margin-top: 20px;
            font-family: var(--font-mono);
            font-size: 11px;
            color: var(--text-secondary);
        }
        .demo-creds strong { color: var(--accent-cyan); }
        .animated-logo {
            animation: logo-glow 3s ease-in-out infinite;
        }
        @keyframes logo-glow {
            0%, 100% { box-shadow: 0 0 20px rgba(0,212,255,0.3); }
            50% { box-shadow: 0 0 40px rgba(0,212,255,0.6); }
        }
        .hex-bg {
            position: fixed; inset: 0;
            overflow: hidden;
            pointer-events: none;
        }
        .hex {
            position: absolute;
            border: 1px solid rgba(0,212,255,0.06);
            width: 80px; height: 80px;
            transform: rotate(30deg);
            animation: hex-float linear infinite;
        }
        @keyframes hex-float {
            from { transform: rotate(30deg) translateY(0); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            to { transform: rotate(30deg) translateY(-100vh); opacity: 0; }
        }
    </style>
</head>
<body>
    <div class="auth-bg-grid"></div>
    <div class="hex-bg" id="hexBg"></div>

    <div class="auth-page">
        <div class="auth-card">
            <div class="auth-logo">
                <div class="auth-logo-icon animated-logo">⬡</div>
                <div class="auth-title">SHADOWWATCH</div>
                <div class="auth-subtitle">SOC TRAINING SIMULATOR v1.0</div>
            </div>

            <?php if ($error): ?>
                <div class="alert-bar error">✕ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert-bar success">✓ <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">

                <div class="form-group">
                    <label class="form-label">Username or Email</label>
                    <input type="text" name="username" class="form-control" 
                           placeholder="analyst@shadowwatch.local" 
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           autocomplete="username" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" 
                           placeholder="••••••••" 
                           autocomplete="current-password" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px;">
                    ▶ AUTHENTICATE
                </button>
            </form>

            <div class="auth-divider">or</div>

            <div style="text-align:center">
                <a href="/shadowwatch/register.php" style="color:var(--text-secondary);font-size:13px;">
                    No account? <span style="color:var(--accent-cyan)">Register as Analyst</span>
                </a>
            </div>

            <div class="demo-creds">
                <div style="margin-bottom:6px;color:var(--accent-cyan)">◈ DEMO CREDENTIALS</div>
                <div><strong>Admin:</strong> admin / password</div>
                <div><strong>Analyst:</strong> jsmith / password</div>
                <div><strong>Junior:</strong> alee / password</div>
            </div>
        </div>
    </div>

    <script>
        // Floating hex elements
        const hexBg = document.getElementById('hexBg');
        for (let i = 0; i < 15; i++) {
            const hex = document.createElement('div');
            hex.className = 'hex';
            hex.style.left = Math.random() * 100 + 'vw';
            hex.style.top = Math.random() * 100 + 'vh';
            hex.style.animationDuration = (15 + Math.random() * 20) + 's';
            hex.style.animationDelay = (Math.random() * 10) + 's';
            hex.style.width = hex.style.height = (40 + Math.random() * 60) + 'px';
            hexBg.appendChild(hex);
        }
    </script>
</body>
</html>
