<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$db = db();
$success = '';
$error   = '';

// Platform stats
$stats = [
    'users'     => (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn(),
    'alerts'    => (int)$db->query("SELECT COUNT(*) FROM alerts")->fetchColumn(),
    'open'      => (int)$db->query("SELECT COUNT(*) FROM alerts WHERE status='open'")->fetchColumn(),
    'incidents' => (int)$db->query("SELECT COUNT(*) FROM incidents")->fetchColumn(),
    'logs'      => (int)$db->query("SELECT COUNT(*) FROM system_logs")->fetchColumn(),
    'scenarios' => (int)$db->query("SELECT COUNT(*) FROM scenarios WHERE is_active=1")->fetchColumn(),
];

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken($_POST['_csrf'] ?? '');
    $action = $_POST['action'] ?? '';

    if ($action === 'bulk_generate') {
        $count = min(50, max(1, (int)($_POST['count'] ?? 5)));
        $generated = 0;
        for ($i = 0; $i < $count; $i++) {
            $alert = generateRandomAlert();
            if ($alert) $generated++;
        }
        logActivity($_SESSION['user_id'], 'admin_bulk_generate', "Generated $generated alerts");
        $success = "Generated $generated random alerts.";

    } elseif ($action === 'reset_alerts') {
        $status = sanitize($_POST['reset_status'] ?? 'resolved');
        $days   = max(0, (int)($_POST['older_than_days'] ?? 0));
        if ($days > 0) {
            $db->execute(
                "UPDATE alerts SET status='resolved', resolved_at=NOW() WHERE status='open' AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            );
        } else {
            $db->execute("UPDATE alerts SET status='resolved', resolved_at=NOW() WHERE status='open'");
        }
        logActivity($_SESSION['user_id'], 'admin_reset_alerts', "Bulk resolved open alerts (older_than={$days}d)");
        $success = 'Open alerts bulk-resolved.';

    } elseif ($action === 'clear_logs') {
        $days = max(1, (int)($_POST['log_days'] ?? 30));
        $db->execute("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)", [$days]);
        logActivity($_SESSION['user_id'], 'admin_clear_logs', "Cleared system_logs older than $days days");
        $success = "System logs older than $days days cleared.";

    } elseif ($action === 'clear_score_history') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid) {
            $db->execute("DELETE FROM score_events WHERE user_id=?", [$uid]);
            $db->execute("UPDATE users SET score=0, level=1 WHERE id=?", [$uid]);
            logActivity($_SESSION['user_id'], 'admin_reset_score', "Reset score for user #$uid");
            $success = "Score reset for user #$uid.";
        } else {
            $error = 'No user specified.';
        }

    } elseif ($action === 'close_incidents') {
        $db->execute("UPDATE incidents SET status='closed', closed_at=NOW() WHERE status NOT IN ('closed')");
        logActivity($_SESSION['user_id'], 'admin_close_incidents', 'Bulk-closed all open incidents');
        $success = 'All incidents closed.';
    }

    // Refresh stats
    $stats = [
        'users'     => (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn(),
        'alerts'    => (int)$db->query("SELECT COUNT(*) FROM alerts")->fetchColumn(),
        'open'      => (int)$db->query("SELECT COUNT(*) FROM alerts WHERE status='open'")->fetchColumn(),
        'incidents' => (int)$db->query("SELECT COUNT(*) FROM incidents")->fetchColumn(),
        'logs'      => (int)$db->query("SELECT COUNT(*) FROM system_logs")->fetchColumn(),
        'scenarios' => (int)$db->query("SELECT COUNT(*) FROM scenarios WHERE is_active=1")->fetchColumn(),
    ];
}

// User list for score reset dropdown
$users = $db->query("SELECT id, username, score, role FROM users WHERE is_active=1 ORDER BY username")->fetchAll();

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-cogs" style="color:var(--cyan)"></i> PLATFORM SETTINGS</h1>
        <p class="page-subtitle">Admin Panel — System configuration &amp; maintenance</p>
    </div>
</div>

<?php if ($success): ?>
<div class="alert-banner success"><i class="fas fa-check-circle"></i> <?= sanitize($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert-banner error"><i class="fas fa-exclamation-triangle"></i> <?= sanitize($error) ?></div>
<?php endif; ?>

<!-- Platform Stats -->
<div class="admin-stat-grid">
    <div class="astat-card">
        <i class="fas fa-users"></i>
        <div class="astat-val"><?= $stats['users'] ?></div>
        <div class="astat-label">Active Users</div>
    </div>
    <div class="astat-card">
        <i class="fas fa-bell" style="color:var(--orange)"></i>
        <div class="astat-val"><?= $stats['alerts'] ?></div>
        <div class="astat-label">Total Alerts</div>
    </div>
    <div class="astat-card">
        <i class="fas fa-exclamation-circle" style="color:var(--red)"></i>
        <div class="astat-val"><?= $stats['open'] ?></div>
        <div class="astat-label">Open Alerts</div>
    </div>
    <div class="astat-card">
        <i class="fas fa-ticket-alt" style="color:var(--blue)"></i>
        <div class="astat-val"><?= $stats['incidents'] ?></div>
        <div class="astat-label">Incidents</div>
    </div>
    <div class="astat-card">
        <i class="fas fa-scroll" style="color:var(--cyan)"></i>
        <div class="astat-val"><?= number_format($stats['logs']) ?></div>
        <div class="astat-label">Log Entries</div>
    </div>
    <div class="astat-card">
        <i class="fas fa-dragon" style="color:var(--green)"></i>
        <div class="astat-val"><?= $stats['scenarios'] ?></div>
        <div class="astat-label">Active Scenarios</div>
    </div>
</div>

<div class="settings-actions-grid">

    <!-- Bulk Alert Generator -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-bolt" style="color:var(--yellow)"></i> Bulk Alert Generator</h3>
        </div>
        <form method="POST" class="action-form">
            <input type="hidden" name="_csrf" value="<?= getCsrfToken() ?>">
            <input type="hidden" name="action" value="bulk_generate">
            <p class="action-desc">Generate random alerts from active scenarios to populate the SOC queue for analyst training.</p>
            <div class="form-group">
                <label class="form-label">Number of Alerts to Generate</label>
                <input type="number" name="count" class="form-input" value="10" min="1" max="50">
                <small class="form-hint">Maximum 50 alerts per batch.</small>
            </div>
            <button type="submit" class="btn btn-warning w-100">
                <i class="fas fa-bolt"></i> Generate Alerts
            </button>
        </form>
    </div>

    <!-- Bulk Resolve Alerts -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-check-double" style="color:var(--green)"></i> Bulk Resolve Alerts</h3>
        </div>
        <form method="POST" class="action-form" onsubmit="return confirm('Resolve all open alerts? This cannot be undone.')">
            <input type="hidden" name="_csrf" value="<?= getCsrfToken() ?>">
            <input type="hidden" name="action" value="reset_alerts">
            <p class="action-desc">Mark open alerts as resolved. Useful for resetting the queue after training sessions.</p>
            <div class="form-group">
                <label class="form-label">Older than (days) — 0 = all</label>
                <input type="number" name="older_than_days" class="form-input" value="0" min="0">
            </div>
            <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-check-double"></i> Resolve Open Alerts
            </button>
        </form>
    </div>

    <!-- Clear System Logs -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-trash" style="color:var(--red)"></i> Clear System Logs</h3>
        </div>
        <form method="POST" class="action-form" onsubmit="return confirm('Delete old system logs? Cannot be undone.')">
            <input type="hidden" name="_csrf" value="<?= getCsrfToken() ?>">
            <input type="hidden" name="action" value="clear_logs">
            <p class="action-desc">Remove old system log entries from the database to free up space.</p>
            <div class="form-group">
                <label class="form-label">Delete Logs Older Than (days)</label>
                <select name="log_days" class="form-input">
                    <option value="7">7 days</option>
                    <option value="14">14 days</option>
                    <option value="30" selected>30 days</option>
                    <option value="60">60 days</option>
                    <option value="90">90 days</option>
                </select>
            </div>
            <button type="submit" class="btn btn-danger w-100">
                <i class="fas fa-trash"></i> Clear System Logs
            </button>
        </form>
    </div>

    <!-- Reset User Score -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-undo" style="color:var(--orange)"></i> Reset User Score</h3>
        </div>
        <form method="POST" class="action-form" onsubmit="return confirm('Reset this user\'s score to 0? This cannot be undone.')">
            <input type="hidden" name="_csrf" value="<?= getCsrfToken() ?>">
            <input type="hidden" name="action" value="clear_score_history">
            <p class="action-desc">Reset a specific analyst's score and level to zero. Score events history will be cleared.</p>
            <div class="form-group">
                <label class="form-label">Select User</label>
                <select name="user_id" class="form-input" required>
                    <option value="">— Choose analyst —</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>">
                        <?= sanitize($u['username']) ?> (<?= number_format($u['score']) ?> pts)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-danger w-100">
                <i class="fas fa-undo"></i> Reset Score
            </button>
        </form>
    </div>

    <!-- Close All Incidents -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-times-circle" style="color:var(--red)"></i> Close All Incidents</h3>
        </div>
        <form method="POST" class="action-form" onsubmit="return confirm('Close ALL open incidents? Cannot be undone.')">
            <input type="hidden" name="_csrf" value="<?= getCsrfToken() ?>">
            <input type="hidden" name="action" value="close_incidents">
            <p class="action-desc">Mark all non-closed incident tickets as closed. Use at the end of a training session.</p>
            <div class="danger-box">
                <i class="fas fa-exclamation-triangle"></i>
                This will close <strong><?= $stats['incidents'] ?></strong> incidents immediately.
            </div>
            <button type="submit" class="btn btn-danger w-100" style="margin-top:1rem">
                <i class="fas fa-times-circle"></i> Close All Incidents
            </button>
        </form>
    </div>

    <!-- Quick Links -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-link"></i> Admin Quick Links</h3>
        </div>
        <div class="action-form">
            <div class="quick-links">
                <a href="users.php" class="quick-link">
                    <i class="fas fa-users"></i>
                    <span>User Management</span>
                    <i class="fas fa-chevron-right ml-auto"></i>
                </a>
                <a href="scenarios.php" class="quick-link">
                    <i class="fas fa-dragon"></i>
                    <span>Scenario Management</span>
                    <i class="fas fa-chevron-right ml-auto"></i>
                </a>
                <a href="logs.php" class="quick-link">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Activity Logs</span>
                    <i class="fas fa-chevron-right ml-auto"></i>
                </a>
                <a href="../leaderboard.php" class="quick-link">
                    <i class="fas fa-trophy"></i>
                    <span>Leaderboard</span>
                    <i class="fas fa-chevron-right ml-auto"></i>
                </a>
                <a href="../dashboard.php" class="quick-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Main Dashboard</span>
                    <i class="fas fa-chevron-right ml-auto"></i>
                </a>
                <a href="../about.php" class="quick-link" target="_blank">
                    <i class="fas fa-info-circle"></i>
                    <span>About Page</span>
                    <i class="fas fa-chevron-right ml-auto"></i>
                </a>
            </div>
        </div>
    </div>

</div>

<style>
.admin-stat-grid { display:grid; grid-template-columns:repeat(6,1fr); gap:.75rem; margin-bottom:1.5rem; }
.astat-card { background:var(--bg-panel); border:1px solid var(--border); border-radius:10px; padding:1.25rem; text-align:center; }
.astat-card i { font-size:1.5rem; color:var(--cyan); margin-bottom:.5rem; display:block; }
.astat-val { font-family:'Orbitron',sans-serif; font-size:1.6rem; color:var(--text-primary); }
.astat-label { font-size:.7rem; color:var(--text-muted); margin-top:.2rem; }
.settings-actions-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:1.25rem; }
.action-form { padding:1.25rem; }
.action-desc { font-size:.82rem; color:var(--text-muted); line-height:1.6; margin-bottom:1rem; }
.danger-box { background:rgba(255,56,96,.1); border:1px solid var(--red); border-radius:6px; padding:.75rem; font-size:.82rem; color:var(--red); display:flex; align-items:center; gap:.5rem; }
.btn-danger { background:rgba(255,56,96,.15); color:var(--red); border:1px solid var(--red); }
.btn-danger:hover { background:var(--red); color:#fff; }
.w-100 { width:100%; }
.quick-links { display:flex; flex-direction:column; gap:.3rem; }
.quick-link { display:flex; align-items:center; gap:.75rem; padding:.6rem .75rem; border-radius:6px; color:var(--text-secondary); text-decoration:none; font-size:.85rem; transition:background .15s, color .15s; }
.quick-link:hover { background:var(--bg-card); color:var(--cyan); }
.quick-link i:first-child { width:16px; color:var(--text-muted); }
.ml-auto { margin-left:auto; font-size:.7rem; }
.alert-banner { padding:.75rem 1rem; border-radius:6px; margin-bottom:1rem; display:flex; align-items:center; gap:.5rem; font-size:.85rem; }
.alert-banner.success { background:rgba(0,255,136,.1); border:1px solid var(--green); color:var(--green); }
.alert-banner.error   { background:rgba(255,56,96,.1);  border:1px solid var(--red);   color:var(--red); }
@media(max-width:1200px){ .admin-stat-grid{grid-template-columns:repeat(3,1fr);} .settings-actions-grid{grid-template-columns:repeat(2,1fr);} }
@media(max-width:768px){ .admin-stat-grid{grid-template-columns:repeat(2,1fr);} .settings-actions-grid{grid-template-columns:1fr;} }
</style>

<?php include '../includes/footer.php'; ?>
