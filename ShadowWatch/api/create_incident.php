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

$action = sanitize($input['action'] ?? 'create');
$db = db();

switch ($action) {

    case 'create':
        $title       = sanitize($input['title'] ?? '');
        $description = sanitize($input['description'] ?? '');
        $severity    = sanitize($input['severity'] ?? 'medium');
        $category    = sanitize($input['category'] ?? 'other');
        $alertId     = (int)($input['alert_id'] ?? 0);

        if (!$title) {
            jsonResponse(['success' => false, 'message' => 'Title is required'], 400);
        }

        $validSeverities = ['critical','high','medium','low','info'];
        $validCategories = ['malware','phishing','ddos','insider_threat','data_breach','ransomware','apt','other'];

        if (!in_array($severity, $validSeverities)) $severity = 'medium';
        if (!in_array($category, $validCategories)) $category = 'other';

        $ticketNumber = generateTicketNumber();

        $incidentId = $db->insert(
            "INSERT INTO incidents
             (ticket_number, title, description, severity, category,
              status, created_by, assigned_to, alert_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 'open', ?, NULL, ?, NOW(), NOW())",
            [$ticketNumber, $title, $description, $severity, $category,
             $_SESSION['user_id'], $alertId ?: null]
        );

        // Link alert if provided
        if ($alertId) {
            $db->execute(
                "UPDATE alerts SET incident_id = ? WHERE id = ? AND incident_id IS NULL",
                [$incidentId, $alertId]
            );
        }

        awardPoints($_SESSION['user_id'], 25, 'incident_create', $incidentId, 'incident',
            "Created incident #{$ticketNumber}: {$title}");

        logActivity($_SESSION['user_id'], 'create_incident',
            "Created incident #{$ticketNumber} [{$severity}] {$title}");

        jsonResponse([
            'success'       => true,
            'message'       => "Incident created! Ticket #{$ticketNumber}. +25 points.",
            'incident_id'   => (int)$incidentId,
            'ticket_number' => $ticketNumber,
            'points'        => 25,
        ]);
        break;

    case 'update_status':
        $incidentId = (int)($input['incident_id'] ?? 0);
        $status     = sanitize($input['status'] ?? '');
        $notes      = sanitize($input['notes'] ?? '');

        $validStatuses = ['open','investigating','contained','eradicated','recovering','closed'];

        if (!$incidentId || !in_array($status, $validStatuses)) {
            jsonResponse(['success' => false, 'message' => 'Invalid parameters'], 400);
        }

        $incident = $db->query("SELECT * FROM incidents WHERE id = ?", [$incidentId])->fetch();
        if (!$incident) {
            jsonResponse(['success' => false, 'message' => 'Incident not found'], 404);
        }

        $extra = [];
        $params = [$status, $notes ?: null];

        if ($status === 'closed' && $incident['status'] !== 'closed') {
            $extra[] = 'closed_at = NOW()';
            awardPoints($_SESSION['user_id'], 50, 'incident_close', $incidentId, 'incident',
                "Closed incident #{$incident['ticket_number']}");
        }

        $extraSql = $extra ? ', ' . implode(', ', $extra) : '';
        $params[] = $incidentId;

        $db->execute(
            "UPDATE incidents SET status = ?, notes = ?, updated_at = NOW(){$extraSql} WHERE id = ?",
            $params
        );

        logActivity($_SESSION['user_id'], 'update_incident',
            "Updated incident #{$incident['ticket_number']} status → $status");

        $pts = $status === 'closed' ? 50 : 0;
        jsonResponse([
            'success' => true,
            'message' => 'Incident updated.' . ($pts ? " +{$pts} points for closing!" : ''),
            'points'  => $pts,
            'status'  => $status,
        ]);
        break;

    case 'assign':
        // Senior analyst / admin only
        if (!in_array($_SESSION['role'] ?? 'analyst', ['senior_analyst', 'admin'])) {
            jsonResponse(['success' => false, 'message' => 'Insufficient permissions'], 403);
        }

        $incidentId = (int)($input['incident_id'] ?? 0);
        $assigneeId = (int)($input['assignee_id'] ?? 0);

        $incident = $db->query("SELECT * FROM incidents WHERE id = ?", [$incidentId])->fetch();
        if (!$incident) {
            jsonResponse(['success' => false, 'message' => 'Incident not found'], 404);
        }

        if ($assigneeId) {
            $assignee = $db->query("SELECT id, username FROM users WHERE id = ?", [$assigneeId])->fetch();
            if (!$assignee) jsonResponse(['success' => false, 'message' => 'User not found'], 404);
        }

        $db->execute(
            "UPDATE incidents SET assigned_to = ?, updated_at = NOW() WHERE id = ?",
            [$assigneeId ?: null, $incidentId]
        );

        logActivity($_SESSION['user_id'], 'assign_incident',
            "Assigned incident #{$incident['ticket_number']} to user #{$assigneeId}");

        jsonResponse([
            'success' => true,
            'message' => $assigneeId
                ? "Incident assigned to {$assignee['username']}."
                : 'Incident unassigned.',
        ]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
}
