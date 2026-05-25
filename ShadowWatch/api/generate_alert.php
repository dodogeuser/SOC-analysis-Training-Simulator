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

$scenarioId = (int)($input['scenario_id'] ?? 0);
$count = min((int)($input['count'] ?? 1), 10);

$db = db();

if ($scenarioId > 0) {
    $scenario = $db->query("SELECT * FROM scenarios WHERE id = ? AND is_active = 1", [$scenarioId])->fetch();
    if (!$scenario) {
        jsonResponse(['success' => false, 'message' => 'Scenario not found'], 404);
    }
}

$generated = [];
for ($i = 0; $i < $count; $i++) {
    $alert = generateRandomAlert($scenarioId ?: null);
    $generated[] = $alert;
}

logActivity($_SESSION['user_id'], 'generate_alert', "Generated $count alert(s)" . ($scenarioId ? " for scenario #$scenarioId" : ""));

jsonResponse(['success' => true, 'alerts' => $generated, 'count' => count($generated)]);
