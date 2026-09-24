<?php
/* ============================================================
   MEMBER LAYOUT
   Shared by all pages in /member/
   ============================================================ */
require_once __DIR__ . '/../includes/auth.php';
requireRole(['member']);

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

/* ---------- Member submenu ---------- */
$MEM_MENU = [
    'My Dashboard'  => 'index.php',
    'My Profile'    => 'my_profile.php',
    'My Sacraments' => 'my_sacraments.php',
    'My Giving'     => 'my_giving.php',
    'Day Born'      => 'my_dayborn.php',
];

$current   = basename($_SERVER['PHP_SELF']);
$pageTitle = array_search($current, $MEM_MENU) ?: 'My Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> · <?= e($settings['church_name']) ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.mem-header {
  background: linear-gradient(135deg, #553c9a 0%, #805ad5 100%);
  color: #fff;
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: 0 2px 12px rgba(0,0,0,0.15);
}
.mem-top {
  display: flex; justify-content: space-between; align-items: center;
  padding: 14px 20px; gap: 16px; flex-wrap: wrap;
}
@media (max-width: 700px) {
  .mem-top { flex-direction: column; align-items: stretch;
             text-align: center; padding: 14px 16px; }
}
.mem-brand { display: flex; align-items: center; gap: 12px; }
@media (max-width: 700px) { .mem-brand { justify-content: center; } }
.mem-brand .mem-icon {
  font-size: 26px; width: 44px; height: 44px;
  display: flex; align-items: center; justify-content: center;
  background: rgba(255,255,255,0.15); border-radius: 12px; flex-shrink: 0;
}
.mem-brand .mem-titles { display: flex; flex-direction: column; gap: 2px; }
.mem-brand .mem-title {
  font-size: 17px; font-weight: 800; letter-spacing: 0.2px;
  line-height: 1.2; margin: 0;
}
.mem-brand .mem-sub {
  font-size: 10px; letter-spacing: 1.2px; text-transform: uppercase;
  opacity: 0.75; font-weight: 600;
}
.mem-user {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
@media (max-width: 700px) { .mem-user { justify-content: center; } }
.mem-user .user-name {
  font-size: 13px; font-weight: 600; opacity: 0.95;
  padding-right: 6px; border-right: 1px solid rgba(255,255,255,0.2);
  margin-right: 4px;
}
@media (max-width: 700px) {
  .mem-user .user-name {
    border-right: none; width: 100%; text-align: center;
    padding: 0 0 6px; margin: 0;
  }
}
.mem-user .btn-station,
.mem-user .btn-logout {
  padding: 9px 16px; border-radius: 10px; text-decoration: none;
  font-size: 13px; font-weight: 700; white-space: nowrap;
  transition: all 0.15s ease; display: inline-flex;
  align-items: center; gap: 6px;
}
.mem-user .btn-station { background: #f6e05e; color: #744210; }
.mem-user .btn-station:hover { background: #faf089; transform: translateY(-1px); }
.mem-user .btn-logout {
  background: rgba(255,255,255,0.15); color: #fff;
  border: 1px solid rgba(255,255,255,0.25);
}
.mem-user .btn-logout:hover { background: rgba(255,255,255,0.25); }

.mem-nav {
  display: flex; flex-wrap: wrap; gap: 6px;
  padding: 12px 20px;
  border-top: 1px solid rgba(255,255,255,0.1);
  margin-top: 4px;
}
@media (max-width: 700px) {
  .mem-nav { padding: 12px 16px; justify-content: center; }
  .mem-nav a { padding: 8px 12px; font-size: 12px; }
}
.mem-nav a {
  flex-shrink: 0; padding: 9px 16px; font-size: 13px; font-weight: 600;
  color: rgba(255,255,255,0.85); text-decoration: none; border-radius: 10px;
  white-space: nowrap; transition: all 0.15s ease;
  border: 1px solid transparent;
}
.mem-nav a:hover { background: rgba(255,255,255,0.1); color: #fff; }
.mem-nav a.active {
  background: #fff; color: #553c9a;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.mem-back-bar {
  background: linear-gradient(135deg, #faf5ff 0%, #e9d8fd 100%);
  border-radius: 12px;
  padding: 14px 18px;
  margin: 14px 0 16px;
  box-shadow: 0 2px 10px rgba(85,60,154,0.12);
  border: 2px solid #d6bcfa;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.mem-back-bar .txt {
  font-size: 13px;
  color: #553c9a;
  font-weight: 500;
}
.mem-back-bar .txt strong {
  color: #322659;
  font-weight: 700;
}
.mem-back-bar a {
  padding: 12px 20px;
  font-size: 14px;
  font-weight: 700;
  text-decoration: none;
  border-radius: 10px;
  background: #553c9a;
  color: #fff;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  box-shadow: 0 4px 12px rgba(85,60,154,0.3);
  transition: all 0.15s ease;
}
.mem-back-bar a:hover {
  background: #322659;
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(85,60,154,0.4);
}
@media (max-width: 600px) {
  .mem-back-bar {
    flex-direction: column;
    text-align: center;
    padding: 14px;
  }
  .mem-back-bar a { width: 100%; justify-content: center; }
}
</style>
</head>
<body>

<header class="mem-header">

  <div class="mem-top">
    <div class="mem-brand">
      <div class="mem-icon">🙏</div>
      <div class="mem-titles">
        <h2 class="mem-title"><?= e($pageTitle) ?></h2>
        <div class="mem-sub">Member Portal</div>
      </div>
    </div>

    <div class="mem-user">
      <span class="user-name"><?= e($u['name']) ?></span>
      <a class="btn-station" href="index.php">← Station</a>
      <a class="btn-logout" href="../logout.php">Logout</a>
    </div>
  </div>

  <nav class="mem-nav">
    <?php foreach ($MEM_MENU as $label => $url): ?>
      <a class="<?= $current === $url ? 'active' : '' ?>" href="<?= $url ?>">
        <?= e($label) ?>
      </a>
    <?php endforeach; ?>
  </nav>

</header>

<main>

<div class="mem-back-bar">
  <div class="txt">
    🙏 <strong>Member Portal</strong> — Your personal station records
  </div>
  <a href="index.php">
    <span style="font-size:16px;">←</span> Back to Station Dashboard
  </a>
</div>

<?php if ($f = flash()): ?>
  <div class="flash"><?= e($f) ?></div>
<?php endif; ?>