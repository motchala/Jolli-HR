<?php
/**
 * Notifications endpoint.
 *
 * GET  ?action=list        → JSON array of unread notifications.
 * POST ?action=mark_read   → marks all unread as read; returns {"ok":true}.
 *
 * Always returns JSON. Requires an active login session.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/notifications.php';

header('Content-Type: application/json; charset=utf-8');

require_login();   // redirects to login.php if not logged in

$uid    = (int)$_SESSION['user']['id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'list') {
    $rows = get_notifications($uid);
    $out  = [];
    foreach ($rows as $r) {
        $out[] = [
            'id'      => (int)$r['id'],
            'message' => $r['message'],
            'time'    => $r['created_at'],
        ];
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'mark_read') {
    mark_notifications_read($uid);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action']);
