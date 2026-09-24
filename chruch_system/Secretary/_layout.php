<?php
/* ============================================================
   SECRETARY LAYOUT
   Shared by all pages in /secretary/
   ============================================================ */
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin','secretary']);

$u = currentUser();

/* ---------- Ensure $settings ---------- */
if (!isset($settings) || !is_array($settings)) {
    global $pdo;
    try {
        $settings = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch();
    } catch (PDOException $e) { $settings = null; }
    if (!$settings) {
        $settings = ['church_name' => 'Roman Catholic Church', 'currency' => 'GH₵'];
    }
}

/* ---------- Secretary submenu ---------- */
$SEC_MENU = [
    'Dashboard'         => 'index.php',
    'Members'           => 'members.php',
    'Sacraments'        => 'sacraments.php',
    'Day Born'          => 'dayborn.php',
    'Attendance'        => 'attendance.php',
    'Attendance Report' => 'attendance_report.php',
    'WhatsApp Groups'   => 'wa_groups.php',
    'Bulk Message'      => 'bulk_message.php',
];

$current   = basename($_SERVER['PHP_SELF']);
$pageTitle = array_search($current, $SEC_MENU) ?: 'Secretary';

$roleLabel = match($u['role']) {
    'admin'     => 'Station Administrator',
    'secretary' => 'Station Secretary',
    default     => ucfirst((string)$u['role']),
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> · Secretary · <?= e($settings['church_name']) ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.sec-header {
  background: linear-gradient(135deg, #2c5282 0%, #3182ce 100%);
  color: #fff;
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: 0 2px 12px rgba(0,0,0,0.15);
}
.sec-top {
  display: flex; justify-content: space-between; align-items: center;
  padding: 14px 20px; gap: 16px; flex-wrap: wrap;
}
@media (max-width: 700px) {
  .sec-top { flex-direction: column; align-items: stretch;
             text-align: center; padding: 14px 16px; }
}
.sec-brand { display: flex; align-items: center; gap: 12px; }
@media (max-width: 700px) { .sec-brand { justify-content: center; } }
.sec-brand .sec-icon {
  font-size: 26px; width: 44px; height: 44px;
  display: flex; align-items: center; justify-content: center;
  background: rgba(255,255,255,0.15); border-radius: 12px; flex-shrink: 0;
}
.sec-brand .sec-titles { display: flex; flex-direction: column; gap: 2px; }
.sec-brand .sec-title {
  font-size: 17px; font-weight: 800; letter-spacing: 0.2px;
  line-height: 1.2; margin: 0;
}
.sec-brand .sec-sub {
  font-size: 10px; letter-spacing: 1.2px; text-transform: uppercase;
  opacity: 0.75; font-weight: 600;
}
.sec-user {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
@media (max-width: 700px) { .sec-user { justify-content: center; } }
.sec-user .user-name {
  font-size: 13px; font-weight: 600; opacity: 0.95;
  padding-right: 6px; border-right: 1px solid rgba(255,255,255,0.2);
  margin-right: 4px;
}
@media (max-width: 700px) {
  .sec-user .user-name {
    border-right: none; width: 100%; text-align: center;
    padding: 0 0 6px; margin: 0;
  }
}
.sec-user .btn-station,
.sec-user .btn-logout {
  padding: 9px 16px; border-radius: 10px; text-decoration: none;
  font-size: 13px; font-weight: 700; white-space: nowrap;
  transition: all 0.15s ease; display: inline-flex;
  align-items: center; gap: 6px;
}
.sec-user .btn-station { background: #f6e05e; color: #744210; }
.sec-user .btn-station:hover { background: #faf089; transform: translateY(-1px); }
.sec-user .btn-logout {
  background: rgba(255,255,255,0.15); color: #fff;
  border: 1px solid rgba(255,255,255,0.25);
}
.sec-user .btn-logout:hover { background: rgba(255,255,255,0.25); }

.sec-nav {
  display: flex; flex-wrap: wrap; gap: 6px;
  padding: 12px 20px;
  border-top: 1px solid rgba(255,255,255,0.1);
  margin-top: 4px;
}
@media (max-width: 700px) {
  .sec-nav { padding: 12px 16px; justify-content: center; }
  .sec-nav a { padding: 8px 12px; font-size: 12px; }
}
.sec-nav a {
  flex-shrink: 0; padding: 9px 16px; font-size: 13px; font-weight: 600;
  color: rgba(255,255,255,0.85); text-decoration: none; border-radius: 10px;
  white-space: nowrap; transition: all 0.15s ease;
  border: 1px solid transparent;
}
.sec-nav a:hover { background: rgba(255,255,255,0.1); color: #fff; }
.sec-nav a.active {
  background: #fff; color: #2c5282;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

/* ============================================================
   BACK TO STATION BAR
   ============================================================ */
.sec-back-bar {
  background: linear-gradient(135deg, #ebf8ff 0%, #bee3f8 100%);
  border-radius: 12px;
  padding: 14px 18px;
  margin: 14px 0 16px;
  box-shadow: 0 2px 10px rgba(44,82,130,0.12);
  border: 2px solid #90cdf4;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.sec-back-bar .txt {
  font-size: 13px;
  color: #2c5282;
  font-weight: 500;
}
.sec-back-bar .txt strong {
  color: #1a365d;
  font-weight: 700;
}
.sec-back-bar a {
  padding: 12px 20px;
  font-size: 14px;
  font-weight: 700;
  text-decoration: none;
  border-radius: 10px;
  background: #2c5282;
  color: #fff;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  box-shadow: 0 4px 12px rgba(44,82,130,0.3);
  transition: all 0.15s ease;
}
.sec-back-bar a:hover {
  background: #1a365d;
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(44,82,130,0.4);
}
@media (max-width: 600px) {
  .sec-back-bar {
    flex-direction: column;
    text-align: center;
    padding: 14px;
  }
  .sec-back-bar a { width: 100%; justify-content: center; }
}
</style>
</head>
<body>

<header class="sec-header">

  <div class="sec-top">
    <div class="sec-brand">
      <div class="sec-icon">✍️</div>
      <div class="sec-titles">
        <h2 class="sec-title"><?= e($pageTitle) ?></h2>
        <div class="sec-sub">Station Secretary</div>
      </div>
    </div>

    <div class="sec-user">
      <span class="user-name"><?= e($u['name']) ?> · <?= e($roleLabel) ?></span>
      <a class="btn-station" href="index.php">← Station</a>
      <a class="btn-logout" href="../logout.php">Logout</a>
    </div>
  </div>

  <nav class="sec-nav">
    <?php foreach ($SEC_MENU as $label => $url): ?>
      <a class="<?= $current === $url ? 'active' : '' ?>" href="<?= $url ?>">
        <?= e($label) ?>
      </a>
    <?php endforeach; ?>
  </nav>

</header>

<main>

<!-- ============================================================
     BACK TO STATION BAR
     ============================================================ -->
<div class="sec-back-bar">
  <div class="txt">
    ✍️ <strong>Secretary's Office</strong> — Station Records
  </div>
  <a href="index.php">
    <span style="font-size:16px;">←</span> Back to Station Dashboard
  </a>
</div>

<?php if ($f = flash()): ?>
  <div class="flash"><?= e($f) ?></div>
<?php endif; ?>