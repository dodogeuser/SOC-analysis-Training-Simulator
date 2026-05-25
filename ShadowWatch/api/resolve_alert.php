<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
validateCsrfToken($input['_csrf'] ?? '');

$action  = sanitize($input['action'] ?? 'acknowledge');
$alertId = (int)($input['alert_id'] ?? 0);

if (!$alertId) {
    jsonResponse(['success' => false, 'message' => 'Alert ID required'], 400);
}

$db = db();
$alert = $db->query("SELECT * FROM alerts WHERE id = ?", [$alertId])->fetch();

if (!$alert) {
    jsonResponse(['success' => false, 'message' => 'Alert not found'], 404);
}

switch ($action) {

    case 'acknowledge':
        if ($alert['status'] !== 'open') {
            jsonResponse(['success' => false, 'message' => 'Alert is not open'], 409);
        }

        $db->execute(
            "UPDATE alerts SET status = 'acknowledged', assigned_to = ?, acknowledged_at = NOW() WHERE id = ?",
            [$_SESSION['user_id'], $alertId]
        );

        awardPoints($_SESSION['user_id'], 5, 'alert_ack', $alertId, 'alert',
            "Acknowledged alert #{$alertId}: {$alert['title']}");

        logActivity($_SESSION['user_id'], 'acknowledge_alert', "Acknowledged alert #{$alertId}");

        jsonResponse([
            'success' => true,
            'message' => 'Alert acknowledged. +5 points.',
            'points'  => 5,
            'status'  => 'acknowledged',
        ]);
        break;

    case 'resolve':
        if ($alert['status'] === 'resolved') {
            jsonResponse(['success' => false, 'message' => 'Alert already resolved'], 409);
        }

        $selectedAction = sanitize($input['resolution_action'] ?? '');
        $notes          = sanitize($input['notes'] ?? '');

        $validActions = [
            'block_ip'       => ['label' => 'Block IP',          'points' => 25],
            'isolate_host'   => ['label' => 'Isolate Host',       'points' => 30],
            'reset_password' => ['label' => 'Reset Password',     'points' => 15],
            'patch_system'   => ['label' => 'Patch System',       'points' => 20],
            'update_rules'   => ['label' => 'Update Firewall Rules','points'=> 20],
            'scan_system'    => ['label' => 'Run Full Scan',      'points' => 15],
            'monitor'        => ['label' => 'Monitor & Watch',    'points' => 10],
            'false_positive' => ['label' => 'Mark False Positive','points' => 5],
        ];

        if ($selectedAction && !isset($validActions[$selectedAction])) {
            jsonResponse(['success' => false, 'message' => 'Invalid resolution action'], 400);
        }

        // Base points from scenario, fallback to severity-based
        $severityPoints = ['critical' => 50, 'high' => 30, 'medium' => 20, 'low' => 10, 'info' => 5];
        $basePoints = $alert['points_value'] ?? ($severityPoints[$alert['severity']] ?? 10);
        $actionPoints = $selectedAction ? $validActions[$selectedAction]['points'] : 0;
        $totalPoints = $basePoints + $actionPoints;

        $db->execute(
            "UPDATE alerts
             SET status = 'resolved', assigned_to = ?, resolved_by = ?,
                 resolved_at = NOW(), resolution_notes = ?
             WHERE id = ?",
            [$_SESSION['user_id'], $_SESSION['user_id'], $notes ?: null, $alertId]
        );

        awardPoints($_SESSION['user_id'], $totalPoints, 'alert_resolve', $alertId, 'alert',
            "Resolved alert #{$alertId}: {$alert['title']}" . ($selectedAction ? " via {$validActions[$selectedAction]['label']}" : ""));

        logActivity($_SESSION['user_id'], 'resolve_alert',
            "Resolved alert #{$alertId} ({$alert['severity']}) action={$selectedAction}");

        jsonResponse([
            'success'      => true,
            'message'      => "Alert resolved! +{$totalPoints} points awarded.",
            'points'       => $totalPoints,
            'base_points'  => $basePoints,
            'action_points'=> $actionPoints,
            'status'       => 'resolved',
        ]);
        break;

    case 'false_positive':
        $db->execute(
            "UPDATE alerts SET status = 'false_positive', resolved_by = ?, resolved_at = NOW() WHERE id = ?",
            [$_SESSION['user_id'], $alertId]
        );

        awardPoints($_SESSION['user_id'], 5, 'false_positive', $alertId, 'alert',
            "Marked alert #{$alertId} as false positive");

        jsonResponse([
            'success' => true,
            'message' => 'Alert marked as false positive. +5 points.',
            'points'  => 5,
            'status'  => 'false_positive',
        ]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
}
