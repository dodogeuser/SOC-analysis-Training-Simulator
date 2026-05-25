<?php
$pageTitle = 'Log Analyzer';
$currentPage = 'logs';
require_once __DIR__ . '/includes/header.php';

$filterType = $_GET['type'] ?? '';
$selectedId = intval($_GET['id'] ?? 0);

$where = '1=1';
$params = [];
if ($filterType) { $where .= ' AND log_type = ?'; $params[] = $filterType; }

$logs = db()->fetchAll("SELECT l.*, u.username as analyzer FROM system_logs l
    LEFT JOIN users u ON l.analysed_by = u.id
    WHERE $where ORDER BY l.created_at DESC LIMIT 100", $params);

$selectedLog = null;
if ($selectedId) {
    $selectedLog = db()->fetchOne("SELECT l.*, u.username as analyzer FROM system_logs l
        LEFT JOIN users u ON l.analysed_by = u.id WHERE l.id = ?", [$selectedId]);
}

$stats = db()->fetchOne("SELECT 
    COUNT(*) as total,
    SUM(is_malicious) as malicious,
    SUM(verdict IS NOT NULL) as analyzed,
    SUM(is_malicious = 1) as correct
FROM system_logs");
?>

<div class="page-header">
    <div>
        <div class="page-title">Log Analyzer</div>
        <div class="page-subtitle">INGEST · PARSE · DETECT</div>
    </div>
    <button class="btn btn-primary" onclick="openModal('generate-logs-modal')">⊟ Generate Logs</button>
</div>

<!-- Stats -->
<div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
    <div class="stat-card high">
        <div class="stat-label">Total Logs</div>
        <div class="stat-value" style="color:var(--accent-cyan)"><?= number_format($stats['total']) ?></div>
    </div>
    <div class="stat-card critical">
        <div class="stat-label">Malicious</div>
        <div class="stat-value"><?= $stats['malicious'] ?></div>
    </div>
    <div class="stat-card good">
        <div class="stat-label">Analyzed</div>
        <div class="stat-value" style="color:var(--accent-green)"><?= $stats['analyzed'] ?></div>
    </div>
    <div class="stat-card medium">
        <div class="stat-label">Accuracy</div>
        <div class="stat-value" style="color:var(--accent-yellow)"><?= $stats['analyzed'] > 0 ? round($stats['correct'] / $stats['analyzed'] * 100) : 0 ?>%</div>
    </div>
</div>

<div class="<?= $selectedLog ? 'grid-6-4' : '' ?>" style="gap:16px">
    <!-- LOG TABLE -->
    <div>
        <!-- Filter Bar -->
        <div class="card" style="padding:10px 16px;margin-bottom:16px;">
            <div class="flex gap-8 flex-wrap">
                <?php foreach (['firewall','auth','web','dns','endpoint','network','system'] as $t): ?>
                <button class="btn btn-sm <?= $filterType === $t ? 'btn-primary' : 'btn-ghost' ?>"
                    onclick="window.location='/shadowwatch/logs.php?type=<?= $t ?>'">
                    <?= strtoupper($t) ?>
                </button>
                <?php endforeach; ?>
                <button class="btn btn-sm <?= !$filterType ? 'btn-primary' : 'btn-ghost' ?>"
                    onclick="window.location='/shadowwatch/logs.php'">ALL</button>
            </div>
        </div>

        <!-- Log Viewer -->
        <div class="card" style="padding:0">
            <div style="padding:10px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;">
                <span style="font-family:var(--font-mono);font-size:11px;color:var(--text-secondary)">
                    ⊟ <?= count($logs) ?> log entries <?= $filterType ? '— type: '.strtoupper($filterType) : '' ?>
                </span>
                <input type="text" class="form-control" id="log-search" placeholder="Search logs..." style="max-width:220px;margin-left:auto" oninput="filterTable('log-search','log-table')">
            </div>
            <div class="table-wrapper" style="border:none">
                <table id="log-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Timestamp</th>
                            <th>Source</th>
                            <th>Log Entry</th>
                            <th>Threat</th>
                            <th>Analysis</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr onclick="selectLog(<?= $log['id'] ?>)" 
                            style="<?= $selectedId == $log['id'] ? 'background:rgba(0,212,255,0.05)' : '' ?>;cursor:pointer">
                            <td>
                                <span style="font-family:var(--font-mono);font-size:10px;padding:2px 6px;background:rgba(0,212,255,0.08);border:1px solid rgba(0,212,255,0.2);border-radius:2px;color:var(--accent-cyan)">
                                    <?= strtoupper($log['log_type']) ?>
                                </span>
                            </td>
                            <td class="td-mono" style="font-size:10px;color:var(--text-dim)"><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></td>
                            <td class="td-mono" style="font-size:11px;color:var(--text-secondary)"><?= htmlspecialchars(substr($log['source'] ?? '—', 0, 20)) ?></td>
                            <td>
                                <div style="font-family:var(--font-mono);font-size:11px;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:<?= $log['is_malicious'] ? 'var(--accent-red)' : 'var(--text-secondary)' ?>">
                                    <?= htmlspecialchars(substr($log['raw_log'], 0, 100)) ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($log['is_malicious']): ?>
                                <span style="color:var(--accent-red);font-family:var(--font-mono);font-size:10px">⚠ MALICIOUS</span>
                                <?php else: ?>
                                <span style="color:var(--text-dim);font-family:var(--font-mono);font-size:10px">— BENIGN</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($log['verdict']): ?>
                                <span class="ioc-tag"><?= strtoupper($log['verdict']) ?></span>
                                <?php else: ?>
                                <span style="color:var(--text-dim);font-family:var(--font-mono);font-size:10px">PENDING</span>
                                <?php endif; ?>
                            </td>
                            <td onclick="event.stopPropagation()">
                                <?php if (!$log['verdict']): ?>
                                <button class="btn btn-ghost btn-sm" onclick="analyzeLog(<?= $log['id'] ?>)">Analyze</button>
                                <?php else: ?>
                                <span style="font-family:var(--font-mono);font-size:10px;color:<?= $log['is_malicious'] ? 'var(--accent-green)' : 'var(--accent-red)' ?>">
                                    <?= $log['is_malicious'] ? '✓ Correct' : '✕ Wrong' ?>
                                </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($logs)): ?>
                        <tr><td colspan="7" style="text-align:center;color:var(--text-dim);font-family:var(--font-mono);padding:32px">No logs found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- LOG DETAIL -->
    <?php if ($selectedLog): ?>
    <div style="display:flex;flex-direction:column;gap:16px;">
        <div class="card">
            <div class="card-title">⊟ Log Entry #<?= $selectedLog['id'] ?></div>
            
            <div style="margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap;">
                <span class="ioc-tag"><?= strtoupper($selectedLog['log_type']) ?></span>
                <span class="ioc-tag"><?= htmlspecialchars($selectedLog['source'] ?? '—') ?></span>
                <?php if ($selectedLog['is_malicious']): ?>
                <span style="background:rgba(255,34,68,0.1);border:1px solid rgba(255,34,68,0.3);color:var(--accent-red);font-family:var(--font-mono);font-size:10px;padding:2px 8px;border-radius:3px">⚠ MALICIOUS</span>
                <?php endif; ?>
            </div>

            <div style="background:var(--bg-void);border:1px solid var(--border);border-radius:4px;padding:12px;margin-bottom:16px;">
                <div style="font-family:var(--font-mono);font-size:11px;color:var(--text-secondary);white-space:pre-wrap;word-break:break-all;line-height:1.8"><?= htmlspecialchars($selectedLog['raw_log']) ?></div>
            </div>

            <div style="font-family:var(--font-mono);font-size:10px;color:var(--text-dim);margin-bottom:16px">
                <?= date('Y-m-d H:i:s', strtotime($selectedLog['created_at'])) ?> UTC
            </div>

            <?php if (!$selectedLog['verdict']): ?>
            <!-- Analyze form -->
            <div style="border-top:1px solid var(--border);padding-top:16px;">
                <div class="form-label" style="margin-bottom:12px">◎ Classify This Log Entry</div>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php foreach (['malicious' => ['⚠', 'var(--accent-red)', 'This is a malicious/attack log'], 'suspicious' => ['?', 'var(--accent-orange)', 'Suspicious but not confirmed'], 'benign' => ['✓', 'var(--accent-green)', 'Legitimate / normal traffic'], 'unknown' => ['—', 'var(--text-secondary)', 'Cannot determine']] as $val => [$icon, $col, $desc]): ?>
                    <button class="btn btn-ghost" style="justify-content:flex-start;border-color:rgba(26,58,82,0.8)" onclick="submitAnalysis(<?= $selectedLog['id'] ?>, '<?= $val ?>')">
                        <span style="color:<?= $col ?>;font-size:16px"><?= $icon ?></span>
                        <div style="text-align:left">
                            <div style="font-weight:600"><?= strtoupper($val) ?></div>
                            <div style="font-size:11px;color:var(--text-dim)"><?= $desc ?></div>
                        </div>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="alert-bar <?= $selectedLog['is_malicious'] ? 'success' : 'error' ?>">
                <?= $selectedLog['is_malicious'] ? '✓ Correct analysis!' : '✕ Incorrect — the actual classification was ' . ($selectedLog['is_malicious'] ? 'MALICIOUS' : 'BENIGN') ?>
            </div>
            <div style="font-size:12px;color:var(--text-secondary)">Analyzed by: <?= htmlspecialchars($selectedLog['analyzer'] ?? 'Unknown') ?></div>
            <?php endif; ?>
        </div>

        <!-- Quick threat check -->
        <div class="card">
            <div class="card-title">⬡ Quick IOC Extract</div>
            <div id="ioc-extract" style="font-family:var(--font-mono);font-size:11px;color:var(--text-secondary);">
                <?php
                $raw = $selectedLog['raw_log'];
                preg_match_all('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $raw, $ips);
                preg_match_all('/\b[a-z0-9-]+\.[a-z]{2,}\b/i', $raw, $domains);
                preg_match_all('/\b[0-9a-f]{32,64}\b/', $raw, $hashes);
                $uniqueIps = array_unique($ips[0] ?? []);
                $uniqueDomains = array_unique($domains[0] ?? []);
                $uniqueHashes = array_unique($hashes[0] ?? []);
                ?>
                <?php if ($uniqueIps): ?>
                <div style="margin-bottom:8px"><span style="color:var(--accent-cyan)">IPs:</span><br>
                <?php foreach ($uniqueIps as $ip): ?>
                    <span class="ioc-tag" style="cursor:pointer" onclick="checkThreat('<?= htmlspecialchars($ip) ?>', 'ip')"><?= htmlspecialchars($ip) ?></span>
                <?php endforeach; ?></div>
                <?php endif; ?>
                <?php if ($uniqueDomains): ?>
                <div style="margin-bottom:8px"><span style="color:var(--accent-yellow)">Domains:</span><br>
                <?php foreach (array_slice($uniqueDomains, 0, 5) as $d): ?>
                    <span class="ioc-tag" style="cursor:pointer" onclick="checkThreat('<?= htmlspecialchars($d) ?>', 'domain')"><?= htmlspecialchars($d) ?></span>
                <?php endforeach; ?></div>
                <?php endif; ?>
                <?php if ($uniqueHashes): ?>
                <div><span style="color:var(--accent-orange)">Hashes:</span><br>
                <?php foreach ($uniqueHashes as $h): ?>
                    <span class="ioc-tag"><?= htmlspecialchars(substr($h, 0, 16)) ?>…</span>
                <?php endforeach; ?></div>
                <?php endif; ?>
                <?php if (!$uniqueIps && !$uniqueDomains && !$uniqueHashes): ?>
                <span style="color:var(--text-dim)">No IOCs extracted</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- GENERATE LOGS MODAL -->
<div class="modal-overlay" id="generate-logs-modal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">⊟ GENERATE TRAINING LOGS</div>
            <button class="modal-close">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Log Type</label>
                <select class="form-control" id="gen-log-type">
                    <option value="firewall">Firewall Logs</option>
                    <option value="auth">Authentication Logs</option>
                    <option value="web">Web Access Logs</option>
                    <option value="dns">DNS Query Logs</option>
                    <option value="endpoint">Endpoint/EDR Logs</option>
                    <option value="network">Network Flow Logs</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Include Malicious Entries</label>
                <select class="form-control" id="gen-include-malicious">
                    <option value="mixed">Mixed (Realistic)</option>
                    <option value="yes">Yes — All Malicious</option>
                    <option value="no">No — All Benign</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Count</label>
                <select class="form-control" id="gen-count">
                    <option value="5">5 entries</option>
                    <option value="10" selected>10 entries</option>
                    <option value="25">25 entries</option>
                </select>
            </div>
            <div class="alert-bar info" style="font-size:12px">
                ℹ Generated logs will appear in the analyzer. Correctly classifying them earns points.
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('generate-logs-modal')">Cancel</button>
            <button class="btn btn-primary" onclick="generateLogs()">⊟ Generate Logs</button>
        </div>
    </div>
</div>

<!-- Threat check result modal -->
<div class="modal-overlay" id="threat-check-modal">
    <div class="modal" style="width:420px">
        <div class="modal-header">
            <div class="modal-title">⬡ THREAT INTEL LOOKUP</div>
            <button class="modal-close">✕</button>
        </div>
        <div class="modal-body" id="threat-check-result">Checking...</div>
    </div>
</div>


<script>
function selectLog(id) {
    window.location = '/shadowwatch/logs.php?id=' + id + '<?= $filterType ? "&type=".$filterType : "" ?>';
}

async function submitAnalysis(logId, verdict) {
    const r = await apiPost('/shadowwatch/api/fetch_alerts.php', { action: 'analyze_log', log_id: logId, verdict });
    if (r.success) {
        showToast(r.correct ? '✓ Correct! +' + r.points + ' pts' : '✕ Incorrect. +' + r.points + ' pts', r.correct ? 'success' : 'warning');
        setTimeout(() => location.reload(), 1200);
    } else showToast(r.message || 'Error', 'error');
}

async function generateLogs() {
    const count = document.getElementById('gen-count').value;
    const r = await apiPost('/shadowwatch/api/fetch_alerts.php', { action: 'generate_logs', count });
    if (r.success) {
        closeModal('generate-logs-modal');
        showToast(r.generated + ' log entries generated', 'success');
        setTimeout(() => location.reload(), 1000);
    } else showToast(r.message || 'Error', 'error');
}

async function checkThreat(indicator, type) {
    openModal('threat-check-modal');
    document.getElementById('threat-check-result').innerHTML = '<div style="color:var(--text-muted)">Querying threat database...</div>';
    const r = await apiPost('/shadowwatch/api/fetch_alerts.php', { action: 'threat_lookup', indicator, type });
    const el = document.getElementById('threat-check-result');
    if (r.found) {
        el.innerHTML = '<div style="color:var(--red);margin-bottom:.5rem"><i class="fas fa-exclamation-triangle"></i> THREAT INDICATOR FOUND</div>'
            + '<div style="font-size:.82rem;color:var(--text-secondary);margin-bottom:.5rem"><strong>Type:</strong> ' + r.data.threat_type + '</div>'
            + '<div style="font-size:.82rem;color:var(--text-secondary);margin-bottom:.5rem"><strong>Confidence:</strong> ' + (r.data.confidence||'').toUpperCase() + '</div>'
            + '<div style="font-size:.82rem;color:var(--text-secondary)">' + r.data.description + '</div>';
    } else {
        el.innerHTML = '<div style="color:var(--green)"><i class="fas fa-check-circle"></i> Not found in threat database — indicator appears clean</div>'
            + '<div style="font-size:.78rem;color:var(--text-muted);margin-top:.5rem">Indicator: ' + indicator + '</div>';
    }
}

filterTable('log-search', 'log-table');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>