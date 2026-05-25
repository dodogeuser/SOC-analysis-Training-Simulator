<?php
$pageTitle = 'SOC Dashboard';
$currentPage = 'dashboard';
require_once __DIR__ . '/includes/header.php';

// Stats
$stats = db()->fetchOne("SELECT 
    (SELECT COUNT(*) FROM alerts WHERE status = 'open') as open_alerts,
    (SELECT COUNT(*) FROM alerts WHERE status = 'open' AND severity = 'critical') as critical_alerts,
    (SELECT COUNT(*) FROM incidents WHERE status NOT IN ('closed')) as open_incidents,
    (SELECT COUNT(*) FROM alerts WHERE DATE(created_at) = CURDATE()) as alerts_today,
    (SELECT COUNT(*) FROM alerts WHERE status = 'resolved' AND DATE(resolved_at) = CURDATE()) as resolved_today,
    (SELECT COUNT(*) FROM users WHERE is_active = 1) as active_users
");

$recentAlerts = db()->fetchAll("SELECT a.*, s.title as scenario_name FROM alerts a 
    LEFT JOIN scenarios s ON a.scenario_id = s.id 
    ORDER BY a.created_at DESC LIMIT 8");

$recentIncidents = db()->fetchAll("SELECT i.*, u.username as created_by_name FROM incidents i 
    LEFT JOIN users u ON i.created_by = u.id 
    ORDER BY i.created_at DESC LIMIT 5");

$topAnalysts = getLeaderboard(5);
$myStats = db()->fetchOne("SELECT u.*,
    (SELECT COUNT(*) FROM alerts WHERE resolved_by = u.id) as total_alerts_resolved,
    (SELECT COUNT(*) FROM incidents WHERE created_by = u.id) as total_incidents_created
    FROM users u WHERE u.id = ?", [$_SESSION['user_id']]);
$levelInfo = getLevelProgress($myStats['score']);

// Alert distribution
$alertDist = db()->fetchAll("SELECT severity, COUNT(*) as cnt FROM alerts WHERE status = 'open' GROUP BY severity");
$distMap = [];
foreach ($alertDist as $d) $distMap[$d['severity']] = $d['cnt'];
?>

<!-- STAT CARDS -->
<div class="stat-grid">
    <div class="stat-card critical">
        <div class="stat-label">Critical Alerts</div>
        <div class="stat-value"><?= $stats['critical_alerts'] ?></div>
        <div class="stat-delta">⬡ Requires immediate action</div>
    </div>
    <div class="stat-card high">
        <div class="stat-label">Open Alerts</div>
        <div class="stat-value"><?= $stats['open_alerts'] ?></div>
        <div class="stat-delta">▲ <?= $stats['alerts_today'] ?> generated today</div>
    </div>
    <div class="stat-card medium">
        <div class="stat-label">Active Incidents</div>
        <div class="stat-value"><?= $stats['open_incidents'] ?></div>
        <div class="stat-delta">◎ Investigations in progress</div>
    </div>
    <div class="stat-card good">
        <div class="stat-label">Resolved Today</div>
        <div class="stat-value"><?= $stats['resolved_today'] ?></div>
        <div class="stat-delta">✓ By all analysts</div>
    </div>
    <div class="stat-card" style="border-left:3px solid var(--accent-cyan)">
        <div class="stat-label">My Score</div>
        <div class="stat-value" style="color:var(--accent-cyan)"><?= number_format($myStats['score']) ?></div>
        <div class="stat-delta">Level <?= $myStats['level'] ?> • <?= $levelInfo['progress'] ?>% to next</div>
    </div>
</div>

<!-- MAIN GRID -->
<div class="grid-7-3" style="margin-bottom:20px;">
    <!-- Recent Alerts -->
    <div class="card">
        <div class="card-title">
            ⚠ Recent Alerts
            <a href="/shadowwatch/alerts.php" class="btn btn-ghost btn-sm" style="margin-left:auto;">View All →</a>
        </div>
        <div class="table-wrapper">
            <table id="dash-alerts-table">
                <thead>
                    <tr>
                        <th>Severity</th>
                        <th>Alert</th>
                        <th>Category</th>
                        <th>Source IP</th>
                        <th>Status</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentAlerts as $alert): ?>
                    <tr class="alert-row-<?= $alert['severity'] ?>" onclick="window.location='/shadowwatch/alerts.php?id=<?= $alert['id'] ?>'" style="cursor:pointer">
                        <td><?= severityBadge($alert['severity']) ?></td>
                        <td>
                            <div style="font-weight:600;font-size:13px"><?= htmlspecialchars(substr($alert['title'], 0, 50)) ?><?= strlen($alert['title']) > 50 ? '…' : '' ?></div>
                            <div style="font-family:var(--font-mono);font-size:10px;color:var(--text-dim)">#<?= $alert['id'] ?></div>
                        </td>
                        <td class="td-mono" style="font-size:11px"><?= htmlspecialchars($alert['category']) ?></td>
                        <td class="td-mono" style="color:var(--accent-orange)"><?= htmlspecialchars($alert['source_ip'] ?? '—') ?></td>
                        <td><?= statusBadge($alert['status']) ?></td>
                        <td class="td-mono" style="font-size:11px;color:var(--text-dim)"><?= timeAgo($alert['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Column -->
    <div style="display:flex;flex-direction:column;gap:16px;">
        <!-- Alert Distribution -->
        <div class="card">
            <div class="card-title">◈ Alert Distribution</div>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php
                $severities = ['critical' => '#ff2244', 'high' => '#ff6600', 'medium' => '#ffcc00', 'low' => '#00ff88'];
                $totalOpen = array_sum($distMap) ?: 1;
                foreach ($severities as $sev => $col):
                    $cnt = $distMap[$sev] ?? 0;
                    $pct = round($cnt / $totalOpen * 100);
                ?>
                <div>
                    <div class="flex justify-between mb-8" style="font-family:var(--font-mono);font-size:11px;">
                        <span style="color:<?= $col ?>"><?= strtoupper($sev) ?></span>
                        <span style="color:var(--text-secondary)"><?= $cnt ?> (<?= $pct ?>%)</span>
                    </div>
                    <div class="progress-bar-wrap">
                        <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $col ?>;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- My Progress -->
        <div class="card">
            <div class="card-title">◎ My Progress</div>
            <div style="text-align:center;margin-bottom:16px;">
                <div style="font-family:var(--font-display);font-size:36px;color:var(--accent-cyan)"><?= $myStats['level'] ?></div>
                <div style="font-family:var(--font-mono);font-size:10px;color:var(--text-dim);letter-spacing:2px">ANALYST LEVEL</div>
            </div>
            <div class="progress-bar-wrap" style="margin-bottom:8px;">
                <div class="progress-bar cyan" style="width:<?= $levelInfo['progress'] ?>%"></div>
            </div>
            <div class="flex justify-between" style="font-family:var(--font-mono);font-size:10px;color:var(--text-dim);">
                <span><?= number_format($myStats['score']) ?> PTS</span>
                <span><?= number_format($levelInfo['next']) ?> PTS</span>
            </div>
            <div style="margin-top:16px;display:grid;grid-template-columns:1fr 1fr;gap:8px;text-align:center;">
                <div style="background:var(--bg-panel);border:1px solid var(--border);border-radius:4px;padding:8px;">
                    <div style="font-family:var(--font-display);font-size:20px;color:var(--accent-green)"><?= $myStats['total_alerts_resolved'] ?></div>
                    <div style="font-family:var(--font-mono);font-size:9px;color:var(--text-dim)">RESOLVED</div>
                </div>
                <div style="background:var(--bg-panel);border:1px solid var(--border);border-radius:4px;padding:8px;">
                    <div style="font-family:var(--font-display);font-size:20px;color:var(--accent-yellow)"><?= number_format($myStats['total_incidents_created'] ?? 0) ?></div>
                    <div style="font-family:var(--font-mono);font-size:9px;color:var(--text-dim)">INCIDENTS</div>
                </div>
            </div>
        </div>

        <!-- Top Analysts -->
        <div class="card">
            <div class="card-title">▲ Top Analysts</div>
            <?php foreach ($topAnalysts as $i => $analyst): ?>
            <div class="flex flex-center gap-8" style="padding:6px 0;border-bottom:1px solid rgba(26,58,82,0.3);">
                <div class="rank-num <?= 'rank-' . ($i + 1) ?>">#<?= $i + 1 ?></div>
                <div class="user-avatar-sm" style="width:24px;height:24px;font-size:10px"><?= strtoupper(substr($analyst['username'], 0, 2)) ?></div>
                <div style="flex:1">
                    <div style="font-size:12px;font-weight:600"><?= htmlspecialchars($analyst['username']) ?></div>
                    <div style="font-family:var(--font-mono);font-size:10px;color:var(--text-dim)">LVL <?= $analyst['level'] ?></div>
                </div>
                <div style="font-family:var(--font-mono);font-size:11px;color:var(--accent-cyan)"><?= number_format($analyst['score']) ?></div>
            </div>
            <?php endforeach; ?>
            <div style="margin-top:12px;">
                <a href="/shadowwatch/leaderboard.php" class="btn btn-ghost btn-sm" style="width:100%;justify-content:center;">Full Leaderboard →</a>
            </div>
        </div>
    </div>
</div>

<!-- RECENT INCIDENTS -->
<div class="card">
    <div class="card-title">
        📋 Recent Incidents
        <a href="/shadowwatch/incidents.php" class="btn btn-ghost btn-sm" style="margin-left:auto;">View All →</a>
        <a href="/shadowwatch/incidents.php?create=1" class="btn btn-primary btn-sm">+ New Ticket</a>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Ticket</th>
                    <th>Title</th>
                    <th>Severity</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentIncidents as $inc): ?>
                <tr onclick="window.location='/shadowwatch/incidents.php?id=<?= $inc['id'] ?>'" style="cursor:pointer">
                    <td class="td-mono" style="color:var(--accent-cyan)"><?= htmlspecialchars($inc['ticket_number']) ?></td>
                    <td><?= htmlspecialchars($inc['title']) ?></td>
                    <td><?= severityBadge($inc['severity']) ?></td>
                    <td><?= statusBadge($inc['status']) ?></td>
                    <td class="td-mono" style="font-size:11px"><?= htmlspecialchars($inc['created_by_name']) ?></td>
                    <td class="td-mono" style="font-size:11px;color:var(--text-dim)"><?= timeAgo($inc['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>