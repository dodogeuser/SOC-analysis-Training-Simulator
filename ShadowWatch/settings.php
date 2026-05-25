<?php
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$db = db();
$user = $db->query("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']])->fetch();

$success = '';
$error   = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken($_POST['_csrf'] ?? '');

    $formAction = $_POST['form_action'] ?? '';

    if ($formAction === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $db->execute("UPDATE users SET password_hash = ? WHERE id = ?", [$hash, $_SESSION['user_id']]);
            logActivity($_SESSION['user_id'], 'change_password', 'Password changed');
            $success = 'Password updated successfully.';
        }
    } elseif ($formAction === 'update_profile') {
        $email = sanitize($_POST['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } else {
            $exists = $db->query(
                "SELECT id FROM users WHERE email = ? AND id != ?",
                [$email, $_SESSION['user_id']]
            )->fetch();

            if ($exists) {
                $error = 'Email address already in use.';
            } else {
                $db->execute("UPDATE users SET email = ? WHERE id = ?", [$email, $_SESSION['user_id']]);
                logActivity($_SESSION['user_id'], 'update_profile', "Updated email to $email");
                $success = 'Profile updated.';
                $user['email'] = $email;
            }
        }
    } elseif ($formAction === 'update_preferences') {
        $alertSound   = isset($_POST['alert_sound']) ? 1 : 0;
        $compactView  = isset($_POST['compact_view']) ? 1 : 0;
        $autoRefresh  = isset($_POST['auto_refresh']) ? 1 : 0;

        $prefs = json_encode([
            'alert_sound'  => $alertSound,
            'compact_view' => $compactView,
            'auto_refresh' => $autoRefresh,
        ]);

        $db->execute("UPDATE users SET preferences = ? WHERE id = ?", [$prefs, $_SESSION['user_id']]);
        $success = 'Preferences saved.';
    }

    $user = $db->query("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']])->fetch();
}

$prefs = json_decode($user['preferences'] ?? '{}', true) ?: [];

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">SETTINGS</h1>
        <p class="page-subtitle">Account &amp; preferences</p>
    </div>
</div>

<?php if ($success): ?>
<div class="alert-banner success"><i class="fas fa-check-circle"></i> <?= sanitize($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert-banner error"><i class="fas fa-exclamation-triangle"></i> <?= sanitize($error) ?></div>
<?php endif; ?>

<div class="settings-grid">

    <!-- Profile Info -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-user"></i> Profile Information</h3>
        </div>
        <form method="POST" class="settings-form">
            <input type="hidden" name="_csrf" value="<?= getCsrfToken() ?>">
            <input type="hidden" name="form_action" value="update_profile">

            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" class="form-input" value="<?= sanitize($user['username']) ?>" disabled>
                <small class="form-hint">Username cannot be changed.</small>
            </div>

            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-input"
                       value="<?= sanitize($user['email']) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Role</label>
                <input type="text" class="form-input"
                       value="<?= str_replace('_', ' ', strtoupper($user['role'])) ?>" disabled>
            </div>

            <div class="form-group">
                <label class="form-label">Account Created</label>
                <input type="text" class="form-input"
                       value="<?= date('F j, Y', strtotime($user['created_at'])) ?>" disabled>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </form>
    </div>

    <!-- Change Password -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-lock"></i> Change Password</h3>
        </div>
        <form method="POST" class="settings-form" id="pwForm">
            <input type="hidden" name="_csrf" value="<?= getCsrfToken() ?>">
            <input type="hidden" name="form_action" value="change_password">

            <div class="form-group">
                <label class="form-label">Current Password</label>
                <div class="input-pw-wrap">
                    <input type="password" name="current_password" class="form-input" required id="cur_pw">
                    <button type="button" class="pw-toggle" onclick="togglePw('cur_pw',this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">New Password</label>
                <div class="input-pw-wrap">
                    <input type="password" name="new_password" class="form-input" required
                           id="new_pw" minlength="8" oninput="checkStrength(this.value)">
                    <button type="button" class="pw-toggle" onclick="togglePw('new_pw',this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="pw-strength-bar"><div id="pwStrengthFill"></div></div>
                <small class="form-hint" id="pwStrengthLabel">Min. 8 characters</small>
            </div>

            <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <div class="input-pw-wrap">
                    <input type="password" name="confirm_password" class="form-input" required id="con_pw">
                    <button type="button" class="pw-toggle" onclick="togglePw('con_pw',this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-warning">
                <i class="fas fa-key"></i> Update Password
            </button>
        </form>
    </div>

    <!-- Preferences -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-sliders-h"></i> Preferences</h3>
        </div>
        <form method="POST" class="settings-form">
            <input type="hidden" name="_csrf" value="<?= getCsrfToken() ?>">
            <input type="hidden" name="form_action" value="update_preferences">

            <div class="pref-row">
                <div class="pref-info">
                    <span class="pref-label">Alert Sound</span>
                    <span class="pref-desc">Play audio on new critical alerts</span>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="alert_sound" <?= !empty($prefs['alert_sound']) ? 'checked' : '' ?>>
                    <span class="toggle-track"><span class="toggle-thumb"></span></span>
                </label>
            </div>

            <div class="pref-row">
                <div class="pref-info">
                    <span class="pref-label">Compact View</span>
                    <span class="pref-desc">Reduce table row height</span>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="compact_view" <?= !empty($prefs['compact_view']) ? 'checked' : '' ?>>
                    <span class="toggle-track"><span class="toggle-thumb"></span></span>
                </label>
            </div>

            <div class="pref-row">
                <div class="pref-info">
                    <span class="pref-label">Auto Refresh</span>
                    <span class="pref-desc">Refresh alert count every 30s</span>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="auto_refresh" <?= !empty($prefs['auto_refresh']) ? 'checked' : '' ?>>
                    <span class="toggle-track"><span class="toggle-thumb"></span></span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top:1rem">
                <i class="fas fa-save"></i> Save Preferences
            </button>
        </form>
    </div>

    <!-- Account Info / Danger Zone -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-info-circle"></i> Account Info</h3>
        </div>
        <div class="settings-form">
            <div class="info-row">
                <span class="info-key">User ID</span>
                <span class="info-val">#<?= $user['id'] ?></span>
            </div>
            <div class="info-row">
                <span class="info-key">Score</span>
                <span class="info-val" style="color:var(--cyan)"><?= number_format($user['score']) ?> pts</span>
            </div>
            <div class="info-row">
                <span class="info-key">Last Login</span>
                <span class="info-val"><?= $user['last_login'] ? date('M j, Y H:i', strtotime($user['last_login'])) : 'N/A' ?></span>
            </div>
            <div class="info-row">
                <span class="info-key">Status</span>
                <span class="info-val">
                    <span class="status-badge status-<?= $user['is_active'] ? 'open' : 'closed' ?>">
                        <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </span>
            </div>

            <div style="margin-top:1.5rem; padding-top:1.5rem; border-top:1px solid var(--border);">
                <p style="color:var(--text-muted); font-size:.82rem; margin-bottom:1rem;">
                    Need help or want to report an issue? Contact your system administrator.
                </p>
                <a href="profile.php" class="btn btn-secondary">
                    <i class="fas fa-user"></i> View Profile
                </a>
            </div>
        </div>
    </div>

</div>

<style>
.settings-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:1.25rem; }
.settings-form { padding:1.25rem; }
.alert-banner { padding:.75rem 1rem; border-radius:6px; margin-bottom:1rem; display:flex; align-items:center; gap:.5rem; font-size:.85rem; }
.alert-banner.success { background:rgba(0,255,136,.1); border:1px solid var(--green); color:var(--green); }
.alert-banner.error   { background:rgba(255,56,96,.1);  border:1px solid var(--red);   color:var(--red); }
.form-hint { color:var(--text-muted); font-size:.72rem; }
.input-pw-wrap { position:relative; }
.input-pw-wrap .form-input { padding-right:2.5rem; }
.pw-toggle { position:absolute; right:.75rem; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; }
.pw-strength-bar { height:4px; background:var(--bg-card); border-radius:2px; margin-top:.4rem; overflow:hidden; }
.pw-strength-bar div { height:100%; width:0; border-radius:2px; transition:width .3s, background .3s; }
.pref-row { display:flex; justify-content:space-between; align-items:center; padding:.75rem 0; border-bottom:1px solid var(--border); }
.pref-row:last-of-type { border-bottom:none; }
.pref-label { display:block; font-size:.88rem; color:var(--text-primary); }
.pref-desc  { display:block; font-size:.73rem; color:var(--text-muted); margin-top:.1rem; }
.toggle-switch { position:relative; display:inline-block; cursor:pointer; }
.toggle-switch input { display:none; }
.toggle-track { display:block; width:44px; height:24px; background:var(--bg-card); border:1px solid var(--border); border-radius:12px; transition:background .2s; }
.toggle-switch input:checked ~ .toggle-track { background:var(--cyan); border-color:var(--cyan); }
.toggle-thumb { position:absolute; top:3px; left:3px; width:18px; height:18px; background:#fff; border-radius:50%; transition:left .2s; }
.toggle-switch input:checked ~ .toggle-track .toggle-thumb { left:23px; }
.info-row { display:flex; justify-content:space-between; padding:.5rem 0; border-bottom:1px solid var(--border); font-size:.85rem; }
.info-key { color:var(--text-muted); }
.info-val { color:var(--text-primary); font-family:'Share Tech Mono',monospace; }
@media(max-width:768px){ .settings-grid{grid-template-columns:1fr;} }
</style>

<script>
function togglePw(id, btn) {
    const el = document.getElementById(id);
    if (el.type === 'password') { el.type = 'text'; btn.innerHTML = '<i class="fas fa-eye-slash"></i>'; }
    else { el.type = 'password'; btn.innerHTML = '<i class="fas fa-eye"></i>'; }
}

function checkStrength(pw) {
    const fill  = document.getElementById('pwStrengthFill');
    const label = document.getElementById('pwStrengthLabel');
    let score = 0;
    if (pw.length >= 8)  score++;
    if (pw.length >= 12) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    const pct = (score / 5) * 100;
    const clr = score <= 1 ? 'var(--red)' : score <= 3 ? 'var(--orange)' : 'var(--green)';
    const lbl = score <= 1 ? 'Weak' : score <= 3 ? 'Moderate' : 'Strong';
    fill.style.width = pct + '%';
    fill.style.background = clr;
    label.textContent = lbl;
    label.style.color = clr;
}
</script>

<?php include 'includes/footer.php'; ?>
