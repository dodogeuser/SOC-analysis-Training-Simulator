<?php
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$db = db();
$userId = (int)($_GET['id'] ?? $_SESSION['user_id']);

$user = $db->query(
    "SELECT u.*, COUNT(DISTINCT se.id) AS total_events
     FROM users u
     LEFT JOIN score_events se ON se.user_id = u.id
     WHERE u.id = ?
     GROUP BY u.id",
    [$userId]
)->fetch();

if (!$user) {
    header('Location: /dashboard.php');
    exit;
}

$isOwn = ($userId === (int)$_SESSION['user_id']);

// Stats
$alertsResolved = (int)$db->query(
    "SELECT COUNT(*) FROM alerts WHERE resolved_by = ?", [$userId]
)->fetchColumn();

$incidentsCreated = (int)$db->query(
    "SELECT COUNT(*) FROM incidents WHERE created_by = ?", [$userId]
)->fetchColumn();

$logsAnalysed = (int)$db->query(
    "SELECT COUNT(*) FROM system_logs WHERE analysed_by = ?", [$userId]
)->fetchColumn();

$levelProgress = getLevelProgress($user['score']);

// Recent score events
$recentEvents = $db->query(
    "SELECT * FROM score_events WHERE user_id = ? ORDER BY created_at DESC LIMIT 20",
    [$userId]
)->fetchAll();

// Badges
$userBadges = $db->query(
    "SELECT b.* FROM badges b
     JOIN user_badges ub ON ub.badge_id = b.id
     WHERE ub.user_id = ?
     ORDER BY ub.earned_at DESC",
    [$userId]
)->fetchAll();

// All badges for display
$allBadges = $db->query("SELECT * FROM badges ORDER BY points_required ASC")->fetchAll();
$earnedIds = array_column($userBadges, 'id');

// Rank on leaderboard
$rank = $db->query(
    "SELECT COUNT(*) + 1 FROM users WHERE score > ? AND is_active = 1",
    [$user['score']]
)->fetchColumn();

include 'includes/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">
            <?= $isOwn ? 'MY PROFILE' : 'ANALYST PROFILE' ?>
        </h1>
        <p class="page-subtitle">
            <?= $isOwn ? 'Your performance dashboard' : sanitize($user['username']) . '\'s stats' ?>
        </p>
    </div>
    <?php if ($isOwn): ?>
    <div class="page-actions">
        <a href="settings.php" class="btn btn-secondary">
            <i class="fas fa-cog"></i> Settings
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="profile-grid">

    <!-- Identity Card -->
    <div class="card profile-card">
        <div class="profile-avatar-wrap">
            <div class="profile-avatar">
                <?= strtoupper(substr($user['username'], 0, 2)) ?>
            </div>
            <span class="role-badge role-<?= $user['role'] ?>">
                <?= str_replace('_', ' ', strtoupper($user['role'])) ?>
            </span>
        </div>
        <h2 class="profile-name"><?= sanitize($user['username']) ?></h2>
        <p class="profile-email"><?= sanitize($user['email']) ?></p>

        <div class="profile-level-wrap">
            <div class="level-label">
                <span>LEVEL <?= $levelProgress['level'] ?></span>
                <span class="level-name"><?= $levelProgress['label'] ?></span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width:<?= $levelProgress['percent'] ?>%"></div>
            </div>
            <div class="level-xp-info">
                <span><?= number_format($levelProgress['current_xp']) ?> XP</span>
                <span><?= number_format($levelProgress['next_threshold']) ?> XP</span>
            </div>
        </div>

        <div class="profile-meta">
            <div class="meta-item">
                <i class="fas fa-trophy"></i>
                <span>Rank #<?= $rank ?></span>
            </div>
            <div class="meta-item">
                <i class="fas fa-star"></i>
                <span><?= number_format($user['score']) ?> pts</span>
            </div>
            <div class="meta-item">
                <i class="fas fa-calendar"></i>
                <span>Joined <?= date('M Y', strtotime($user['created_at'])) ?></span>
            </div>
            <?php if ($user['last_login']): ?>
            <div class="meta-item">
                <i class="fas fa-clock"></i>
                <span>Active <?= timeAgo($user['last_login']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats Column -->
    <div class="profile-stats-col">

        <!-- Stat Cards -->
        <div class="stat-cards-row">
            <div class="stat-card">
                <div class="stat-icon" style="color:var(--red)"><i class="fas fa-bell-slash"></i></div>
                <div class="stat-body">
                    <div class="stat-value"><?= number_format($alertsResolved) ?></div>
                    <div class="stat-label">Alerts Resolved</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color:var(--orange)"><i class="fas fa-ticket-alt"></i></div>
                <div class="stat-body">
                    <div class="stat-value"><?= number_format($incidentsCreated) ?></div>
                    <div class="stat-label">Incidents Created</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color:var(--cyan)"><i class="fas fa-search"></i></div>
                <div class="stat-body">
                    <div class="stat-value"><?= number_format($logsAnalysed) ?></div>
                    <div class="stat-label">Logs Analysed</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color:var(--green)"><i class="fas fa-medal"></i></div>
                <div class="stat-body">
                    <div class="stat-value"><?= count($earnedIds) ?></div>
                    <div class="stat-label">Badges Earned</div>
                </div>
            </div>
        </div>

        <!-- Badges -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-award"></i> Badges</h3>
                <span class="badge badge-info"><?= count($earnedIds) ?> / <?= count($allBadges) ?></span>
            </div>
            <div class="badges-grid">
                <?php foreach ($allBadges as $badge): ?>
                <?php $earned = in_array($badge['id'], $earnedIds); ?>
                <div class="badge-item <?= $earned ? 'earned' : 'locked' ?>"
                     title="<?= sanitize($badge['description']) ?> (<?= number_format($badge['points_required']) ?> pts required)">
                    <div class="badge-icon"><?= $badge['icon'] ?></div>
                    <div class="badge-name"><?= sanitize($badge['name']) ?></div>
                    <?php if (!$earned): ?>
                    <div class="badge-lock"><i class="fas fa-lock"></i></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-history"></i> Recent Activity</h3>
            </div>
            <?php if ($recentEvents): ?>
            <div class="activity-feed">
                <?php foreach ($recentEvents as $ev): ?>
                <div class="activity-item">
                    <div class="activity-icon <?= $ev['points'] >= 20 ? 'high' : ($ev['points'] >= 10 ? 'med' : 'low') ?>">
                        <i class="fas fa-<?= $ev['points'] >= 20 ? 'fire' : ($ev['points'] >= 10 ? 'bolt' : 'check') ?>"></i>
                    </div>
                    <div class="activity-body">
                        <div class="activity-desc"><?= sanitize($ev['description']) ?></div>
                        <div class="activity-meta">
                            <span class="activity-pts">+<?= $ev['points'] ?> pts</span>
                            <span class="activity-time"><?= timeAgo($ev['created_at']) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state"><i class="fas fa-ghost"></i><p>No activity yet. Start solving alerts!</p></div>
            <?php endif; ?>
        </div>

    </div>
</div>

<style>
.profile-grid { display:grid; grid-template-columns:320px 1fr; gap:1.5rem; align-items:start; }
.profile-card { text-align:center; padding:2rem 1.5rem; }
.profile-avatar-wrap { position:relative; display:inline-block; margin-bottom:1rem; }
.profile-avatar { width:96px; height:96px; border-radius:50%; background:linear-gradient(135deg,var(--cyan),var(--blue)); display:flex; align-items:center; justify-content:center; font-size:2rem; font-weight:700; color:#000; font-family:'Orbitron',sans-serif; border:3px solid var(--cyan); margin:0 auto; }
.role-badge { position:absolute; bottom:-6px; left:50%; transform:translateX(-50%); padding:2px 10px; border-radius:12px; font-size:0.55rem; font-family:'Orbitron',sans-serif; letter-spacing:.05em; white-space:nowrap; }
.role-admin { background:var(--red); color:#fff; }
.role-senior_analyst { background:var(--orange); color:#000; }
.role-analyst { background:var(--cyan); color:#000; }
.profile-name { font-size:1.4rem; font-family:'Orbitron',sans-serif; color:var(--text-primary); margin-bottom:.25rem; }
.profile-email { color:var(--text-muted); font-size:.85rem; margin-bottom:1.5rem; }
.profile-level-wrap { background:var(--bg-card); border-radius:8px; padding:1rem; margin-bottom:1.25rem; }
.level-label { display:flex; justify-content:space-between; font-size:.75rem; margin-bottom:.5rem; }
.level-label span:first-child { font-family:'Orbitron',sans-serif; color:var(--cyan); }
.level-name { color:var(--text-muted); }
.level-xp-info { display:flex; justify-content:space-between; font-size:.7rem; color:var(--text-muted); margin-top:.4rem; }
.profile-meta { display:grid; grid-template-columns:1fr 1fr; gap:.5rem; text-align:left; }
.meta-item { display:flex; align-items:center; gap:.5rem; padding:.4rem .6rem; background:var(--bg-card); border-radius:6px; font-size:.8rem; color:var(--text-secondary); }
.meta-item i { color:var(--cyan); width:14px; }
.profile-stats-col { display:flex; flex-direction:column; gap:1.25rem; }
.stat-cards-row { display:grid; grid-template-columns:repeat(4,1fr); gap:.75rem; }
.stat-card { background:var(--bg-card); border:1px solid var(--border); border-radius:8px; padding:1rem; display:flex; align-items:center; gap:.75rem; }
.stat-icon { font-size:1.5rem; }
.stat-value { font-size:1.4rem; font-family:'Orbitron',sans-serif; color:var(--text-primary); }
.stat-label { font-size:.7rem; color:var(--text-muted); }
.badges-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(80px,1fr)); gap:.75rem; padding:1rem; }
.badge-item { position:relative; text-align:center; padding:.75rem .5rem; border-radius:8px; border:1px solid var(--border); cursor:default; transition:border-color .2s; }
.badge-item.earned { border-color:var(--cyan); background:rgba(0,212,255,.06); }
.badge-item.locked { opacity:.45; filter:grayscale(1); }
.badge-icon { font-size:1.6rem; margin-bottom:.3rem; }
.badge-name { font-size:.65rem; color:var(--text-muted); }
.badge-lock { position:absolute; top:4px; right:4px; font-size:.6rem; color:var(--text-muted); }
.activity-feed { padding:0 1rem 1rem; }
.activity-item { display:flex; gap:.75rem; padding:.6rem 0; border-bottom:1px solid var(--border); }
.activity-item:last-child { border-bottom:none; }
.activity-icon { width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.7rem; flex-shrink:0; margin-top:2px; }
.activity-icon.high { background:rgba(255,56,96,.15); color:var(--red); }
.activity-icon.med  { background:rgba(0,212,255,.12); color:var(--cyan); }
.activity-icon.low  { background:rgba(0,255,136,.1);  color:var(--green); }
.activity-desc { font-size:.82rem; color:var(--text-secondary); }
.activity-meta { display:flex; gap:1rem; margin-top:.2rem; }
.activity-pts  { font-family:'Orbitron',sans-serif; font-size:.7rem; color:var(--green); }
.activity-time { font-size:.7rem; color:var(--text-muted); }
@media(max-width:1024px){ .profile-grid{grid-template-columns:1fr;} .stat-cards-row{grid-template-columns:repeat(2,1fr);} }
</style>

<?php include 'includes/footer.php'; ?>
