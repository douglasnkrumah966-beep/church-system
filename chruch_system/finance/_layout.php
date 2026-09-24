<?php
/* ============================================================
   FINANCE LAYOUT
   Shared by all pages in /finance/
   ============================================================ */
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin','finance']);

$u = currentUser();

if (!isset($settings) || !is_array($settings)) {
    global $pdo;
    try {
        $settings = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch();
    } catch (PDOException $e) { $settings = null; }
    if (!$settings) {
        $settings = ['church_name' => 'Roman Catholic Church', 'currency' => 'GH₵'];
    }
}

$FIN_MENU = [
    'Dashboard'           => 'index.php',
    'First Collection'    => 'first_collection.php',
    'Day Born Collection' => 'dayborn_collection.php',
    'Contributions'       => 'contributions.php',
    'Expenses'            => 'expenses.php',
    'Cashbook'            => 'cashbook.php',
    'Funeral Report'      => 'funeral_report.php',
    'Reports'             => 'reports.php',
    'Financial Settings'  => 'financial_settings.php',
];

$current   = basename($_SERVER['PHP_SELF']);
$pageTitle = array_search($current, $FIN_MENU) ?: 'Finance';

$roleLabel = match($u['role']) {
    'admin'     => 'Station Administrator',
    'finance'   => 'Finance Officer',
    default     => ucfirst((string)$u['role']),
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> · Finance · <?= e($settings['church_name']) ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.fin-header {
  background: linear-gradient(135deg, #22543d 0%, #2f855a 100%);
  color: #fff;
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: 0 2px 12px rgba(0,0,0,0.15);
}

.fin-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 20px;
  gap: 16px;
  flex-wrap: wrap;
}
@media (max-width: 700px) {
  .fin-top {
    flex-direction: column;
    align-items: stretch;
    text-align: center;
    padding: 14px 16px;
  }
}

.fin-brand {
  display: flex;
  align-items: center;
  gap: 12px;
}
@media (max-width: 700px) {
  .fin-brand { justify-content: center; }
}
.fin-brand .fin-icon {
  font-size: 26px;
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255,255,255,0.15);
  border-radius: 12px;
  flex-shrink: 0;
}
.fin-brand .fin-titles { display: flex; flex-direction: column; gap: 2px; }
.fin-brand .fin-title {
  font-size: 17px;
  font-weight: 800;
  letter-spacing: 0.2px;
  line-height: 1.2;
  margin: 0;
}
.fin-brand .fin-sub {
  font-size: 10px;
  letter-spacing: 1.2px;
  text-transform: uppercase;
  opacity: 0.7;
  font-weight: 600;
}

.fin-user {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
@media (max-width: 700px) {
  .fin-user { justify-content: center; }
}
.fin-user .user-name {
  font-size: 13px;
  font-weight: 600;
  opacity: 0.95;
  padding-right: 6px;
  border-right: 1px solid rgba(255,255,255,0.2);
  margin-right: 4px;
}
@media (max-width: 700px) {
  .fin-user .user-name {
    border-right: none;
    width: 100%;
    text-align: center;
    padding: 0 0 6px;
    margin: 0;
  }
}
.fin-user .btn-station,
.fin-user .btn-logout {
  padding: 9px 16px;
  border-radius: 10px;
  text-decoration: none;
  font-size: 13px;
  font-weight: 700;
  white-space: nowrap;
  transition: all 0.15s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.fin-user .btn-station { background: #f6e05e; color: #744210; }
.fin-user .btn-station:hover { background: #faf089; transform: translateY(-1px); }
.fin-user .btn-logout {
  background: rgba(255,255,255,0.15);
  color: #fff;
  border: 1px solid rgba(255,255,255,0.25);
}
.fin-user .btn-logout:hover { background: rgba(255,255,255,0.25); }

/* Nav — now wraps so all items are visible */
.fin-nav {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  padding: 12px 20px;
  border-top: 1px solid rgba(255,255,255,0.1);
  margin-top: 4px;
}
@media (max-width: 700px) {
  .fin-nav {
    padding: 12px 16px;
    justify-content: center;
  }
  .fin-nav a {
    padding: 8px 12px;
    font-size: 12px;
  }
}
.fin-nav a {
  flex-shrink: 0;
  padding: 9px 16px;
  font-size: 13px;
  font-weight: 600;
  color: rgba(255,255,255,0.85);
  text-decoration: none;
  border-radius: 10px;
  white-space: nowrap;
  transition: all 0.15s ease;
  border: 1px solid transparent;
}
.fin-nav a:hover {
  background: rgba(255,255,255,0.1);
  color: #fff;
}
.fin-nav a.active {
  background: #fff;
  color: #22543d;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.fin-back-bar {
  background: #fff;
  border-radius: 12px;
  padding: 12px 16px;
  margin: 14px 0 16px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.05);
  border: 1px solid #edf2f7;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.fin-back-bar .txt { font-size: 12px; color: #718096; }
.fin-back-bar .txt strong { color: #22543d; }
.fin-back-bar a {
  padding: 10px 16px;
  font-size: 13px;
  font-weight: 700;
  text-decoration: none;
  border-radius: 8px;
  background: #2f855a;
  color: #fff;
  white-space: nowrap;
}
.fin-back-bar a:hover { background: #276749; }
</style>
</head>
<body>

<header class="fin-header">

  <div class="fin-top">
    <div class="fin-brand">
      <div class="fin-icon">💰</div>
      <div class="fin-titles">
        <h2 class="fin-title"><?= e($pageTitle) ?></h2>
        <div class="fin-sub">Finance Department</div>
      </div>
    </div>

    <div class="fin-user">
      <span class="user-name"><?= e($u['name']) ?> · <?= e($roleLabel) ?></span>
      <a class="btn-station" href="../index.php">← Station</a>
      <a class="btn-logout" href="../logout.php">Logout</a>
    </div>
  </div>

  <nav class="fin-nav">
    <?php foreach ($FIN_MENU as $label => $url): ?>
      <a class="<?= $current === $url ? 'active' : '' ?>" href="<?= $url ?>">
        <?= e($label) ?>
      </a>
    <?php endforeach; ?>
  </nav>

</header>

<main>

<div class="fin-back-bar">
  <div class="txt">
    💰 You are in the <strong>Finance Department</strong>
  </div>
  <a href="../index.php">← Back to Station Dashboard</a>
</div>

<?php if ($f = flash()): ?>
  <div class="flash"><?= e($f) ?></div>
<?php endif; ?>