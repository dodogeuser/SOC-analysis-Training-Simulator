<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? 'fetch';

if (in_array($action, ['analyze_log', 'generate_logs', 'threat_lookup'])) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    validateCsrfToken($input['_csrf'] ?? '');
}

$db = db();

switch ($action) {

    // ── Fetch alerts (GET) ────────────────────────────────────────────────
    case 'fetch':
        $severity = sanitize($_GET['severity'] ?? '');
        $status   = sanitize($_GET['status'] ?? '');
        $category = sanitize($_GET['category'] ?? '');
        $limit    = min((int)($_GET['limit'] ?? 50), 200);
        $offset   = max((int)($_GET['offset'] ?? 0), 0);

        $where = ['1=1'];
        $params = [];

        if ($severity) { $where[] = 'severity = ?'; $params[] = $severity; }
        if ($status)   { $where[] = 'status = ?';   $params[] = $status; }
        if ($category) { $where[] = 'category = ?'; $params[] = $category; }

        $sql = "SELECT a.*, s.title AS scenario_title,
                       u.username AS assigned_username
                FROM alerts a
                LEFT JOIN scenarios s ON a.scenario_id = s.id
                LEFT JOIN users u ON a.assigned_to = u.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY a.created_at DESC
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;

        $alerts = $db->query($sql, $params)->fetchAll();
        $total  = $db->query("SELECT COUNT(*) FROM alerts WHERE " . implode(' AND ', $where), array_slice($params, 0, -2))->fetchColumn();

        jsonResponse(['success' => true, 'alerts' => $alerts, 'total' => (int)$total]);
        break;

    // ── Analyze a single log entry ────────────────────────────────────────
    case 'analyze_log':
        $logId      = (int)($input['log_id'] ?? 0);
        $verdict    = sanitize($input['verdict'] ?? '');
        $confidence = min(100, max(0, (int)($input['confidence'] ?? 50)));

        if (!$logId || !in_array($verdict, ['malicious', 'suspicious', 'benign', 'unknown'])) {
            jsonResponse(['success' => false, 'message' => 'Invalid parameters'], 400);
        }

        $log = $db->query("SELECT * FROM system_logs WHERE id = ?", [$logId])->fetch();
        if (!$log) {
            jsonResponse(['success' => false, 'message' => 'Log not found'], 404);
        }

        // Check if already analysed by this user
        $existing = $db->query(
            "SELECT id FROM score_events WHERE user_id = ? AND reference_id = ? AND reference_type = 'log'",
            [$_SESSION['user_id'], $logId]
        )->fetch();

        if ($existing) {
            jsonResponse(['success' => false, 'message' => 'You have already analysed this log entry.'], 409);
        }

        // Correctness scoring (correct verdict earns points)
        $correctVerdict = $log['log_type'] === 'error' || $log['log_type'] === 'critical' ? 'malicious' : 'benign';
        $isCorrect = $verdict === $correctVerdict;
        $points = $isCorrect ? 15 : 5;

        awardPoints($_SESSION['user_id'], $points, 'log_analysis', $logId, 'log',
            ($isCorrect ? 'Correct' : 'Partial') . " log analysis: {$log['log_type']} → $verdict"
        );

        $db->execute(
            "UPDATE system_logs SET analysed_by = ?, analysed_at = NOW(), verdict = ? WHERE id = ?",
            [$_SESSION['user_id'], $verdict, $logId]
        );

        jsonResponse([
            'success'   => true,
            'correct'   => $isCorrect,
            'points'    => $points,
            'verdict'   => $verdict,
            'expected'  => $correctVerdict,
            'message'   => $isCorrect
                ? "Correct! +{$points} points awarded."
                : "Partial credit. Expected: $correctVerdict. +{$points} points.",
        ]);
        break;

    // ── Generate fake log batch ───────────────────────────────────────────
    case 'generate_logs':
        $count = min((int)($input['count'] ?? 10), 50);
        $types = ['info', 'warning', 'error', 'critical', 'debug'];
        $sources = ['firewall', 'ids', 'antivirus', 'auth', 'web', 'dns', 'smtp', 'vpn'];
        $hosts   = ['ws-001', 'ws-042', 'srv-db01', 'srv-web02', 'srv-mail', 'gw-01', 'lb-01', 'dc-01'];

        $messages = [
            'info'     => ['User login successful', 'Service started', 'Backup completed', 'Config reload OK', 'Health check passed'],
            'warning'  => ['High memory usage detected', 'Disk space below 15%', 'Certificate expiring soon', 'Slow query detected', '5 failed login attempts'],
            'error'    => ['Connection refused on port 22', 'Authentication failure', 'Module load failed', 'Database timeout', 'SSL handshake error'],
            'critical' => ['Ransomware pattern detected', 'Data exfiltration attempt', 'Root privilege escalation', 'Known C2 beacon detected', 'Critical service crash'],
            'debug'    => ['Cache miss: /api/users', 'Thread pool at 80%', 'GC pause 420ms', 'Retry attempt 3/5', 'Token refresh triggered'],
        ];

        $inserted = 0;
        for ($i = 0; $i < $count; $i++) {
            $type    = $types[array_rand($types)];
            $source  = $sources[array_rand($sources)];
            $host    = $hosts[array_rand($hosts)];
            $msgList = $messages[$type];
            $message = $msgList[array_rand($msgList)];
            $ip      = rand(10,192) . '.' . rand(0,255) . '.' . rand(0,255) . '.' . rand(1,254);

            $rawLog = "[" . date('Y-m-d H:i:s') . "] [{$type}] [{$source}] host={$host} src_ip={$ip} msg=\"{$message}\"";

            $db->execute(
                "INSERT INTO system_logs (source, host, log_type, message, raw_log, ip_address, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [$source, $host, $type, $message, $rawLog, $ip]
            );
            $inserted++;
        }

        logActivity($_SESSION['user_id'], 'generate_logs', "Generated $inserted log entries");
        jsonResponse(['success' => true, 'generated' => $inserted]);
        break;

    // ── Threat intel lookup ───────────────────────────────────────────────
    case 'threat_lookup':
        $indicator = sanitize($input['indicator'] ?? '');
        $type      = sanitize($input['type'] ?? 'auto');

        if (!$indicator) {
            jsonResponse(['success' => false, 'message' => 'No indicator provided'], 400);
        }

        // Auto-detect type
        if ($type === 'auto') {
            if (filter_var($indicator, FILTER_VALIDATE_IP)) {
                $type = 'ip';
            } elseif (preg_match('/^[a-f0-9]{32,64}$/i', $indicator)) {
                $type = 'hash';
            } else {
                $type = 'domain';
            }
        }

        $result = null;
        if ($type === 'ip') {
            $result = checkIPThreatIntel($indicator);
        } elseif ($type === 'domain') {
            $result = checkDomainThreatIntel($indicator);
        } else {
            // Hash lookup
            $result = $db->query(
                "SELECT * FROM threat_intel WHERE indicator = ? AND indicator_type = 'hash'",
                [$indicator]
            )->fetch();
        }

        logActivity($_SESSION['user_id'], 'threat_lookup', "Looked up $type: $indicator");

        if ($result) {
            jsonResponse([
                'success'   => true,
                'found'     => true,
                'type'      => $type,
                'indicator' => $indicator,
                'data'      => $result,
            ]);
        } else {
            jsonResponse([
                'success'   => true,
                'found'     => false,
                'type'      => $type,
                'indicator' => $indicator,
                'message'   => 'No threat intelligence found for this indicator.',
            ]);
        }
        break;

    // ── Alert counts (for topbar badge) ──────────────────────────────────
    case 'counts':
        jsonResponse([
            'success'  => true,
            'open'     => (int)getOpenAlertsCount(),
            'critical' => (int)getCriticalAlertsCount(),
        ]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
}
