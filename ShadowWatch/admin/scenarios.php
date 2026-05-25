<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$db = db();
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken($_POST['_csrf'] ?? '');
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $id          = (int)($_POST['scenario_id'] ?? 0);
        $title       = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $difficulty  = sanitize($_POST['difficulty'] ?? 'medium');
        $severity    = sanitize($_POST['severity'] ?? 'high');
        $category    = sanitize($_POST['category'] ?? 'other');
        $points      = max(0, (int)($_POST['points'] ?? 50));
        $isActive    = isset($_POST['is_active']) ? 1 : 0;
        $alertTypes  = sanitize($_POST['alert_types'] ?? '');
        $ttc         = max(1, (int)($_POST['time_to_complete'] ?? 30));

        if (!$title) { $error = 'Title is required.'; }
        else {
            $data = [$title, $description, $difficulty, $severity, $category, $points, $isActive, $alertTypes, $ttc];
            if ($action === 'create') {
                $db->execute(
                    "INSERT INTO scenarios (title,description,difficulty,severity,category,points,is_active,alert_types,time_to_complete,created_at)
                     VALUES (?,?,?,?,?,?,?,?,?,NOW())",
                    $data
                );
                logActivity($_SESSION['user_id'], 'admin_create_scenario', "Created scenario: $title");
                $success = 'Scenario created.';
            } else {
                $db->execute(
                    "UPDATE scenarios SET title=?,description=?,difficulty=?,severity=?,category=?,points=?,is_active=?,alert_types=?,time_to_complete=? WHERE id=?",
                    array_merge($data, [$id])
                );
                logActivity($_SESSION['user_id'], 'admin_update_scenario', "Updated scenario #$id: $title");
                $success = 'Scenario updated.';
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['scenario_id'];
        $db->execute("UPDATE scenarios SET is_active = NOT is_active WHERE id=?", [$id]);
        $success = 'Scenario status toggled.';
    } elseif ($action === 'delete') {
        $id = (int)$_POST['scenario_id'];
        $db->execute("UPDATE scenarios SET is_active=0 WHERE id=?", [$id]);
        logActivity($_SESSION['user_id'], 'admin_delete_scenario', "Deactivated scenario #$id");
        $success = 'Scenario deactivated.';
    }
}

$scenarios = $db->query("SELECT * FROM scenarios ORDER BY created_at DESC")->fetchAll();
$counts = [
    'total'  => count($scenarios),
    'active' => count(array_filter($scenarios, fn($s) => $s['is_active'])),
];

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-dragon" style="color:var(--red)"></i> SCENARIO MANAGEMENT</h1>
        <p class="page-subtitle">Admin Panel — <?= $counts['active'] ?> active / <?= $counts['total'] ?> total scenarios</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('createModal')">
            <i class="fas fa-plus"></i> New Scenario
        </button>
    </div>
</div>

<?php if ($success): ?><div class="alert-banner success"><i class="fas fa-check-circle"></i> <?= sanitize($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert-banner error"><i class="fas fa-exclamation-triangle"></i> <?= sanitize($error) ?></div><?php endif; ?>

<div class="scenarios-grid">
    <?php foreach ($scenarios as $sc): ?>
    <div class="scenario-admin-card <?= !$sc['is_active'] ? 'inactive' : '' ?>">
        <div class="scard-header">
            <div>
                <span class="severity-badge severity-<?= $sc['severity'] ?>"><?= strtoupper($sc['severity']) ?></span>
                <span class="diff-badge diff-<?= $sc['difficulty'] ?>"><?= strtoupper($sc['difficulty']) ?></span>
            </div>
            <div class="scard-status">
                <?= $sc['is_active'] ? '<span style="color:var(--green)"><i class="fas fa-circle" style="font-size:.5rem"></i> Active</span>' : '<span style="color:var(--text-muted)"><i class="fas fa-circle" style="font-size:.5rem"></i> Inactive</span>' ?>
            </div>
        </div>

        <h3 class="scard-title"><?= sanitize($sc['title']) ?></h3>
        <p class="scard-desc"><?= sanitize($sc['description']) ?></p>

        <div class="scard-meta">
            <span><i class="fas fa-tag"></i> <?= sanitize($sc['category']) ?></span>
            <span><i class="fas fa-star"></i> <?= $sc['points'] ?> pts</span>
            <span><i class="fas fa-clock"></i> <?= $sc['time_to_complete'] ?>m</span>
        </div>

        <div class="scard-actions">
            <button class="btn btn-sm btn-secondary" onclick='openEditScenarioModal(<?= json_encode($sc) ?>)'>
                <i class="fas fa-edit"></i> Edit
            </button>
            <form method="POST" style="display:inline">
                <input type="hidden" name="_csrf" value="<?= getCsrfToken() ?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="scenario_id" value="<?= $sc['id'] ?>">
                <button type="submit" class="btn btn-sm <?= $sc['is_active']?'btn-warning':'btn-success' ?>">
                    <i class="fas fa-<?= $sc['is_active']?'pause':'play' ?>"></i>
                    <?= $sc['is_active']?'Disable':'Enable' ?>
                </button>
            </form>
            <button class="btn btn-sm btn-primary" onclick="generateFromScenario(<?= $sc['id'] ?>, '<?= addslashes($sc['title']) ?>')" title="Generate alert from this scenario">
                <i class="fas fa-bolt"></i>
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Create Modal -->
<div id="createModal" class="modal-overlay" style="display:none">
    <div class="modal" style="max-width:560px">
        <div class="modal-header">
            <h3 class="modal-title">Create New Scenario</h3>
            <button onclick="closeModal('createModal')" class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= getCsrfToken() ?>">
                <input type="hidden" name="action" value="create">
                <?= scenarioFormFields() ?>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('createModal')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editScenarioModal" class="modal-overlay" style="display:none">
    <div class="modal" style="max-width:560px">
        <div class="modal-header">
            <h3 class="modal-title">Edit Scenario</h3>
            <button onclick="closeModal('editScenarioModal')" class="modal-close">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= getCsrfToken() ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="scenario_id" id="editScId">
                <?= scenarioFormFields('edit') ?>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('editScenarioModal')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<?php
function scenarioFormFields($prefix = '') {
    $p = $prefix ? "edit_" : "";
    ob_start(); ?>
    <div class="form-group">
        <label class="form-label">Title *</label>
        <input type="text" name="title" id="<?= $p ?>sc_title" class="form-input" required placeholder="e.g. Ransomware Outbreak">
    </div>
    <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="description" id="<?= $p ?>sc_desc" class="form-input" rows="3" placeholder="Describe the attack scenario…"></textarea>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
        <div class="form-group">
            <label class="form-label">Difficulty</label>
            <select name="difficulty" id="<?= $p ?>sc_diff" class="form-input">
                <option value="beginner">Beginner</option>
                <option value="easy">Easy</option>
                <option value="medium" selected>Medium</option>
                <option value="hard">Hard</option>
                <option value="expert">Expert</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Severity</label>
            <select name="severity" id="<?= $p ?>sc_sev" class="form-input">
                <option value="info">Info</option>
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high" selected>High</option>
                <option value="critical">Critical</option>
            </select>
        </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
        <div class="form-group">
            <label class="form-label">Category</label>
            <select name="category" id="<?= $p ?>sc_cat" class="form-input">
                <option value="malware">Malware</option>
                <option value="phishing">Phishing</option>
                <option value="ddos">DDoS</option>
                <option value="insider_threat">Insider Threat</option>
                <option value="data_breach">Data Breach</option>
                <option value="ransomware">Ransomware</option>
                <option value="apt">APT</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Points Value</label>
            <input type="number" name="points" id="<?= $p ?>sc_pts" class="form-input" value="50" min="0" max="500">
        </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
        <div class="form-group">
            <label class="form-label">Time to Complete (min)</label>
            <input type="number" name="time_to_complete" id="<?= $p ?>sc_ttc" class="form-input" value="30" min="1">
        </div>
        <div class="form-group">
            <label class="form-label">Alert Types (comma-separated)</label>
            <input type="text" name="alert_types" id="<?= $p ?>sc_alert_types" class="form-input" placeholder="malware,network,auth">
        </div>
    </div>
    <div class="form-group">
        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
            <input type="checkbox" name="is_active" id="<?= $p ?>sc_active" value="1" checked>
            <span>Active (visible to analysts)</span>
        </label>
    </div>
<?php return ob_get_clean(); }
?>

<style>
.scenarios-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:1.25rem; }
.scenario-admin-card { background:var(--bg-panel); border:1px solid var(--border); border-radius:10px; padding:1.25rem; transition:border-color .2s; }
.scenario-admin-card:hover { border-color:var(--cyan); }
.scenario-admin-card.inactive { opacity:.55; }
.scard-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:.75rem; }
.scard-status { font-size:.75rem; }
.scard-title { font-family:'Orbitron',sans-serif; font-size:.9rem; color:var(--text-primary); margin-bottom:.4rem; }
.scard-desc { font-size:.8rem; color:var(--text-muted); line-height:1.5; margin-bottom:.75rem; min-height:2.4rem; }
.scard-meta { display:flex; gap:.75rem; font-size:.75rem; color:var(--text-muted); margin-bottom:.75rem; }
.scard-actions { display:flex; gap:.4rem; flex-wrap:wrap; }
.diff-badge { padding:2px 8px; border-radius:4px; font-size:.6rem; font-family:'Orbitron',sans-serif; }
.diff-beginner,.diff-easy { background:rgba(0,255,136,.1); color:var(--green); border:1px solid var(--green); }
.diff-medium { background:rgba(255,165,0,.1); color:var(--orange); border:1px solid var(--orange); }
.diff-hard,.diff-expert { background:rgba(255,56,96,.1); color:var(--red); border:1px solid var(--red); }
.btn-success { background:rgba(0,255,136,.2); color:var(--green); border:1px solid var(--green); }
.alert-banner { padding:.75rem 1rem; border-radius:6px; margin-bottom:1rem; display:flex; align-items:center; gap:.5rem; font-size:.85rem; }
.alert-banner.success { background:rgba(0,255,136,.1); border:1px solid var(--green); color:var(--green); }
.alert-banner.error   { background:rgba(255,56,96,.1);  border:1px solid var(--red);   color:var(--red); }
</style>

<script>
function openEditScenarioModal(sc) {
    document.getElementById('editScId').value = sc.id;
    document.getElementById('edit_sc_title').value = sc.title || '';
    document.getElementById('edit_sc_desc').value = sc.description || '';
    document.getElementById('edit_sc_diff').value = sc.difficulty || 'medium';
    document.getElementById('edit_sc_sev').value = sc.severity || 'high';
    document.getElementById('edit_sc_cat').value = sc.category || 'other';
    document.getElementById('edit_sc_pts').value = sc.points || 50;
    document.getElementById('edit_sc_ttc').value = sc.time_to_complete || 30;
    document.getElementById('edit_sc_alert_types').value = sc.alert_types || '';
    document.getElementById('edit_sc_active').checked = !!sc.is_active;
    document.getElementById('editScenarioModal').style.display = 'flex';
}

async function generateFromScenario(id, title) {
    if (!confirm(`Generate an alert from "${title}"?`)) return;
    const res = await apiPost('../api/generate_alert.php', { scenario_id: id, count: 1 });
    if (res.success) {
        showToast(`Alert generated from "${title}"`, 'success');
    } else {
        showToast(res.message || 'Failed', 'error');
    }
}
</script>

<?php include '../includes/footer.php'; ?>
