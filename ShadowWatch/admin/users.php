<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$db = db();
$success = '';
$error   = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken($_POST['_csrf'] ?? '');
    $action = $_POST['action'] ?? '';

    if ($action === 'update_role') {
        $uid  = (int)$_POST['user_id'];
        $role = sanitize($_POST['role']);
        if (!in_array($role, ['analyst','senior_analyst','admin'])) {
            $error = 'Invalid role.';
        } elseif ($uid === (int)$_SESSION['user_id']) {
            $error = 'Cannot change your own role.';
        } else {
            $db->execute("UPDATE users SET role=? WHERE id=?", [$role, $uid]);
            logActivity($_SESSION['user_id'], 'admin_update_role', "Changed user #$uid role to $role");
            $success = 'Role updated.';
        }
    } elseif ($action === 'toggle_active') {
        $uid = (int)$_POST['user_id'];
        if ($uid === (int)$_SESSION['user_id']) {
            $error = 'Cannot deactivate yourself.';
        } else {
            $db->execute("UPDATE users SET is_active = NOT is_active WHERE id=?", [$uid]);
            logActivity($_SESSION['user_id'], 'admin_toggle_user', "Toggled active state for user #$uid");
            $success = 'User status updated.';
        }
    } elseif ($action === 'reset_password') {
        $uid     = (int)$_POST['user_id'];
        $newPass = sanitize($_POST['new_password'] ?? '');
        if (strlen($newPass) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $db->execute("UPDATE users SET password_hash=? WHERE id=?", [$hash, $uid]);
            logActivity($_SESSION['user_id'], 'admin_reset_password', "Reset password for user #$uid");
            $success = 'Password reset successfully.';
        }
    } elseif ($action === 'adjust_score') {
        $uid    = (int)$_POST['user_id'];
        $points = (int)$_POST['points'];
        $reason = sanitize($_POST['reason'] ?? 'Admin adjustment');
        $db->execute("UPDATE users SET score = GREATEST(0, score + ?) WHERE id=?", [$points, $uid]);
        awardPoints($uid, $points, 'admin_adjust', $uid, 'user', "Admin: $reason");
        logActivity($_SESSION['user_id'], 'admin_adjust_score', "Adjusted score for user #$uid by $points pts");
        $success = 'Score adjusted.';
    }
}

// Filters
$search = sanitize($_GET['search'] ?? '');
$roleFilter = sanitize($_GET['role'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];
if ($search) { $where[] = '(username LIKE ? OR email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($roleFilter) { $where[] = 'role = ?'; $params[] = $roleFilter; }

$whereSql = implode(' AND ', $where);
$total = (int)$db->query("SELECT COUNT(*) FROM users WHERE $whereSql", $params)->fetchColumn();
$users = $db->query("SELECT * FROM users WHERE $whereSql ORDER BY score DESC LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $offset]))->fetchAll();

$totalPages = max(1, ceil($total / $perPage));

// Summary stats
$stats = $db->query("SELECT role, COUNT(*) as cnt FROM users WHERE is_active=1 GROUP BY role")->fetchAll(\PDO::FETCH_KEY_PAIR);

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-shield-alt" style="color:var(--red)"></i> USER MANAGEMENT</h1>
        <p class="page-subtitle">Admin Panel &mdash; <?= $total ?> registered users</p>
    </div>
</div>

<?php if ($success): ?><div class="alert-banner success"><i class="fas fa-check-circle"></i> <?= sanitize($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert-banner error"><i class="fas fa-exclamation-triangle"></i> <?= sanitize($error) ?></div><?php endif; ?>

<!-- Summary -->
<div class="stat-cards-row" style="margin-bottom:1.25rem;">
    <div class="stat-card"><div class="stat-icon" style="color:var(--cyan)"><i class="fas fa-users"></i></div><div class="stat-body"><div class="stat-value"><?= $total ?></div><div class="stat-label">Total Users</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="color:var(--green)"><i class="fas fa-user-check"></i></div><div class="stat-body"><div class="stat-value"><?= array_sum($stats) ?></div><div class="stat-label">Active</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="color:var(--orange)"><i class="fas fa-user-graduate"></i></div><div class="stat-body"><div class="stat-value"><?= $stats['senior_analyst'] ?? 0 ?></div><div class="stat-label">Senior Analysts</div></div></div>
    <div class="stat-card"><div class="stat-icon" style="color:var(--red)"><i class="fas fa-user-shield"></i></div><div class="stat-body"><div class="stat-value"><?= $stats['admin'] ?? 0 ?></div><div class="stat-label">Admins</div></div></div>
</div>

<!-- Filter Bar -->
<div class="card" style="margin-bottom:1rem">
    <div class="filter-bar" style="padding:.75rem 1rem">
        <form method="GET" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
            <div class="search-wrap" style="flex:1;min-width:200px">
                <i class="fas fa-search search-icon"></i>
                <input type="text" name="search" class="form-input search-input"
                       placeholder="Search username or email…" value="<?= sanitize($search) ?>">
            </div>
            <select name="role" class="form-input" style="width:180px">
                <option value="">All Roles</option>
                <option value="analyst" <?= $roleFilter==='analyst'?'selected':'' ?>>Analyst</option>
                <option value="senior_analyst" <?= $roleFilter==='senior_analyst'?'selected':'' ?>>Senior Analyst</option>
                <option value="admin" <?= $roleFilter==='admin'?'selected':'' ?>>Admin</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a href="users.php" class="btn btn-secondary btn-sm">Reset</a>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Score</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr class="<?= !$u['is_active'] ? 'row-muted' : '' ?>">
                    <td class="mono">#<?= $u['id'] ?></td>
                    <td>
                        <a href="../profile.php?id=<?= $u['id'] ?>" style="color:var(--cyan)">
                            <?= sanitize($u['username']) ?>
                        </a>
                        <?php if ($u['id'] == $_SESSION['user_id']): ?>
                            <span class="badge badge-info" style="font-size:.55rem">YOU</span>
                        <?php endif; ?>
                    </td>
                    <td class="mono" style="font-size:.78rem"><?= sanitize($u['email']) ?></td>
                    <td>
                        <span class="role-pill role-<?= $u['role'] ?>">
                            <?= str_replace('_',' ',strtoupper($u['role'])) ?>
                        </span>
                    </td>
                    <td class="mono" style="color:var(--cyan)"><?= number_format($u['score']) ?></td>
                    <td>
                        <span class="status-badge status-<?= $u['is_active']?'open':'closed' ?>">
                            <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td style="font-size:.78rem;color:var(--text-muted)"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <div class="action-btns">
                            <button class="btn btn-xs btn-secondary" onclick='openEditModal(<?= json_encode($u) ?>)' title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="_csrf" value="<?= getCsrfToken() ?>">
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-xs <?= $u['is_active']?'btn-warning':'btn-success' ?>"
                                        title="<?= $u['is_active']?'Deactivate':'Activate' ?>">
                                    <i class="fas fa-<?= $u['is_active']?'ban':'check' ?>"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$users): ?>
                <tr><td colspan="8" class="empty-state"><i class="fas fa-users-slash"></i><p>No users found.</p></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($roleFilter) ?>"
               class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal-overlay" style="display:none">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <h3 class="modal-title">Edit User: <span id="editUsername"></span></h3>
            <button onclick="closeModal('editModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="tab-btns" style="margin-bottom:1rem">
                <button class="btn btn-sm btn-primary tab-btn active" onclick="switchTab('role')">Role</button>
                <button class="btn btn-sm btn-secondary tab-btn" onclick="switchTab('password')">Password</button>
                <button class="btn btn-sm btn-secondary tab-btn" onclick="switchTab('score')">Score</button>
            </div>

            <!-- Role Tab -->
            <div id="tab-role">
                <form method="POST">
                    <input type="hidden" name="_csrf" value="<?= getCsrfToken() ?>">
                    <input type="hidden" name="action" value="update_role">
                    <input type="hidden" name="user_id" id="editUserId">
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" id="editRole" class="form-input">
                            <option value="analyst">Analyst</option>
                            <option value="senior_analyst">Senior Analyst</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Update Role</button>
                </form>
            </div>

            <!-- Password Tab -->
            <div id="tab-password" style="display:none">
                <form method="POST">
                    <input type="hidden" name="_csrf" value="<?= getCsrfToken() ?>">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="editUserIdPw">
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="text" name="new_password" class="form-input" placeholder="Min. 6 characters" required>
                    </div>
                    <button type="submit" class="btn btn-warning w-100">Reset Password</button>
                </form>
            </div>

            <!-- Score Tab -->
            <div id="tab-score" style="display:none">
                <form method="POST">
                    <input type="hidden" name="_csrf" value="<?= getCsrfToken() ?>">
                    <input type="hidden" name="action" value="adjust_score">
                    <input type="hidden" name="user_id" id="editUserIdScore">
                    <div class="form-group">
                        <label class="form-label">Points Adjustment (use negative to deduct)</label>
                        <input type="number" name="points" class="form-input" value="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reason</label>
                        <input type="text" name="reason" class="form-input" placeholder="Admin adjustment reason">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Adjust Score</button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.stat-cards-row { display:grid; grid-template-columns:repeat(4,1fr); gap:.75rem; }
.stat-card { background:var(--bg-card); border:1px solid var(--border); border-radius:8px; padding:1rem; display:flex; align-items:center; gap:.75rem; }
.stat-icon { font-size:1.5rem; }
.stat-value { font-size:1.4rem; font-family:'Orbitron',sans-serif; color:var(--text-primary); }
.stat-label { font-size:.7rem; color:var(--text-muted); }
.role-pill { padding:2px 8px; border-radius:4px; font-size:.65rem; font-family:'Orbitron',sans-serif; }
.role-admin { background:rgba(255,56,96,.2); color:var(--red); border:1px solid var(--red); }
.role-senior_analyst { background:rgba(255,165,0,.2); color:var(--orange); border:1px solid var(--orange); }
.role-analyst { background:rgba(0,212,255,.1); color:var(--cyan); border:1px solid var(--cyan); }
.row-muted { opacity:.5; }
.action-btns { display:flex; gap:.3rem; }
.btn-xs { padding:.2rem .5rem; font-size:.7rem; }
.btn-success { background:var(--green); color:#000; }
.pagination { display:flex; gap:.3rem; padding:1rem; justify-content:center; }
.page-btn { padding:.3rem .7rem; background:var(--bg-card); border:1px solid var(--border); border-radius:4px; color:var(--text-secondary); text-decoration:none; font-size:.82rem; }
.page-btn.active { background:var(--cyan); color:#000; border-color:var(--cyan); }
.w-100 { width:100%; }
.alert-banner { padding:.75rem 1rem; border-radius:6px; margin-bottom:1rem; display:flex; align-items:center; gap:.5rem; font-size:.85rem; }
.alert-banner.success { background:rgba(0,255,136,.1); border:1px solid var(--green); color:var(--green); }
.alert-banner.error   { background:rgba(255,56,96,.1);  border:1px solid var(--red);   color:var(--red); }
.search-wrap { position:relative; }
.search-icon { position:absolute; left:.75rem; top:50%; transform:translateY(-50%); color:var(--text-muted); }
.search-input { padding-left:2.2rem; }
</style>

<script>
function openEditModal(user) {
    document.getElementById('editUsername').textContent = user.username;
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editUserIdPw').value = user.id;
    document.getElementById('editUserIdScore').value = user.id;
    document.getElementById('editRole').value = user.role;
    switchTab('role');
    document.getElementById('editModal').style.display = 'flex';
}

function switchTab(tab) {
    ['role','password','score'].forEach(t => {
        document.getElementById('tab-'+t).style.display = t === tab ? 'block' : 'none';
    });
    document.querySelectorAll('.tab-btn').forEach((btn,i) => {
        btn.className = 'btn btn-sm ' + (['role','password','score'][i] === tab ? 'btn-primary' : 'btn-secondary') + ' tab-btn';
    });
}
</script>

<?php include '../includes/footer.php'; ?>
