<?php
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$loggedIn = isLoggedIn();
$db = db();

// Public stats
$totalUsers     = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
$alertsResolved = (int)$db->query("SELECT COUNT(*) FROM alerts WHERE status='resolved'")->fetchColumn();
$scenarios      = (int)$db->query("SELECT COUNT(*) FROM scenarios WHERE is_active=1")->fetchColumn();
$incidents      = (int)$db->query("SELECT COUNT(*) FROM incidents")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About | ShadowWatch</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;600&family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        body { background:var(--bg-void); color:var(--text-primary); }
        .about-nav { display:flex; justify-content:space-between; align-items:center; padding:1rem 2rem; border-bottom:1px solid var(--border); position:sticky; top:0; background:var(--bg-void); z-index:100; }
        .nav-logo { font-family:'Orbitron',sans-serif; font-size:1.1rem; color:var(--cyan); text-decoration:none; }
        .nav-logo span { color:var(--text-muted); font-weight:300; }
        .nav-links a { color:var(--text-secondary); text-decoration:none; margin-left:1.5rem; font-size:.88rem; transition:color .2s; }
        .nav-links a:hover { color:var(--cyan); }
        .nav-links .btn { margin-left:1rem; }

        .about-hero { text-align:center; padding:5rem 2rem 3rem; }
        .about-hero h1 { font-family:'Orbitron',sans-serif; font-size:clamp(2rem,5vw,3.5rem); color:var(--cyan); margin-bottom:1rem; }
        .about-hero p { font-size:1.1rem; color:var(--text-secondary); max-width:700px; margin:0 auto 2rem; line-height:1.8; }

        .about-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; max-width:900px; margin:0 auto 4rem; padding:0 2rem; }
        .astat { text-align:center; background:var(--bg-panel); border:1px solid var(--border); border-radius:10px; padding:1.5rem; }
        .astat-val { font-family:'Orbitron',sans-serif; font-size:2rem; color:var(--cyan); margin-bottom:.25rem; }
        .astat-label { font-size:.78rem; color:var(--text-muted); }

        .modules-section { max-width:1100px; margin:0 auto; padding:0 2rem 5rem; }
        .section-title { font-family:'Orbitron',sans-serif; font-size:1.3rem; color:var(--text-primary); margin-bottom:.5rem; text-align:center; }
        .section-sub { text-align:center; color:var(--text-muted); font-size:.88rem; margin-bottom:2.5rem; }
        .modules-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:1.25rem; }
        .module-card { background:var(--bg-panel); border:1px solid var(--border); border-radius:10px; padding:1.5rem; transition:border-color .2s, transform .2s; }
        .module-card:hover { border-color:var(--cyan); transform:translateY(-3px); }
        .module-icon { font-size:2rem; margin-bottom:.75rem; }
        .module-name { font-family:'Orbitron',sans-serif; font-size:.9rem; color:var(--text-primary); margin-bottom:.5rem; }
        .module-desc { font-size:.82rem; color:var(--text-muted); line-height:1.6; }

        .how-section { background:var(--bg-panel); padding:4rem 2rem; text-align:center; border-top:1px solid var(--border); border-bottom:1px solid var(--border); margin-bottom:4rem; }
        .steps { display:flex; justify-content:center; gap:2rem; flex-wrap:wrap; margin-top:2rem; }
        .step { max-width:200px; }
        .step-num { width:48px; height:48px; border-radius:50%; background:var(--cyan); color:#000; font-family:'Orbitron',sans-serif; font-weight:700; font-size:1.1rem; display:flex; align-items:center; justify-content:center; margin:0 auto .75rem; }
        .step-title { font-size:.88rem; color:var(--text-primary); margin-bottom:.3rem; }
        .step-desc  { font-size:.78rem; color:var(--text-muted); line-height:1.6; }

        .cta-section { text-align:center; padding:4rem 2rem; }
        .cta-section h2 { font-family:'Orbitron',sans-serif; font-size:1.8rem; color:var(--text-primary); margin-bottom:1rem; }
        .cta-section p  { color:var(--text-muted); margin-bottom:2rem; }
        .cta-btns { display:flex; gap:1rem; justify-content:center; flex-wrap:wrap; }

        .about-footer { text-align:center; padding:1.5rem; border-top:1px solid var(--border); color:var(--text-muted); font-size:.8rem; }

        @media(max-width:768px){ .about-stats{grid-template-columns:repeat(2,1fr);} .steps{flex-direction:column; align-items:center;} }
    </style>
</head>
<body>

<nav class="about-nav">
    <a href="index.php" class="nav-logo">SHADOW<span>WATCH</span></a>
    <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="about.php">About</a>
        <?php if ($loggedIn): ?>
            <a href="dashboard.php" class="btn btn-primary btn-sm">Dashboard</a>
        <?php else: ?>
            <a href="login.php" class="btn btn-secondary btn-sm">Login</a>
            <a href="register.php" class="btn btn-primary btn-sm">Get Started</a>
        <?php endif; ?>
    </div>
</nav>

<!-- Hero -->
<section class="about-hero">
    <h1>SOC ANALYST TRAINING PLATFORM</h1>
    <p>ShadowWatch is a gamified Security Operations Center simulator designed to develop real-world analyst skills through hands-on threat detection, incident response, and log analysis in a safe, realistic environment.</p>
</section>

<!-- Live Stats -->
<div class="about-stats">
    <div class="astat">
        <div class="astat-val"><?= number_format($totalUsers) ?></div>
        <div class="astat-label">Active Analysts</div>
    </div>
    <div class="astat">
        <div class="astat-val"><?= number_format($alertsResolved) ?></div>
        <div class="astat-label">Alerts Resolved</div>
    </div>
    <div class="astat">
        <div class="astat-val"><?= number_format($scenarios) ?></div>
        <div class="astat-label">Attack Scenarios</div>
    </div>
    <div class="astat">
        <div class="astat-val"><?= number_format($incidents) ?></div>
        <div class="astat-label">Incidents Managed</div>
    </div>
</div>

<!-- How it works -->
<section class="how-section">
    <h2 class="section-title">HOW IT WORKS</h2>
    <p class="section-sub">Four simple steps to becoming a better analyst</p>
    <div class="steps">
        <div class="step">
            <div class="step-num">1</div>
            <div class="step-title">Register & Start</div>
            <div class="step-desc">Create your analyst account and access the live SOC dashboard immediately.</div>
        </div>
        <div class="step">
            <div class="step-num">2</div>
            <div class="step-title">Triage Alerts</div>
            <div class="step-desc">Review simulated alerts, investigate threats, and take appropriate response actions.</div>
        </div>
        <div class="step">
            <div class="step-num">3</div>
            <div class="step-title">Earn Points</div>
            <div class="step-desc">Correct actions award XP. Resolve incidents, analyse logs, and unlock badges.</div>
        </div>
        <div class="step">
            <div class="step-num">4</div>
            <div class="step-title">Climb the Ranks</div>
            <div class="step-desc">Compete on the leaderboard. Progress from Trainee to Elite Operator.</div>
        </div>
    </div>
</section>

<!-- Modules -->
<section class="modules-section">
    <h2 class="section-title">PLATFORM MODULES</h2>
    <p class="section-sub">Everything you need to train like a real SOC analyst</p>
    <div class="modules-grid">
        <div class="module-card">
            <div class="module-icon">🚨</div>
            <div class="module-name">Alert Console</div>
            <div class="module-desc">Triage live-simulated security alerts across multiple severity levels. Acknowledge, investigate, and resolve threats using real analyst workflows.</div>
        </div>
        <div class="module-card">
            <div class="module-icon">🎭</div>
            <div class="module-name">Attack Scenarios</div>
            <div class="module-desc">Choose from 10+ curated attack scenarios including ransomware, APT campaigns, DDoS attacks, and insider threats. Each scenario generates realistic alerts.</div>
        </div>
        <div class="module-card">
            <div class="module-icon">🎫</div>
            <div class="module-name">Incident Management</div>
            <div class="module-desc">Open, assign, and resolve incident tickets following the PICERL lifecycle. Practice structured incident response documentation.</div>
        </div>
        <div class="module-card">
            <div class="module-icon">📋</div>
            <div class="module-name">Log Analyzer</div>
            <div class="module-desc">Parse and classify simulated system logs. Extract IOCs with regex patterns, identify malicious activity, and earn points for correct verdicts.</div>
        </div>
        <div class="module-card">
            <div class="module-icon">🔍</div>
            <div class="module-name">Threat Intelligence</div>
            <div class="module-desc">Query a built-in IOC database for IPs, domains, and file hashes. Correlate threat intel findings with active alerts and logs.</div>
        </div>
        <div class="module-card">
            <div class="module-icon">🏆</div>
            <div class="module-name">Gamification & Ranks</div>
            <div class="module-desc">A 10-tier rank system from Trainee to Elite Operator. Earn badges, compete on the leaderboard, and track your analyst progression.</div>
        </div>
        <div class="module-card">
            <div class="module-icon">⚙️</div>
            <div class="module-name">Admin Panel</div>
            <div class="module-desc">Platform administrators can manage users, create custom scenarios, monitor analyst performance, and configure system-wide settings.</div>
        </div>
        <div class="module-card">
            <div class="module-icon">🔐</div>
            <div class="module-name">Secure Authentication</div>
            <div class="module-desc">CSRF protection, password hashing, session management, and role-based access control (Analyst / Senior Analyst / Admin).</div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-section">
    <h2>READY TO START TRAINING?</h2>
    <p>Join analysts building real cybersecurity skills on ShadowWatch.</p>
    <div class="cta-btns">
        <?php if ($loggedIn): ?>
            <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
        <?php else: ?>
            <a href="register.php" class="btn btn-primary">Create Free Account</a>
            <a href="login.php" class="btn btn-secondary">Sign In</a>
        <?php endif; ?>
    </div>
</section>

<footer class="about-footer">
    &copy; <?= date('Y') ?> ShadowWatch SOC Simulator &mdash; For training purposes only. All threats are simulated.
</footer>

</body>
</html>
