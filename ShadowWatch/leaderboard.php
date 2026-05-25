<?php
$pageTitle = 'Analyst Leaderboard';
$currentPage = 'leaderboard';
require_once __DIR__ . '/includes/header.php';

$leaderboard = db()->fetchAll("SELECT u.id, u.username, u.score, u.level, u.role, u.created_at,
    (SELECT COUNT(*) FROM alerts WHERE resolved_by = u.id) as total_alerts_resolved,
    (SELECT COUNT(*) FROM incidents WHERE created_by = u.id) as total_incidents_created,
    (SELECT COUNT(*) FROM system_logs WHERE analysed_by = u.id) as total_logs_analyzed,
    (SELECT COUNT(*) FROM score_events se WHERE se.user_id = u.id AND DATE(se.created_at) = CURDATE()) as events_today
    FROM users u 
    WHERE u.is_active = 1 
    ORDER BY u.score DESC");

$myRank = 0;
foreach ($leaderboard as $i => $u) {
    if ($u['id'] == $_SESSION['user_id']) { $myRank = $i + 1; break; }
}

$myStats = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
$levelInfo = getLevelProgress($myStats['score']);

$recentEvents = db()->fetchAll("SELECT se.*, u.username FROM score_events se
    LEFT JOIN users u ON se.user_id = u.id
    ORDER BY se.created_at DESC LIMIT 15");
?>

<div class="page-header">
    <div>
        <div class="page-title">Analyst Leaderboard</div>
        <div class="page-subtitle">COMPETE · IMPROVE · DOMINATE</div>
    </div>
    <div style="font-family:var(--font-mono);font-size:12px;color:var(--text-secondary)">
        Your Rank: <span style="font-family:var(--font-display);font-size:20px;color:var(--accent-cyan)">#<?= $myRank ?></span>
    </div>
</div>

<div class="grid-7-3" style="gap:20px">
    <!-- LEADERBOARD TABLE -->
    <div>
        <!-- Top 3 Cards -->
        <div class="grid-3" style="margin-bottom:20px">
            <?php for ($i = 0; $i < min(3, count($leaderboard)); $i++): 
                $u = $leaderboard[$i];
                $medals = ['🥇', '🥈', '🥉'];
                $colors = ['#FFD700', '#C0C0C0', '#CD7F32'];
            ?>
            <div class="card" style="text-align:center;border-top:3px solid <?= $colors[$i] ?>;position:relative">
                <?php if ($i === 0): ?>
                <div style="position:absolute;top:-10px;left:50%;transform:translateX(-50%);font-size:20px">👑</div>
                <?php endif; ?>
                <div style="margin-top:8px;font-size:32px"><?= $medals[$i] ?></div>
                <div class="user-avatar-sm" style="width:48px;height:48px;font-size:18px;margin:8px auto"><?= strtoupper(substr($u['username'], 0, 2)) ?></div>
                <div style="font-weight:700;font-size:14px;color:var(--text-bright)"><?= htmlspecialchars($u['username']) ?></div>
                <div style="font-family:var(--font-mono);font-size:10px;color:var(--text-dim);margin-bottom:8px"><?= strtoupper(str_replace('_', ' ', $u['role'])) ?></div>
                <div style="font-family:var(--font-display);font-size:24px;color:<?= $colors[$i] ?>;font-weight:700"><?= number_format($u['score']) ?></div>
                <div style="font-family:var(--font-mono);font-size:10px;color:var(--text-dim)">LEVEL <?= $u['level'] ?></div>
            </div>
            <?php endfor; ?>
        </div>

        <!-- Full Table -->
        <div class="card" style="padding:0">
            <div class="table-wrapper" style="border:none">
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Analyst</th>
                            <th>Level</th>
                            <th>Score</th>
                            <th>Alerts</th>
                            <th>Incidents</th>
                            <th>Accuracy</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaderboard as $i => $u): 
                            $rank = $i + 1;
                            $isMe = $u['id'] == $_SESSION['user_id'];
                        ?>
                        <tr style="<?= $isMe ? 'background:rgba(0,212,255,0.05);' : '' ?>">
                            <td>
                                <div class="rank-num <?= $rank <= 3 ? 'rank-' . $rank : '' ?>">#<?= $rank ?></div>
                            </td>
                            <td>
                                <div class="flex flex-center gap-8">
                                    <div class="user-avatar-sm" style="width:28px;height:28px;font-size:11px"><?= strtoupper(substr($u['username'], 0, 2)) ?></div>
                                    <div>
                                        <div style="font-weight:600;<?= $isMe ? 'color:var(--accent-cyan)' : '' ?>">
                                            <?= htmlspecialchars($u['username']) ?>
                                            <?php if ($isMe): ?><span style="font-family:var(--font-mono);font-size:9px;color:var(--accent-cyan);margin-left:4px">YOU</span><?php endif; ?>
                                        </div>
                                        <div style="font-family:var(--font-mono);font-size:10px;color:var(--text-dim)"><?= strtoupper(str_replace('_',' ',$u['role'])) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="level-badge"><?= $u['level'] ?></span>
                            </td>
                            <td style="font-family:var(--font-display);font-size:16px;font-weight:700;color:<?= $rank <= 3 ? ['#FFD700','#C0C0C0','#CD7F32'][$rank-1] : 'var(--accent-cyan)' ?>">
                                <?= number_format($u['score']) ?>
                            </td>
                            <td class="td-mono" style="color:var(--accent-green)"><?= number_format($u['total_alerts_resolved']) ?></td>
                            <td class="td-mono" style="color:var(--accent-yellow)"><?= number_format($u['total_incidents_created']) ?></td>
                            <td>
                                <div style="font-family:var(--font-mono);font-size:12px;color:<?= (0) >= 75 ? 'var(--accent-green)' : 'var(--accent-orange)' ?>">
                                    <?= number_format(0, 1) ?>%
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL -->
    <div style="display:flex;flex-direction:column;gap:16px">
        <!-- My Stats -->
        <div class="card">
            <div class="card-title">◎ My Stats</div>
            <div style="text-align:center;margin-bottom:16px">
                <div style="font-family:var(--font-display);font-size:40px;font-weight:900;color:var(--accent-cyan)">#<?= $myRank ?></div>
                <div style="font-family:var(--font-mono);font-size:10px;color:var(--text-dim)">CURRENT RANK</div>
            </div>
            
            <div style="margin-bottom:12px">
                <div class="flex justify-between" style="font-family:var(--font-mono);font-size:11px;margin-bottom:6px">
                    <span style="color:var(--text-dim)">Level <?= $myStats['level'] ?> → <?= $myStats['level'] + 1 ?></span>
                    <span style="color:var(--accent-cyan)"><?= $levelInfo['progress'] ?>%</span>
                </div>
                <div class="progress-bar-wrap">
                    <div class="progress-bar cyan" style="width:<?= $levelInfo['progress'] ?>%"></div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                <?php $myFullStats = db()->fetchOne("SELECT u.*,
                    (SELECT COUNT(*) FROM alerts WHERE resolved_by = u.id) as total_alerts_resolved,
                    (SELECT COUNT(*) FROM incidents WHERE created_by = u.id) as total_incidents_created,
                    (SELECT COUNT(*) FROM system_logs WHERE analysed_by = u.id) as total_logs_analyzed
                    FROM users u WHERE u.id = ?", [$_SESSION['user_id']]); ?>
                <?php $statItems = [
                    ['Score', number_format($myFullStats['score']), 'var(--accent-cyan)'],
                    ['Level', $myFullStats['level'], 'var(--accent-blue)'],
                    ['Resolved', $myFullStats['total_alerts_resolved'], 'var(--accent-green)'],
                    ['Incidents', $myFullStats['total_incidents_created'], 'var(--accent-yellow)'],
                    ['Logs', $myFullStats['total_logs_analyzed'], 'var(--text-secondary)'],
                    ['Accuracy', number_format(0, 1).'%', (0) >= 75 ? 'var(--accent-green)' : 'var(--accent-orange)'],
                ]; ?>
                <?php foreach ($statItems as [$label, $val, $color]): ?>
                <div style="background:var(--bg-panel);border:1px solid var(--border);border-radius:4px;padding:8px;text-align:center">
                    <div style="font-family:var(--font-display);font-size:18px;color:<?= $color ?>"><?= $val ?></div>
                    <div style="font-family:var(--font-mono);font-size:9px;color:var(--text-dim)"><?= $label ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <div class="card-title">⚡ Recent Activity</div>
            <?php foreach ($recentEvents as $event): ?>
            <div style="padding:7px 0;border-bottom:1px solid rgba(26,58,82,0.3);display:flex;gap:10px;align-items:center">
                <div class="user-avatar-sm" style="width:24px;height:24px;font-size:10px"><?= strtoupper(substr($event['username'] ?? '?', 0, 2)) ?></div>
                <div style="flex:1;min-width:0">
                    <div style="font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($event['description'] ?? ucwords(str_replace('_',' ',$event['event_type']))) ?></div>
                    <div style="font-family:var(--font-mono);font-size:10px;color:var(--text-dim)"><?= htmlspecialchars($event['username'] ?? '—') ?> · <?= timeAgo($event['created_at']) ?></div>
                </div>
                <div style="font-family:var(--font-mono);font-size:11px;color:var(--accent-green);flex-shrink:0">+<?= $event['points'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>