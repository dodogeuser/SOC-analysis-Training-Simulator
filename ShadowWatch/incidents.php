<?php
$pageTitle = 'Incident Tickets';
$currentPage = 'incidents';
require_once __DIR__ . '/includes/header.php';

$selectedId = intval($_GET['id'] ?? 0);
$showCreate = isset($_GET['create']);

$incidents = db()->fetchAll("SELECT i.*, u.username as creator, a.username as assignee 
    FROM incidents i
    LEFT JOIN users u ON i.created_by = u.id
    LEFT JOIN users a ON i.assigned_to = a.id
    ORDER BY 
        FIELD(i.status,'open','investigating','pending','resolved','closed'),
        FIELD(i.severity,'critical','high','medium','low'),
        i.created_at DESC");

$selectedInc = null;
if ($selectedId) {
    $selectedInc = db()->fetchOne("SELECT i.*, u.username as creator, a.username as assignee 
        FROM incidents i
        LEFT JOIN users u ON i.created_by = u.id
        LEFT JOIN users a ON i.assigned_to = a.id
        WHERE i.id = ?", [$selectedId]);
}

$analysts = db()->fetchAll("SELECT id, username, role FROM users WHERE is_active = 1 ORDER BY username");
?>

<div class="page-header">
    <div>
        <div class="page-title">Incident Management</div>
        <div class="page-subtitle">TRACK · INVESTIGATE · RESOLVE</div>
    </div>
    <button class="btn btn-primary" onclick="openModal('create-modal')">+ New Incident</button>
</div>

<div class="<?= $selectedInc ? 'grid-6-4' : '' ?>" style="gap:16px;">
    <!-- INCIDENTS TABLE -->
    <div class="card" style="padding:0">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;">
            <input type="text" class="form-control" id="inc-search" placeholder="🔍 Search incidents..." style="max-width:250px" oninput="filterTable('inc-search','inc-table')">
            <select class="form-control" style="width:auto" onchange="filterByStatus(this.value)">
                <option value="">All Statuses</option>
                <option value="open">Open</option>
                <option value="in_progress">Investigating</option>
                <option value="resolved">Resolved</option>
                <option value="closed">Closed</option>
            </select>
        </div>
        <div class="table-wrapper" style="border:none">
            <table id="inc-table">
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Title</th>
                        <th>Severity</th>
                        <th>Status</th>
                        <th>Assignee</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($incidents as $inc): ?>
                    <tr onclick="selectInc(<?= $inc['id'] ?>)" 
                        style="<?= $selectedId == $inc['id'] ? 'background:rgba(0,212,255,0.05)' : '' ?>;cursor:pointer">
                        <td class="td-mono" style="color:var(--accent-cyan);font-size:12px"><?= htmlspecialchars($inc['ticket_number']) ?></td>
                        <td>
                            <div style="font-size:13px;font-weight:600;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                <?= htmlspecialchars($inc['title']) ?>
                            </div>
                            <div style="font-family:var(--font-mono);font-size:10px;color:var(--text-dim)"><?= htmlspecialchars($inc['category'] ?? '') ?></div>
                        </td>
                        <td><?= severityBadge($inc['severity']) ?></td>
                        <td><?= statusBadge($inc['status']) ?></td>
                        <td class="td-mono" style="font-size:11px"><?= htmlspecialchars($inc['assignee'] ?? 'Unassigned') ?></td>
                        <td class="td-mono" style="font-size:11px;color:var(--text-dim)"><?= timeAgo($inc['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($incidents)): ?>
                    <tr><td colspan="6" style="text-align:center;color:var(--text-dim);font-family:var(--font-mono);padding:32px">No incidents found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- INCIDENT DETAIL -->
    <?php if ($selectedInc): ?>
    <div style="display:flex;flex-direction:column;gap:16px;">
        <div class="card">
            <div class="card-title">📋 <?= htmlspecialchars($selectedInc['ticket_number']) ?></div>
            
            <div style="margin-bottom:8px">
                <?= severityBadge($selectedInc['severity']) ?>
                <?= statusBadge($selectedInc['status']) ?>
            </div>

            <h3 style="color:var(--text-bright);font-size:15px;margin-bottom:12px"><?= htmlspecialchars($selectedInc['title']) ?></h3>
            <p style="font-size:13px;color:var(--text-secondary);line-height:1.6;margin-bottom:16px"><?= nl2br(htmlspecialchars($selectedInc['description'])) ?></p>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px;">
                <?php $detailFields = [
                    'Created By' => $selectedInc['creator'],
                    'Assignee' => $selectedInc['assignee'] ?? 'Unassigned',
                    'Category' => $selectedInc['category'] ?? '—',
                    'Created' => $selectedInc['created_at'],
                    'Updated' => $selectedInc['updated_at'],
                    'Resolved' => $selectedInc['resolved_at'] ?? '—',
                ];
                foreach ($detailFields as $label => $val): ?>
                <div style="background:var(--bg-panel);border:1px solid var(--border);border-radius:4px;padding:8px;">
                    <div style="font-family:var(--font-mono);font-size:9px;color:var(--text-dim);letter-spacing:2px"><?= $label ?></div>
                    <div style="font-family:var(--font-mono);font-size:11px;color:var(--text-primary);margin-top:2px"><?= htmlspecialchars($val) ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Update Status -->
            <?php if (in_array($selectedInc['status'], ['open','investigating','pending'])): ?>
            <div style="border-top:1px solid var(--border);padding-top:16px;">
                <div class="form-label" style="margin-bottom:8px">Update Status</div>
                <div class="flex gap-8">
                    <select class="form-control" id="update-status-<?= $selectedInc['id'] ?>">
                        <option value="open" <?= $selectedInc['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                        <option value="in_progress" <?= $selectedInc['status'] === 'investigating' ? 'selected' : '' ?>>Investigating</option>
                        <option value="pending" <?= $selectedInc['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                    <button class="btn btn-primary btn-sm" onclick="updateIncidentStatus(<?= $selectedInc['id'] ?>)">Update</button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Assign -->
            <?php if (isSeniorAnalyst()): ?>
            <div style="margin-top:12px;">
                <div class="form-label" style="margin-bottom:8px">Assign To</div>
                <div class="flex gap-8">
                    <select class="form-control" id="assign-to-<?= $selectedInc['id'] ?>">
                        <option value="">Unassigned</option>
                        <?php foreach ($analysts as $analyst): ?>
                        <option value="<?= $analyst['id'] ?>" <?= $selectedInc['assigned_to'] == $analyst['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($analyst['username']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-ghost btn-sm" onclick="assignIncident(<?= $selectedInc['id'] ?>)">Assign</button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Resolution summary if resolved -->
        <?php if (!empty($selectedInc['notes'])): ?>
        <div class="card">
            <div class="card-title">✓ Notes</div>
            <p style="font-size:13px;color:var(--text-secondary);line-height:1.6"><?= nl2br(htmlspecialchars($selectedInc['notes'])) ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- CREATE INCIDENT MODAL -->
<div class="modal-overlay" id="create-modal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">📋 CREATE INCIDENT TICKET</div>
            <button class="modal-close">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Title *</label>
                <input type="text" class="form-control" id="new-title" placeholder="Incident title">
            </div>
            <div class="flex gap-8">
                <div class="form-group" style="flex:1">
                    <label class="form-label">Severity *</label>
                    <select class="form-control" id="new-severity">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high" selected>High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
                <div class="form-group" style="flex:1">
                    <label class="form-label">Category</label>
                    <select class="form-control" id="new-category">
                        <option value="malware">Malware</option>
                        <option value="phishing">Phishing</option>
                        <option value="ddos">DDoS</option>
                        <option value="intrusion">Intrusion</option>
                        <option value="data_breach">Data Breach</option>
                        <option value="ransomware">Ransomware</option>
                        <option value="insider_threat">Insider Threat</option>
                        <option value="brute_force">Brute Force</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Description *</label>
                <textarea class="form-control" id="new-description" rows="5" placeholder="Describe the incident, affected systems, timeline, and initial findings..."></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Assign To</label>
                <select class="form-control" id="new-assignee">
                    <option value="">Myself</option>
                    <?php foreach ($analysts as $analyst): ?>
                    <option value="<?= $analyst['id'] ?>"><?= htmlspecialchars($analyst['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('create-modal')">Cancel</button>
            <button class="btn btn-primary" onclick="createIncident()">📋 Create Ticket</button>
        </div>
    </div>
</div>


<script>
function selectInc(id) {
    window.location = '/shadowwatch/incidents.php?id=' + id;
}

function filterByStatus(status) {
    const rows = document.querySelectorAll('#inc-table tbody tr');
    rows.forEach(row => {
        if (!status) { row.style.display = ''; return; }
        const statusCell = row.querySelector('td:nth-child(4)');
        row.style.display = (statusCell && statusCell.textContent.toLowerCase().includes(status.replace('_',' '))) ? '' : 'none';
    });
}

async function createIncident() {
    const title       = document.getElementById('new-title').value;
    const severity    = document.getElementById('new-severity').value;
    const category    = document.getElementById('new-category').value;
    const description = document.getElementById('new-description').value;
    const assignee    = document.getElementById('new-assignee').value;
    if (!title || !description) { showToast('Title and description required', 'warning'); return; }
    const r = await apiPost('/shadowwatch/api/create_incident.php', {
        action: 'create', title, severity, category, description, assignee_id: assignee
    });
    if (r.success) {
        closeModal('create-modal');
        showToast('Incident ' + r.ticket_number + ' created! +' + r.points + ' pts', 'success');
        setTimeout(() => window.location = '/shadowwatch/incidents.php', 1200);
    } else showToast(r.message || 'Error creating incident', 'error');
}

async function updateIncidentStatus(id) {
    const status = document.getElementById('update-status-' + id).value;
    const r = await apiPost('/shadowwatch/api/create_incident.php', { action: 'update_status', incident_id: id, status });
    if (r.success) { showToast('Status updated', 'success'); setTimeout(() => location.reload(), 800); }
    else showToast(r.message || 'Error', 'error');
}

async function assignIncident(id) {
    const assigneeId = document.getElementById('assign-to-' + id).value;
    const r = await apiPost('/shadowwatch/api/create_incident.php', { action: 'assign', incident_id: id, assignee_id: assigneeId });
    if (r.success) { showToast('Incident assigned', 'success'); setTimeout(() => location.reload(), 800); }
    else showToast(r.message || 'Error', 'error');
}

filterTable('inc-search', 'inc-table');
<?php if ($showCreate): ?>openModal('create-modal');<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>