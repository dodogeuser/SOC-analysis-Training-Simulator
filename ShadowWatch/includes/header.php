<?php
// includes/header.php - Reusable page header with sidebar
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

requireLogin();
$currentUser = getCurrentUser();
$openAlerts = getOpenAlertsCount();
$criticalAlerts = getCriticalAlertsCount();
$initials = strtoupper(substr($currentUser['username'], 0, 2));

$pageTitle = $pageTitle ?? 'Dashboard';
$currentPage = $currentPage ?? '';

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $csrfToken ?>">
    <title><?= htmlspecialchars($pageTitle) ?> — ShadowWatch</title>
    <link rel="stylesheet" href="/shadowwatch/assets/css/app.css">
    <?= $extraStyles ?? '' ?>
</head>
<body>
<div class="app-layout">
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <div class="logo-icon">⬡</div>
            <div>
                <div class="logo-text">SHADOW<br>WATCH</div>
                <div class="logo-sub">SOC SIMULATOR</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">Operations</div>
            <a href="/shadowwatch/dashboard.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <span class="nav-icon">◈</span> Dashboard
            </a>
            <a href="/shadowwatch/alerts.php" class="nav-item <?= $currentPage === 'alerts' ? 'active' : '' ?>">
                <span class="nav-icon">⚠</span> Alerts
                <?php if ($openAlerts > 0): ?>
                    <span class="nav-badge" id="alert-count-badge"><?= $openAlerts ?></span>
                <?php endif; ?>
            </a>
            <a href="/shadowwatch/incidents.php" class="nav-item <?= $currentPage === 'incidents' ? 'active' : '' ?>">
                <span class="nav-icon">📋</span> Incidents
            </a>
            <a href="/shadowwatch/logs.php" class="nav-item <?= $currentPage === 'logs' ? 'active' : '' ?>">
                <span class="nav-icon">⊟</span> Log Analyzer
            </a>

            <div class="nav-section">Intelligence</div>
            <a href="/shadowwatch/scenarios.php" class="nav-item <?= $currentPage === 'scenarios' ? 'active' : '' ?>">
                <span class="nav-icon">⬡</span> Threat Intel
            </a>
            <a href="/shadowwatch/leaderboard.php" class="nav-item <?= $currentPage === 'leaderboard' ? 'active' : '' ?>">
                <span class="nav-icon">▲</span> Leaderboard
            </a>

            <?php if (isSeniorAnalyst()): ?>
            <div class="nav-section">Management</div>
            <a href="/shadowwatch/admin/users.php" class="nav-item <?= $currentPage === 'admin_users' ? 'active' : '' ?>">
                <span class="nav-icon">◉</span> Users
                <?php if (isAdmin()): ?><span class="admin-badge">ADMIN</span><?php endif; ?>
            </a>
            <a href="/shadowwatch/admin/scenarios.php" class="nav-item <?= $currentPage === 'admin_scenarios' ? 'active' : '' ?>">
                <span class="nav-icon">⬡</span> Scenarios
            </a>
            <a href="/shadowwatch/admin/logs.php" class="nav-item <?= $currentPage === 'admin_logs' ? 'active' : '' ?>">
                <span class="nav-icon">⊟</span> Audit Logs
            </a>
            <a href="/shadowwatch/admin/settings.php" class="nav-item <?= $currentPage === 'admin_settings' ? 'active' : '' ?>">
                <span class="nav-icon">⚙</span> Settings
            </a>
            <?php endif; ?>

            <div class="nav-section">Account</div>
            <a href="/shadowwatch/profile.php" class="nav-item <?= $currentPage === 'profile' ? 'active' : '' ?>">
                <span class="nav-icon">◎</span> Profile
            </a>
            <a href="/shadowwatch/settings.php" class="nav-item <?= $currentPage === 'settings' ? 'active' : '' ?>">
                <span class="nav-icon">⚙</span> Settings
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-mini">
                <div class="user-avatar-sm"><?= $initials ?></div>
                <div class="user-mini-info">
                    <div class="user-mini-name"><?= htmlspecialchars($currentUser['username']) ?></div>
                    <div class="user-mini-role"><?= strtoupper(str_replace('_', ' ', $currentUser['role'])) ?></div>
                </div>
                <a href="/shadowwatch/includes/logout.php" title="Logout" style="color:var(--text-dim);text-decoration:none;font-size:16px;margin-left:4px;" onclick="return confirm('Logout?')">⏻</a>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <!-- TOPBAR -->
        <header class="topbar">
            <span class="topbar-title"><?= htmlspecialchars($pageTitle) ?></span>
            
            <div class="topbar-right">
                <?php if ($criticalAlerts > 0): ?>
                <a href="/shadowwatch/alerts.php?severity=critical" class="topbar-alerts">
                    ⚠ <span id="topbar-alert-count"><?= $criticalAlerts ?> CRITICAL</span>
                </a>
                <?php endif; ?>
                
                <div class="threat-level">
                    <div class="threat-indicator"></div>
                    <span>THREAT LVL: <?= $criticalAlerts > 0 ? '<span style="color:var(--accent-red)">HIGH</span>' : '<span style="color:var(--accent-yellow)">ELEVATED</span>' ?></span>
                </div>
                
                <span class="topbar-clock" id="live-clock"></span>
                
                <div class="flex-center gap-8">
                    <span class="level-badge" title="Level <?= $currentUser['level'] ?>"><?= $currentUser['level'] ?></span>
                    <span style="font-family:var(--font-mono);font-size:12px;color:var(--accent-cyan)"><?= number_format($currentUser['score']) ?> PTS</span>
                </div>
            </div>
        </header>

        <!-- PAGE BODY -->
        <div class="page-content">
