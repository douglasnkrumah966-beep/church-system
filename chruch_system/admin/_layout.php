<?php
/* ============================================================
   ADMIN LAYOUT — System Administrator only
   ------------------------------------------------------------
   Single clean header. No duplicate nav bars.
   ============================================================ */
require_once __DIR__ . '/../includes/auth.php';
requireSysadmin();

$u = currentUser();

/* ---------- Admin submenu ---------- */
$ADMIN_MENU = [
    'Dashboard'     => 'index.php',
    'Users'         => 'users.php',
    'Roles'         => 'roles.php',
    'Permissions'   => 'permissions.php',
    'Security'      => 'security.php',
    'Database'      => 'database.php',
    'Backups'       => 'backups.php',
    'System Logs'   => 'system_logs.php',
    'Activity Logs' => 'activity_logs.php',
    'Settings'      => 'settings.php',
    'Maintenance'   => 'maintenance.php',
];

$current   = basename($_SERVER['PHP_SELF']);
$pageTitle = array_search($current, $ADMIN_MENU) ?: 'Admin';

/* ---------- Ensure $settings exists ---------- */
if (!isset($settings) || !is_array($settings)) {
    global $pdo;
    try {
        $settings = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch();
    } catch (PDOException $e) { $settings = null; }
    if (!$settings) {
        $settings = ['church_name' => 'Roman Catholic Church', 'currency' => 'GH₵'];
    }
}

/* ---------- Log page view ---------- */
try {
    $pdo->prepare("INSERT INTO activity_logs (user_id, username, action, details, ip)
                   VALUES (?,?,?,?,?)")
        ->execute([
            $u['id'], $u['username'],
            'view_admin', $pageTitle, $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> · Admin · <?= e($settings['church_name']) ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
/* ============================================================
   ADMIN HEADER
   ============================================================ */
.admin-header {
  background: linear-gradient(135deg, #1a365d 0%, #2c5282 100%);
  color: #fff;
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: 0 2px 12px rgba(0,0,0,0.15);
}

/* Top row */
.admin-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 20px;
  gap: 16px;
  flex-wrap: wrap;
}
@media (max-width: 700px) {
  .admin-top {
    flex-direction: column;
    align-items: stretch;
    text-align: center;
    padding: 14px 16px;
  }
}

/* Brand */
.admin-brand {
  display: flex;
  align-items: center;
  gap: 12px;
}
@media (max-width: 700px) { .admin-brand { justify-content: center; } }
.admin-brand .admin-icon {
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
.admin-brand .admin-titles { display: flex; flex-direction: column; gap: 2px; }
.admin-brand .admin-title {
  font-size: 17px;
  font-weight: 800;
  letter-spacing: 0.2px;
  line-height: 1.2;
  margin: 0;
}
.admin-brand .admin-sub {
  font-size: 10px;
  letter-spacing: 1.2px;
  text-transform: uppercase;
  opacity: 0.75;
  font-weight: 600;
}

/* User area */
.admin-user {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
@media (max-width: 700px) { .admin-user { justify-content: center; } }
.admin-user .user-name {
  font-size: 13px;
  font-weight: 600;
  opacity: 0.95;
  padding-right: 6px;
  border-right: 1px solid rgba(255,255,255,0.2);
  margin-right: 4px;
}
@media (max-width: 700px) {
  .admin-user .user-name {
    border-right: none;
    width: 100%;
    text-align: center;
    padding: 0 0 6px;
    margin: 0;
  }
}
.admin-user .btn-station,
.admin-user .btn-logout {
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
.admin-user .btn-station {
  background: #f6e05e;
  color: #744210;
}
.admin-user .btn-station:hover {
  background: #faf089;
  transform: translateY(-1px);
}
.admin-user .btn-logout {
  background: rgba(255,255,255,0.15);
  color: #fff;
  border: 1px solid rgba(255,255,255,0.25);
}
.admin-user .btn-logout:hover { background: rgba(255,255,255,0.25); }

/* Nav strip — wraps on all sizes */
.admin-nav {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  padding: 12px 20px;
  border-top: 1px solid rgba(255,255,255,0.1);
  margin-top: 4px;
}
@media (max-width: 700px) {
  .admin-nav { padding: 12px 16px; justify-content: center; }
  .admin-nav a { padding: 8px 12px; font-size: 12px; }
}
.admin-nav a {
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
.admin-nav a:hover {
  background: rgba(255,255,255,0.1);
  color: #fff;
}
.admin-nav a.active {
  background: #fff;
  color: #1a365d;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

/* Back bar */
.back-bar {
  background: linear-gradient(135deg, #ebf8ff 0%, #bee3f8 100%);
  border-radius: 12px;
  padding: 14px 18px;
  margin: 14px 0 16px;
  box-shadow: 0 2px 10px rgba(26,54,93,0.12);
  border: 2px solid #90cdf4;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.back-bar .txt {
  font-size: 13px;
  color: #2c5282;
  font-weight: 500;
}
.back-bar .txt strong { color: #1a365d; font-weight: 700; }
.back-bar a {
  padding: 12px 20px;
  font-size: 14px;
  font-weight: 700;
  text-decoration: none;
  border-radius: 10px;
  background: #1a365d;
  color: #fff;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  box-shadow: 0 4px 12px rgba(26,54,93,0.3);
  transition: all 0.15s ease;
}
.back-bar a:hover {
  background: #2c5282;
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(26,54,93,0.4);
}
@media (max-width: 600px) {
  .back-bar {
    flex-direction: column;
    text-align: center;
    padding: 14px;
  }
  .back-bar a { width: 100%; justify-content: center; }
}
</style>
</head>
<body>

<header class="admin-header">

  <div class="admin-top">
    <div class="admin-brand">
      <div class="admin-icon">🔧</div>
      <div class="admin-titles">
        <h2 class="admin-title"><?= e($pageTitle) ?></h2>
        <div class="admin-sub">System Administration</div>
      </div>
    </div>

    <div class="admin-user">
      <span class="user-name"><?= e($u['name']) ?> · System Administrator</span>
      <a class="btn-station" href="index.php">← Station</a>
      <a class="btn-logout" href="../logout.php">Logout</a>
    </div>
  </div>

  <nav class="admin-nav">
    <?php foreach ($ADMIN_MENU as $label => $url): ?>
      <a class="<?= $current === $url ? 'active' : '' ?>" href="<?= $url ?>">
        <?= e($label) ?>
      </a>
    <?php endforeach; ?>
    <a class="<?= $current === 'change_password.php' ? 'active' : '' ?>"
       href="../change_password.php">My Password</a>
  </nav>

</header>

<main>

<!-- Back to Station bar -->
<div class="back-bar">
  <div class="txt">
    🔧 <strong>System Administration</strong> — Technical Management Area
  </div>
  <a href="../index.php">← Back to Station Dashboard</a>
</div>

<?php if ($f = flash()): ?>
  <div class="flash"><?= e($f) ?></div>
<?php endif; ?>