<?php
$pageTitle = 'Alert Console';
$currentPage = 'alerts';
require_once __DIR__ . '/includes/header.php';

$filterSeverity = sanitize($_GET['severity'] ?? '');
$filterStatus   = sanitize($_GET['status'] ?? '');
$filterCategory = sanitize($_GET['category'] ?? '');
$selectedId     = (int)($_GET['id'] ?? 0);

$where  = ['1=1'];
$params = [];
if ($filterSeverity) { $where[] = 'a.severity = ?'; $params[] = $filterSeverity; }
if ($filterStatus)   { $where[] = 'a.status = ?';   $params[] = $filterStatus; }
if ($filterCategory) { $where[] = 'a.category = ?'; $params[] = $filterCategory; }

$whereSql = implode(' AND ', $where);

$alerts = db()->fetchAll("SELECT a.*, s.points as scenario_points
    FROM alerts a
    LEFT JOIN scenarios s ON a.scenario_id = s.id
    WHERE $whereSql
    ORDER BY FIELD(a.severity,'critical','high','medium','low','info'), a.created_at DESC
    LIMIT 100", $params);

$selectedAlert = null;
if ($selectedId) {
    $selectedAlert = db()->fetchOne("SELECT a.*, s.points as scenario_points, s.description as scenario_desc
        FROM alerts a
        LEFT JOIN scenarios s ON a.scenario_id = s.id
        WHERE a.id = ?", [$selectedId]);
}

$analysts = db()->fetchAll("SELECT id, username FROM users WHERE is_active = 1 ORDER BY username");
?>

<div class="page-header">
    <div>
        <h1 class="page-title">ALERT CONSOLE</h1>
        <p class="page-subtitle"><?= count($alerts) ?> alerts loaded</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-secondary btn-sm" onclick="generateAlert()">
            <i class="fas fa-bolt"></i> Generate Alert
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:1rem">
    <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;padding:.75rem 1rem">
        <div style="position:relative;flex:1;min-width:180px">
            <i class="fas fa-search" style="position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:var(--text-muted)"></i>
            <input type="text" id="alert-search" class="form-input" placeholder="Search alerts..." style="padding-left:2.2rem" oninput="filterTable('alert-search','alert-table')">
        </div>
        <select class="form-input" style="width:140px" onchange="applyFilter('severity',this.value)">
            <option value="">All Severity</option>
            <option value="critical" <?= $filterSeverity==='critical'?'selected':'' ?>>Critical</option>
            <option value="high"     <?= $filterSeverity==='high'?'selected':'' ?>>High</option>
            <option value="medium"   <?= $filterSeverity==='medium'?'selected':'' ?>>Medium</option>
            <option value="low"      <?= $filterSeverity==='low'?'selected':'' ?>>Low</option>
        </select>
        <select class="form-input" style="width:140px" onchange="applyFilter('status',this.value)">
            <option value="">All Status</option>
            <option value="open"         <?= $filterStatus==='open'?'selected':'' ?>>Open</option>
            <option value="acknowledged" <?= $filterStatus==='acknowledged'?'selected':'' ?>>Acknowledged</option>
            <option value="resolved"     <?= $filterStatus==='resolved'?'selected':'' ?>>Resolved</option>
            <option value="false_positive" <?= $filterStatus==='false_positive'?'selected':'' ?>>False Positive</option>
        </select>
        <?php if ($filterSeverity || $filterStatus || $filterCategory): ?>
        <a href="alerts.php" class="btn btn-secondary btn-sm">Reset</a>
        <?php endif; ?>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr <?= $selectedAlert ? '380px' : '0px' ?>;gap:1rem;transition:grid-template-columns .3s">

<!-- Alert Table -->
<div class="card">
    <div class="table-wrap">
        <table class="data-table" id="alert-table">
            <thead>
                <tr>
                    <th>Severity</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Source IP</th>
                    <th>Status</th>
                    <th>Time</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($alerts as $alert): ?>
                <tr class="<?= $alert['id']==$selectedId?'row-selected':'' ?> <?= $alert['severity']==='critical'?'row-critical':'' ?>"
                    onclick="selectAlert(<?= $alert['id'] ?>)" style="cursor:pointer">
                    <td><?= severityBadge($alert['severity']) ?></td>
                    <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= sanitize($alert['title']) ?></td>
                    <td><span style="font-size:.75rem;color:var(--text-muted)"><?= sanitize($alert['category']) ?></span></td>
                    <td class="mono" style="font-size:.78rem"><?= sanitize($alert['source_ip'] ?? '—') ?></td>
                    <td><?= statusBadge($alert['status']) ?></td>
                    <td style="font-size:.75rem;color:var(--text-muted)"><?= timeAgo($alert['created_at']) ?></td>
                    <td onclick="event.stopPropagation()">
                        <div style="display:flex;gap:.3rem">
                            <?php if ($alert['status']==='open'): ?>
                            <button class="btn btn-xs btn-secondary" onclick="ackAlert(<?= $alert['id'] ?>)">ACK</button>
                            <?php endif; ?>
                            <?php if (in_array($alert['status'],['open','acknowledged'])): ?>
                            <button class="btn btn-xs btn-primary" onclick="openResolve(<?= $alert['id'] ?>, '<?= addslashes(sanitize($alert['title'])) ?>')">Resolve</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$alerts): ?>
                <tr><td colspan="7" class="empty-state"><i class="fas fa-shield-alt"></i><p>No alerts found. Generate some from Admin → Settings.</p></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Detail Panel -->
<?php if ($selectedAlert): ?>
<div class="card" style="overflow-y:auto;max-height:80vh">
    <div style="padding:1rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
        <h3 style="font-family:'Orbitron',sans-serif;font-size:.9rem;color:var(--text-primary)">ALERT DETAIL</h3>
        <a href="alerts.php<?= $filterSeverity||$filterStatus ? '?severity='.$filterSeverity.'&status='.$filterStatus : '' ?>" style="color:var(--text-muted);font-size:1.2rem;text-decoration:none">&times;</a>
    </div>
    <div style="padding:1rem">
        <div style="margin-bottom:1rem">
            <?= severityBadge($selectedAlert['severity']) ?>
            <?= statusBadge($selectedAlert['status']) ?>
        </div>
        <h4 style="color:var(--text-primary);margin-bottom:.5rem;font-size:.9rem"><?= sanitize($selectedAlert['title']) ?></h4>
        <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:1rem;line-height:1.6"><?= sanitize($selectedAlert['description']) ?></p>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:1rem">
            <?php
            $details = [
                'Source IP'   => $selectedAlert['source_ip'] ?? '—',
                'Dest IP'     => $selectedAlert['dest_ip'] ?? '—',
                'Protocol'    => $selectedAlert['protocol'] ?? '—',
                'Dest Port'   => $selectedAlert['dest_port'] ?? '—',
                'Category'    => $selectedAlert['category'] ?? '—',
                'Points'      => ($selectedAlert['points_value'] ?? 25) . ' pts',
                'Created'     => timeAgo($selectedAlert['created_at']),
            ];
            foreach ($details as $k => $v): ?>
            <div style="background:var(--bg-card);border-radius:4px;padding:.4rem .6rem">
                <div style="font-size:.65rem;color:var(--text-muted);margin-bottom:.1rem"><?= $k ?></div>
                <div style="font-family:'Share Tech Mono',monospace;font-size:.78rem;color:var(--text-primary)"><?= sanitize((string)$v) ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (in_array($selectedAlert['status'],['open','acknowledged'])): ?>
        <div style="display:flex;flex-direction:column;gap:.5rem">
            <?php if ($selectedAlert['status']==='open'): ?>
            <button class="btn btn-secondary" onclick="ackAlert(<?= $selectedAlert['id'] ?>)" style="width:100%;justify-content:center">
                <i class="fas fa-check"></i> Acknowledge
            </button>
            <?php endif; ?>
            <button class="btn btn-primary" onclick="openResolve(<?= $selectedAlert['id'] ?>, '<?= addslashes(sanitize($selectedAlert['title'])) ?>')" style="width:100%;justify-content:center">
                <i class="fas fa-check-double"></i> Resolve Alert
            </button>
            <button class="btn btn-warning" onclick="openCreateIncident(<?= $selectedAlert['id'] ?>, '<?= addslashes(sanitize($selectedAlert['title'])) ?>')" style="width:100%;justify-content:center">
                <i class="fas fa-ticket-alt"></i> Create Incident
            </button>
        </div>
        <?php elseif ($selectedAlert['status']==='resolved'): ?>
        <div style="background:rgba(0,255,136,.08);border:1px solid var(--green);border-radius:6px;padding:.75rem;font-size:.82rem;color:var(--green)">
            <i class="fas fa-check-circle"></i> Resolved
            <?php if ($selectedAlert['resolution_notes']): ?>
            <div style="margin-top:.4rem;color:var(--text-muted)"><?= sanitize($selectedAlert['resolution_notes']) ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

</div>

<!-- Resolve Modal -->
<div id="resolve-modal" class="modal-overlay" style="display:none">
    <div class="modal" style="max-width:460px">
        <div class="modal-header">
            <h3 class="modal-title">Resolve Alert</h3>
            <button onclick="closeModal('resolve-modal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="resolve-alert-id">
            <p id="resolve-alert-title" style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem"></p>
            <div class="form-group">
                <label class="form-label">Response Action *</label>
                <select id="resolve-action" class="form-input">
                    <option value="">— Select action —</option>
                    <option value="block_ip">Block IP Address (+25 pts)</option>
                    <option value="isolate_host">Isolate Host (+30 pts)</option>
                    <option value="reset_password">Reset Password (+15 pts)</option>
                    <option value="patch_system">Patch System (+20 pts)</option>
                    <option value="update_rules">Update Firewall Rules (+20 pts)</option>
                    <option value="scan_system">Run Full Scan (+15 pts)</option>
                    <option value="monitor">Monitor &amp; Watch (+10 pts)</option>
                    <option value="false_positive">Mark False Positive (+5 pts)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Notes (optional)</label>
                <textarea id="resolve-notes" class="form-input" rows="3" placeholder="Investigation notes..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('resolve-modal')" class="btn btn-secondary">Cancel</button>
            <button onclick="submitResolve()" class="btn btn-primary"><i class="fas fa-check"></i> Resolve</button>
        </div>
    </div>
</div>

<!-- Create Incident Modal -->
<div id="incident-modal" class="modal-overlay" style="display:none">
    <div class="modal" style="max-width:460px">
        <div class="modal-header">
            <h3 class="modal-title">Create Incident Ticket</h3>
            <button onclick="closeModal('incident-modal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="inc-alert-id">
            <div class="form-group">
                <label class="form-label">Title *</label>
                <input type="text" id="inc-title" class="form-input" placeholder="Incident title...">
            </div>
            <div class="form-group">
                <label class="form-label">Severity</label>
                <select id="inc-severity" class="form-input">
                    <option value="critical">Critical</option>
                    <option value="high">High</option>
                    <option value="medium" selected>Medium</option>
                    <option value="low">Low</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Description *</label>
                <textarea id="inc-description" class="form-input" rows="3" placeholder="Describe the incident..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('incident-modal')" class="btn btn-secondary">Cancel</button>
            <button onclick="submitIncident()" class="btn btn-primary"><i class="fas fa-ticket-alt"></i> Create</button>
        </div>
    </div>
</div>

<script>
function applyFilter(key, val) {
    const url = new URL(window.location);
    if (val) url.searchParams.set(key, val);
    else url.searchParams.delete(key);
    window.location = url.toString();
}

function selectAlert(id) {
    window.location = '/shadowwatch/alerts.php?id=' + id;
}

function ackAlert(id) {
    apiPost('/shadowwatch/api/resolve_alert.php', { action: 'acknowledge', alert_id: id })
        .then(r => {
            if (r.success) {
                showToast('Alert acknowledged! +5 pts', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(r.message || 'Error', 'error');
            }
        })
        .catch(e => showToast('Request failed: ' + e.message, 'error'));
}

function openResolve(id, title) {
    document.getElementById('resolve-alert-id').value = id;
    document.getElementById('resolve-alert-title').textContent = title;
    document.getElementById('resolve-action').value = '';
    document.getElementById('resolve-notes').value = '';
    openModal('resolve-modal');
}

async function submitResolve() {
    const id     = document.getElementById('resolve-alert-id').value;
    const action = document.getElementById('resolve-action').value;
    const notes  = document.getElementById('resolve-notes').value;
    if (!action) { showToast('Please select an action', 'warning'); return; }
    const r = await apiPost('/shadowwatch/api/resolve_alert.php', {
        action: 'resolve', alert_id: id, resolution_action: action, notes
    });
    if (r.success) {
        closeModal('resolve-modal');
        showToast('Alert resolved! +' + r.points + ' points', 'success');
        setTimeout(() => location.reload(), 1500);
    } else {
        showToast(r.message || 'Error', 'error');
    }
}

function openCreateIncident(alertId, title) {
    document.getElementById('inc-alert-id').value = alertId;
    document.getElementById('inc-title').value = 'Incident: ' + title;
    openModal('incident-modal');
}

async function submitIncident() {
    const alertId     = document.getElementById('inc-alert-id').value;
    const title       = document.getElementById('inc-title').value;
    const severity    = document.getElementById('inc-severity').value;
    const description = document.getElementById('inc-description').value;
    if (!title || !description) { showToast('Please fill all fields', 'warning'); return; }
    const r = await apiPost('/shadowwatch/api/create_incident.php', {
        action: 'create', alert_id: alertId, title, severity, description
    });
    if (r.success) {
        closeModal('incident-modal');
        showToast('Incident ' + r.ticket_number + ' created! +' + r.points + ' points', 'success');
        setTimeout(() => window.location = '/shadowwatch/incidents.php', 1500);
    } else {
        showToast(r.message || 'Error', 'error');
    }
}

async function generateAlert() {
    const r = await apiPost('/shadowwatch/api/generate_alert.php', { count: 1 });
    if (r.success) {
        showToast('New alert generated!', 'success');
        setTimeout(() => location.reload(), 1500);
    } else {
        showToast('Could not generate alert: ' + (r.message || ''), 'error');
    }
}

setInterval(() => { updateAlertCount(); }, 30000);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>