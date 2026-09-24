<?php
/* ============================================================
   CATECHIST LAYOUT
   Shared by all pages in /catechist/
   ============================================================ */
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin','catechist']);

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

/* ---------- Catechist submenu ---------- */
$CAT_MENU = [
    'Dashboard'         => 'index.php',
    'Sacraments'        => 'sacraments.php',
    'Members'           => 'members.php',
    'Attendance'        => 'attendance.php',
    'Attendance Report' => 'attendance_report.php',
    'WhatsApp Groups'   => 'wa_groups.php',
];

$current   = basename($_SERVER['PHP_SELF']);
$pageTitle = array_search($current, $CAT_MENU) ?: 'Catechist';

$roleLabel = match($u['role']) {
    'admin'     => 'Station Administrator',
    'catechist' => 'Catechist',
    default     => ucfirst((string)$u['role']),
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> · Catechist · <?= e($settings['church_name']) ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.cat-header {
  background: linear-gradient(135deg, #9c4221 0%, #dd6b20 100%);
  color: #fff;
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: 0 2px 12px rgba(0,0,0,0.15);
}
.cat-top {
  display: flex; justify-content: space-between; align-items: center;
  padding: 14px 20px; gap: 16px; flex-wrap: wrap;
}
@media (max-width: 700px) {
  .cat-top { flex-direction: column; align-items: stretch;
             text-align: center; padding: 14px 16px; }
}
.cat-brand { display: flex; align-items: center; gap: 12px; }
@media (max-width: 700px) { .cat-brand { justify-content: center; } }
.cat-brand .cat-icon {
  font-size: 26px; width: 44px; height: 44px;
  display: flex; align-items: center; justify-content: center;
  background: rgba(255,255,255,0.15); border-radius: 12px; flex-shrink: 0;
}
.cat-brand .cat-titles { display: flex; flex-direction: column; gap: 2px; }
.cat-brand .cat-title {
  font-size: 17px; font-weight: 800; letter-spacing: 0.2px;
  line-height: 1.2; margin: 0;
}
.cat-brand .cat-sub {
  font-size: 10px; letter-spacing: 1.2px; text-transform: uppercase;
  opacity: 0.75; font-weight: 600;
}
.cat-user {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
@media (max-width: 700px) { .cat-user { justify-content: center; } }
.cat-user .user-name {
  font-size: 13px; font-weight: 600; opacity: 0.95;
  padding-right: 6px; border-right: 1px solid rgba(255,255,255,0.2);
  margin-right: 4px;
}
@media (max-width: 700px) {
  .cat-user .user-name {
    border-right: none; width: 100%; text-align: center;
    padding: 0 0 6px; margin: 0;
  }
}
.cat-user .btn-station,
.cat-user .btn-logout {
  padding: 9px 16px; border-radius: 10px; text-decoration: none;
  font-size: 13px; font-weight: 700; white-space: nowrap;
  transition: all 0.15s ease; display: inline-flex;
  align-items: center; gap: 6px;
}
.cat-user .btn-station { background: #f6e05e; color: #744210; }
.cat-user .btn-station:hover { background: #faf089; transform: translateY(-1px); }
.cat-user .btn-logout {
  background: rgba(255,255,255,0.15); color: #fff;
  border: 1px solid rgba(255,255,255,0.25);
}
.cat-user .btn-logout:hover { background: rgba(255,255,255,0.25); }

.cat-nav {
  display: flex; flex-wrap: wrap; gap: 6px;
  padding: 12px 20px;
  border-top: 1px solid rgba(255,255,255,0.1);
  margin-top: 4px;
}
@media (max-width: 700px) {
  .cat-nav { padding: 12px 16px; justify-content: center; }
  .cat-nav a { padding: 8px 12px; font-size: 12px; }
}
.cat-nav a {
  flex-shrink: 0; padding: 9px 16px; font-size: 13px; font-weight: 600;
  color: rgba(255,255,255,0.85); text-decoration: none; border-radius: 10px;
  white-space: nowrap; transition: all 0.15s ease;
  border: 1px solid transparent;
}
.cat-nav a:hover { background: rgba(255,255,255,0.1); color: #fff; }
.cat-nav a.active {
  background: #fff; color: #9c4221;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.cat-back-bar {
  background: linear-gradient(135deg, #fffaf0 0%, #feebc8 100%);
  border-radius: 12px;
  padding: 14px 18px;
  margin: 14px 0 16px;
  box-shadow: 0 2px 10px rgba(156,66,33,0.12);
  border: 2px solid #f6ad55;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.cat-back-bar .txt {
  font-size: 13px;
  color: #7b341e;
  font-weight: 500;
}
.cat-back-bar .txt strong {
  color: #7b341e;
  font-weight: 700;
}
.cat-back-bar a {
  padding: 12px 20px;
  font-size: 14px;
  font-weight: 700;
  text-decoration: none;
  border-radius: 10px;
  background: #9c4221;
  color: #fff;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  box-shadow: 0 4px 12px rgba(156,66,33,0.3);
  transition: all 0.15s ease;
}
.cat-back-bar a:hover {
  background: #7b341e;
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(156,66,33,0.4);
}
@media (max-width: 600px) {
  .cat-back-bar {
    flex-direction: column;
    text-align: center;
    padding: 14px;
  }
  .cat-back-bar a { width: 100%; justify-content: center; }
}
</style>
</head>
<body>

<header class="cat-header">

  <div class="cat-top">
    <div class="cat-brand">
      <div class="cat-icon">📖</div>
      <div class="cat-titles">
        <h2 class="cat-title"><?= e($pageTitle) ?></h2>
        <div class="cat-sub">Catechist</div>
      </div>
    </div>

    <div class="cat-user">
      <span class="user-name"><?= e($u['name']) ?> · <?= e($roleLabel) ?></span>
      <a class="btn-station" href="index.php">← Station</a>
      <a class="btn-logout" href="../logout.php">Logout</a>
    </div>
  </div>

  <nav class="cat-nav">
    <?php foreach ($CAT_MENU as $label => $url): ?>
      <a class="<?= $current === $url ? 'active' : '' ?>" href="<?= $url ?>">
        <?= e($label) ?>
      </a>
    <?php endforeach; ?>
  </nav>

</header>

<main>

<div class="cat-back-bar">
  <div class="txt">
    📖 <strong>Catechist Office</strong> — Faith Formation & Records
  </div>
  <a href="index.php">
    <span style="font-size:16px;">←</span> Back to Station Dashboard
  </a>
</div>

<?php if ($f = flash()): ?>
  <div class="flash"><?= e($f) ?></div>
<?php endif; ?>