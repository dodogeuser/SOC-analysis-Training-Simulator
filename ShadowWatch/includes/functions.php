<?php

function sanitize($str) {
    return htmlspecialchars(trim((string)$str), ENT_QUOTES, 'UTF-8');
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function timeAgo($datetime) {
    $now  = new DateTime();
    $ago  = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->y > 0) return $diff->y . 'y ago';
    if ($diff->m > 0) return $diff->m . 'mo ago';
    if ($diff->d > 0) return $diff->d . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'just now';
}

function severityBadge($severity) {
    return '<span class="severity-badge severity-' . sanitize($severity) . '">' . strtoupper(sanitize($severity)) . '</span>';
}

function statusBadge($status) {
    return '<span class="status-badge status-' . sanitize($status) . '">' . ucfirst(sanitize(str_replace('_', ' ', $status))) . '</span>';
}

// awardPoints(userId, points, eventType, refId, refType, description)
// refType param accepted but not stored (schema has no reference_type column — stored in description)
function awardPoints($userId, $points, $eventType, $refId = null, $refType = null, $desc = '') {
    if ($points == 0) return;
    db()->execute(
        "INSERT INTO score_events (user_id, event_type, points, reference_id, description, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())",
        [$userId, $eventType, $points, $refId, $desc]
    );
    db()->execute("UPDATE users SET score = score + ? WHERE id = ?", [$points, $userId]);
    updateUserLevel($userId);
}

function updateUserLevel($userId) {
    $user = db()->fetchOne("SELECT score FROM users WHERE id = ?", [$userId]);
    if (!$user) return;
    $score      = (int)$user['score'];
    $level      = 1;
    $thresholds = [0, 500, 1200, 2500, 4500, 7500, 11000, 16000, 22000, 30000];
    foreach ($thresholds as $i => $threshold) {
        if ($score >= $threshold) $level = $i + 1;
    }
    db()->execute("UPDATE users SET level = ? WHERE id = ?", [$level, $userId]);
}

function getLevelProgress($score) {
    $thresholds = [0, 500, 1200, 2500, 4500, 7500, 11000, 16000, 22000, 30000];
    $labels     = ['Trainee', 'Rookie', 'Analyst I', 'Analyst II', 'Senior Analyst', 'Threat Hunter', 'SOC Lead', 'Red Teamer', 'Cyber Specialist', 'Elite Operator'];
    $level      = 1;
    $currentThreshold = 0;
    $nextThreshold    = 500;

    foreach ($thresholds as $i => $t) {
        if ($score >= $t) {
            $level            = $i + 1;
            $currentThreshold = $t;
            $nextThreshold    = $thresholds[$i + 1] ?? ($t + 10000);
        }
    }

    $range   = max(1, $nextThreshold - $currentThreshold);
    $current = $score - $currentThreshold;
    $percent = min(100, round(($current / $range) * 100));

    return [
        'level'          => $level,
        'label'          => $labels[$level - 1] ?? 'Elite Operator',
        'percent'        => $percent,
        'progress'       => $percent,           // alias
        'current_xp'     => $score,
        'current'        => $currentThreshold,
        'next_threshold' => $nextThreshold,
        'next'           => $nextThreshold,     // alias
    ];
}

function getOpenAlertsCount() {
    $r = db()->fetchOne("SELECT COUNT(*) as cnt FROM alerts WHERE status = 'open'");
    return (int)($r['cnt'] ?? 0);
}

function getCriticalAlertsCount() {
    $r = db()->fetchOne("SELECT COUNT(*) as cnt FROM alerts WHERE status = 'open' AND severity = 'critical'");
    return (int)($r['cnt'] ?? 0);
}

function getLeaderboard($limit = 10) {
    return db()->fetchAll(
        "SELECT id, username, score, level, role FROM users WHERE is_active = 1 ORDER BY score DESC LIMIT ?",
        [$limit]
    );
}

function generateTicketNumber() {
    $year = date('Y');
    $r    = db()->fetchOne("SELECT COUNT(*) as cnt FROM incidents WHERE YEAR(created_at) = ?", [$year]);
    $num  = str_pad((int)($r['cnt'] ?? 0) + 1, 4, '0', STR_PAD_LEFT);
    return "INC-$year-$num";
}

function generateRandomAlert($scenarioId = null) {
    if ($scenarioId) {
        $scenario = db()->fetchOne("SELECT * FROM scenarios WHERE id = ? AND is_active = 1", [$scenarioId]);
    } else {
        $scenario = db()->fetchOne("SELECT * FROM scenarios WHERE is_active = 1 ORDER BY RAND() LIMIT 1");
    }
    if (!$scenario) return null;

    $sourceIps  = ['45.33.32.156', '185.234.219.75', '91.108.4.23',
                   '203.0.113.' . rand(1, 254), '198.51.100.' . rand(1, 254),
                   '192.0.2.' . rand(1, 254)];
    $destIps    = ['10.0.1.' . rand(1, 50), '10.0.2.' . rand(1, 50), '192.168.1.' . rand(1, 50)];
    $protocols  = ['TCP', 'UDP', 'HTTP', 'HTTPS', 'SSH', 'DNS', 'SMTP', 'RDP'];
    $ports      = [22, 80, 443, 3389, 8080, 53, 25, 8443, 4444, 1337];

    $title      = ($scenario['title'] ?? 'Unknown Scenario') . ' — Auto-Generated';
    $sourceIp   = $sourceIps[array_rand($sourceIps)];
    $destIp     = $destIps[array_rand($destIps)];
    $protocol   = $protocols[array_rand($protocols)];
    $port       = $ports[array_rand($ports)];
    $pointsVal  = (int)($scenario['points'] ?? 25);

    $id = db()->insert(
        "INSERT INTO alerts
         (scenario_id, title, description, severity, category,
          source_ip, dest_ip, protocol, dest_port, status, points_value, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', ?, NOW())",
        [$scenario['id'], $title, $scenario['description'] ?? '', $scenario['severity'],
         $scenario['category'], $sourceIp, $destIp, $protocol, $port, $pointsVal]
    );

    return db()->fetchOne("SELECT * FROM alerts WHERE id = ?", [$id]);
}

function checkIPThreatIntel($ip) {
    return db()->fetchOne(
        "SELECT * FROM threat_intel WHERE indicator = ? AND indicator_type = 'ip'",
        [$ip]
    );
}

function checkDomainThreatIntel($domain) {
    return db()->fetchOne(
        "SELECT * FROM threat_intel WHERE indicator = ? AND indicator_type = 'domain'",
        [$domain]
    );
}