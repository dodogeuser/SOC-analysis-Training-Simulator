<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$db = db();

// Filters
$userFilter   = sanitize($_GET['user'] ?? '');
$actionFilter = sanitize($_GET['action'] ?? '');
$dateFrom     = sanitize($_GET['from'] ?? '');
$dateTo       = sanitize($_GET['to'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 50;
$offset       = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($userFilter)   { $where[] = '(u.username LIKE ? OR al.user_id = ?)'; $params[] = "%$userFilter%"; $params[] = (int)$userFilter; }
if ($actionFilter) { $where[] = 'al.action = ?'; $params[] = $actionFilter; }
if ($dateFrom)     { $where[] = 'DATE(al.created_at) >= ?'; $params[] = $dateFrom; }
if ($dateTo)       { $where[] = 'DATE(al.created_at) <= ?'; $params[] = $dateTo; }

$whereSql = implode(' AND ', $where);
$total = (int)$db->query("SELECT COUNT(*) FROM activity_log al LEFT JOIN users u ON u.id=al.user_id WHERE $whereSql", $params)->fetchColumn();

$logs = $db->query(
    "SELECT al.*, u.username FROM activity_log al
     LEFT JOIN users u ON u.id = al.user_id
     WHERE $whereSql
     ORDER BY al.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $offset])
)->fetchAll();

$totalPages = max(1, ceil($total / $perPage));

// Distinct actions for filter dropdown
$actions = $db->query("SELECT DISTINCT action FROM activity_log ORDER BY action")->fetchAll(\PDO::FETCH_COLUMN);

// Stats
$todayCount  = (int)$db->query("SELECT COUNT(*) FROM activity_log WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$uniqueUsers = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM activity_log WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$totalLogs   = (int)$db->query("SELECT COUNT(*) FROM activity_log")->fetchColumn();

// Handle clear
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_old') {
    validateCsrfToken($_POST['_csrf'] ?? '');
    $days = max(7, (int)($_POST['days'] ?? 30));
    $db->execute("DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)", [$days]);
    header('Location: logs.php?cleared=1');
    exit;
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-clipboard-list" style="color:var(--orange)"></i> ACTIVITY LOGS</h1>
        <p class="page-subtitle">Admin Panel — <?= number_format($total) ?> entries matching filter</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-warning btn-sm" onclick="openModal('clearModal')">
            <i class="fas fa-trash"></i> Clear Old Logs
        </button>
        <a href="logs.php" class="btn btn-secondary btn-sm"><i class="fas fa-refresh"></i> Refresh</a>
    </div>
</div>

<?php if (isset($_GET['cleared'])): ?>
<div class="alert-banner success"><i class="fas fa-check-circle"></i> Old logs cleared.</div>
<?php endif; ?>

<!-- Stats row -->
<div class="stat-cards-row" style="margin-bottom:1rem">
    <div class="stat-card"><div class="stat-icon" style="color:var(--cyan)"><i class="fas fa-database"></i></div><div class="stat-body"><div class="stat-value"><?= number_format($totalLogs) ?></div><div class="stat-label">Total Log Entries</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="color:var(--green)"><i class="fas fa-calendar-day"></i></div><div class="stat-body"><div class="stat-value"><?= number_format($todayCount) ?></div><div class="stat-label">Today's Events</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="color:var(--orange)"><i class="fas fa-users"></i></div><div class="stat-body"><div class="stat-value"><?= $uniqueUsers ?></div><div class="stat-label">Active Users Today</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="color:var(--blue)"><i class="fas fa-filter"></i></div><div class="stat-body"><div class="stat-value"><?= number_format($total) ?></div><div class="stat-label">Filtered Results</div></div></div>
</div>

<!-- Filter -->
<div class="card" style="margin-bottom:1rem">
    <form method="GET" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;padding:.75rem 1rem">
        <input type="text" name="user" class="form-input" placeholder="Username or ID…"
               value="<?= sanitize($userFilter) ?>" style="width:180px">
        <select name="action" class="form-input" style="width:200px">
            <option value="">All Actions</option>
            <?php foreach ($actions as $a): ?>
            <option value="<?= sanitize($a) ?>" <?= $actionFilter===$a?'selected':'' ?>>
                <?= sanitize(str_replace('_',' ',$a)) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="from" class="form-input" value="<?= sanitize($dateFrom) ?>" style="width:150px" placeholder="From">
        <input type="date" name="to"   class="form-input" value="<?= sanitize($dateTo) ?>"   style="width:150px" placeholder="To">
        <button type="submit" class="btn btn-primary btn-sm">Apply</button>
        <a href="logs.php" class="btn btn-secondary btn-sm">Reset</a>
    </form>
</div>

<!-- Table -->
<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="mono" style="font-size:.75rem;white-space:nowrap;color:var(--text-muted)">
                        <?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?>
                    </td>
                    <td>
                        <?php if ($log['username']): ?>
                        <a href="../profile.php?id=<?= $log['user_id'] ?>" style="color:var(--cyan);font-size:.82rem">
                            <?= sanitize($log['username']) ?>
                        </a>
                        <?php else: ?>
                        <span class="text-muted" style="font-size:.78rem">#<?= $log['user_id'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="action-tag action-<?= getActionClass($log['action']) ?>">
                            <?= sanitize(str_replace('_',' ',$log['action'])) ?>
                        </span>
                    </td>
                    <td style="font-size:.8rem;color:var(--text-secondary);max-width:360px">
                        <?= sanitize($log['details'] ?? '') ?>
                    </td>
                    <td class="mono" style="font-size:.75rem;color:var(--text-muted)">
                        <?= sanitize($log['ip_address'] ?? '') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$logs): ?>
                <tr><td colspan="5" class="empty-state"><i class="fas fa-ghost"></i><p>No log entries found.</p></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php
        $qs = http_build_query(['user'=>$userFilter,'action'=>$actionFilter,'from'=>$dateFrom,'to'=>$dateTo]);
        for ($p = 1; $p <= min($totalPages, 20); $p++):
        ?>
            <a href="?page=<?= $p ?>&<?= $qs ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Clear Modal -->
<div id="clearModal" class="modal-overlay" style="display:none">
    <div class="modal" style="max-width:400px">
        <div class="modal-header">
            <h3 class="modal-title">Clear Old Logs</h3>
            <button onclick="closeModal('clearModal')" class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= getCsrfToken() ?>">
                <input type="hidden" name="action" value="clear_old">
                <p style="color:var(--text-muted);margin-bottom:1rem;font-size:.88rem">
                    This will permanently delete activity log entries older than the selected threshold.
                </p>
                <div class="form-group">
                    <label class="form-label">Delete logs older than</label>
                    <select name="days" class="form-input">
                        <option value="7">7 days</option>
                        <option value="14">14 days</option>
                        <option value="30" selected>30 days</option>
                        <option value="60">60 days</option>
                        <option value="90">90 days</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('clearModal')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-warning" onclick="return confirm('Are you sure? This cannot be undone.')">
                    <i class="fas fa-trash"></i> Clear Logs
                </button>
            </div>
        </form>
    </div>
</div>

<?php
function getActionClass($action) {
    if (str_contains($action, 'login'))    return 'auth';
    if (str_contains($action, 'admin'))    return 'admin';
    if (str_contains($action, 'resolve') || str_contains($action, 'ack'))  return 'resolve';
    if (str_contains($action, 'create'))   return 'create';
    if (str_contains($action, 'generate')) return 'generate';
    return 'default';
}
?>

<style>
.stat-cards-row { display:grid; grid-template-columns:repeat(4,1fr); gap:.75rem; }
.stat-card { background:var(--bg-card); border:1px solid var(--border); border-radius:8px; padding:1rem; display:flex; align-items:center; gap:.75rem; }
.stat-icon { font-size:1.5rem; }
.stat-value { font-size:1.4rem; font-family:'Orbitron',sans-serif; color:var(--text-primary); }
.stat-label { font-size:.7rem; color:var(--text-muted); }
.action-tag { padding:2px 8px; border-radius:4px; font-size:.68rem; white-space:nowrap; }
.action-auth     { background:rgba(0,100,255,.15); color:#6fa8ff; }
.action-admin    { background:rgba(255,56,96,.15);  color:var(--red); }
.action-resolve  { background:rgba(0,255,136,.1);   color:var(--green); }
.action-create   { background:rgba(0,212,255,.1);   color:var(--cyan); }
.action-generate { background:rgba(255,165,0,.1);   color:var(--orange); }
.action-default  { background:var(--bg-card); color:var(--text-muted); }
.pagination { display:flex; gap:.3rem; padding:1rem; justify-content:center; flex-wrap:wrap; }
.page-btn { padding:.3rem .7rem; background:var(--bg-card); border:1px solid var(--border); border-radius:4px; color:var(--text-secondary); text-decoration:none; font-size:.82rem; }
.page-btn.active { background:var(--cyan); color:#000; border-color:var(--cyan); }
.alert-banner { padding:.75rem 1rem; border-radius:6px; margin-bottom:1rem; display:flex; align-items:center; gap:.5rem; font-size:.85rem; }
.alert-banner.success { background:rgba(0,255,136,.1); border:1px solid var(--green); color:var(--green); }
</style>

<?php include '../includes/footer.php'; ?>
