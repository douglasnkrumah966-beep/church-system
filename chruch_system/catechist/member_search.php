<?php
/* ============================================================
   MEMBER SEARCH — AJAX JSON ENDPOINT
   ------------------------------------------------------------
   Used by:
     - contributions.php       (live member search)
     - funeral_report.php      (filter by member)
     - wa_groups.php           (suggest members)
     - bulk_message.php        (member picker)

   Returns up to 15 matching members as JSON.
   ============================================================ */

require_once __DIR__ . '/includes/auth.php';
requireLogin();

/* Return JSON, prevent caching */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

/* Only accept GET */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

/* Read the search query */
$q     = trim($_GET['q'] ?? '');
$limit = (int)($_GET['limit'] ?? 15);
if ($limit < 1 || $limit > 50) $limit = 15;

/* Run the search */
$members = [];
try {
    $members = searchMembers($pdo, $q, $limit);
    if (!is_array($members)) $members = [];
} catch (Exception $e) {
    $members = [];
}

/* Shape the response */
$out = [];
foreach ($members as $m) {
    if (!is_array($m)) continue;
    $out[] = [
        'id'    => (int)($m['id'] ?? 0),
        'name'  => (string)($m['full_name'] ?? ''),
        'phone' => (string)($m['phone'] ?? ''),
        'day'   => (string)($m['day_born'] ?? ''),
    ];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);