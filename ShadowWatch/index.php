<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: /shadowwatch/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ShadowWatch — SOC Training Simulator</title>
    <link rel="stylesheet" href="/shadowwatch/assets/css/app.css">
    <style>
        .landing { min-height: 100vh; display: flex; flex-direction: column; }
        .landing-nav {
            padding: 20px 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
        }
        .landing-hero {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px;
            text-align: center;
        }
        .hero-content { max-width: 800px; }
        .hero-tag {
            display: inline-block;
            background: rgba(0,212,255,0.1);
            border: 1px solid rgba(0,212,255,0.3);
            color: var(--accent-cyan);
            font-family: var(--font-mono);
            font-size: 11px;
            letter-spacing: 3px;
            padding: 6px 16px;
            border-radius: 3px;
            margin-bottom: 24px;
        }
        .hero-title {
            font-family: var(--font-display);
            font-size: clamp(36px, 7vw, 72px);
            font-weight: 900;
            letter-spacing: 6px;
            line-height: 1.1;
            color: var(--text-bright);
            margin-bottom: 16px;
        }
        .hero-title span { color: var(--accent-cyan); }
        .hero-sub {
            font-size: 18px;
            color: var(--text-secondary);
            max-width: 560px;
            margin: 0 auto 40px;
            line-height: 1.7;
        }
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            padding: 40px 60px;
            background: var(--bg-panel);
            border-top: 1px solid var(--border);
        }
        .feature-card {
            padding: 24px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--bg-card);
        }
        .feature-icon { font-size: 28px; margin-bottom: 12px; }
        .feature-title { font-family: var(--font-display); font-size: 13px; letter-spacing: 2px; color: var(--text-bright); margin-bottom: 8px; }
        .feature-desc { font-size: 13px; color: var(--text-secondary); line-height: 1.6; }
        @media (max-width: 768px) {
            .feature-grid { grid-template-columns: 1fr; padding: 24px; }
            .landing-nav { padding: 16px 24px; }
            .landing-hero { padding: 40px 24px; }
        }
    </style>
</head>
<body>
<div class="auth-bg-grid"></div>
<div class="landing">
    <nav class="landing-nav">
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="width:32px;height:32px;background:linear-gradient(135deg,var(--accent-cyan),var(--accent-blue));border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:16px;box-shadow:var(--glow-cyan)">⬡</div>
            <div>
                <div style="font-family:var(--font-display);font-size:14px;letter-spacing:2px;color:var(--text-bright)">SHADOWWATCH</div>
                <div style="font-family:var(--font-mono);font-size:9px;color:var(--text-dim);letter-spacing:3px">SOC SIMULATOR</div>
            </div>
        </div>
        <div style="display:flex;gap:12px;">
            <a href="/shadowwatch/about.php" class="btn btn-ghost btn-sm">About</a>
            <a href="/shadowwatch/login.php" class="btn btn-ghost btn-sm">Login</a>
            <a href="/shadowwatch/register.php" class="btn btn-primary btn-sm">Get Started</a>
        </div>
    </nav>

    <div class="landing-hero">
        <div class="hero-content">
            <div class="hero-tag">◈ CYBERSECURITY TRAINING PLATFORM</div>
            <h1 class="hero-title">MASTER THE<br><span>SOC WORKFLOW</span></h1>
            <p class="hero-sub">
                Simulate real-world cyber threats. Analyze alerts, investigate incidents, 
                and level up your analyst skills in a safe, gamified environment.
            </p>
            <div style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap;">
                <a href="/shadowwatch/register.php" class="btn btn-primary" style="padding:12px 32px;font-size:15px;">
                    ▶ Start Training
                </a>
                <a href="/shadowwatch/login.php" class="btn btn-ghost" style="padding:12px 32px;font-size:15px;">
                    Login →
                </a>
            </div>
            <div style="margin-top:40px;display:flex;gap:40px;justify-content:center;font-family:var(--font-mono);font-size:12px;color:var(--text-secondary);">
                <div><span style="color:var(--accent-cyan);font-size:20px;display:block;font-family:var(--font-display)">50+</span>Scenarios</div>
                <div><span style="color:var(--accent-green);font-size:20px;display:block;font-family:var(--font-display)">8</span>Modules</div>
                <div><span style="color:var(--accent-yellow);font-size:20px;display:block;font-family:var(--font-display)">∞</span>Alerts</div>
            </div>
        </div>
    </div>

    <div class="feature-grid">
        <div class="feature-card">
            <div class="feature-icon">⚠</div>
            <div class="feature-title">LIVE ALERT FEED</div>
            <div class="feature-desc">Receive simulated security alerts in real-time. Triage, investigate and respond like a real SOC analyst.</div>
        </div>
        <div class="feature-card">
            <div class="feature-icon">📋</div>
            <div class="feature-title">INCIDENT MANAGEMENT</div>
            <div class="feature-desc">Create and manage incident tickets. Track investigations from open to resolution with full audit trails.</div>
        </div>
        <div class="feature-card">
            <div class="feature-icon">⊟</div>
            <div class="feature-title">LOG ANALYSIS</div>
            <div class="feature-desc">Analyze firewall, auth, DNS and endpoint logs. Identify malicious patterns and build detection skills.</div>
        </div>
        <div class="feature-card">
            <div class="feature-icon">⬡</div>
            <div class="feature-title">THREAT INTELLIGENCE</div>
            <div class="feature-desc">Query a threat intel database for IOCs. Look up IPs, domains and hashes against known threat actors.</div>
        </div>
        <div class="feature-card">
            <div class="feature-icon">▲</div>
            <div class="feature-title">GAMIFIED RANKING</div>
            <div class="feature-desc">Earn points for every correct action. Level up, unlock badges and compete on the analyst leaderboard.</div>
        </div>
        <div class="feature-card">
            <div class="feature-icon">◉</div>
            <div class="feature-title">ADMIN CONTROL</div>
            <div class="feature-desc">Admins can create custom scenarios, manage users and monitor all analyst activity across the platform.</div>
        </div>
    </div>
</div>
</body>
</html>
