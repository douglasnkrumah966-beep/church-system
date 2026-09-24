<?php
require_once __DIR__ . '/auth.php';
requireLogin();

/* ============================================================
   Safety net — ensure $settings exists
   ============================================================ */
if (!isset($settings) || !is_array($settings)) {
    global $pdo;
    try {
        $settings = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch();
    } catch (PDOException $e) {
        $settings = null;
    }
    if (!$settings) {
        $settings = ['church_name' => 'Roman Catholic Church', 'currency' => 'GH₵'];
    }
}

$u = currentUser();

/* ============================================================
   MENUS PER ROLE
   ============================================================ */
$MENUS = [
  /* ---------- SYSTEM ADMINISTRATOR — technical only ---------- */
  'sysadmin' => [
    'Dashboard'      => 'admin/index.php',
    'Users'          => 'admin/users.php',
    'Roles'          => 'admin/roles.php',
    'Permissions'    => 'admin/permissions.php',
    'Security'       => 'admin/security.php',
    'Database'       => 'admin/database.php',
    'Backups'        => 'admin/backups.php',
    'System Logs'    => 'admin/system_logs.php',
    'Activity Logs'  => 'admin/activity_logs.php',
    'Settings'       => 'admin/settings.php',
    'Maintenance'    => 'admin/maintenance.php',
    'My Password'    => 'change_password.php',
  ],

  /* ---------- STATION ADMINISTRATOR (Priest) ---------- */
  'admin' => [
    'Dashboard'       => 'index.php',
    'Members'         => 'members.php',
    'Sacraments'      => 'sacraments.php',
    'Day Born'        => 'dayborn.php',
    'Attendance'      => 'attendance.php',
    'Finance'         => 'finance.php',
    'Cashbook'        => 'cashbook.php',
    'Funeral Report'  => 'funeral_report.php',
    'Reports'         => 'reports.php',
    'WhatsApp Groups' => 'wa_groups.php',
    'Bulk Message'    => 'bulk_message.php',
    'My Password'     => 'change_password.php',
  ],

  /* ---------- STATION SECRETARY ---------- */
  'secretary' => [
    'Dashboard'         => 'index.php',
    'Members'           => 'members.php',
    'Sacraments'        => 'sacraments.php',
    'Day Born'          => 'dayborn.php',
    'Attendance'        => 'attendance.php',
    'Attendance Report' => 'attendance_report.php',
    'WhatsApp Groups'   => 'wa_groups.php',
    'Bulk Message'      => 'bulk_message.php',
    'My Password'       => 'change_password.php',
  ],

  /* ---------- FINANCE OFFICER ---------- */
 'finance' => [
    'Dashboard'           => 'finance/index.php',
    'First Collection'    => 'finance/first_collection.php',
    'Day Born Collection' => 'finance/dayborn_collection.php',
    'Contributions'       => 'finance/contributions.php',
    'Expenses'            => 'finance/expenses.php',
    'Cashbook'            => 'finance/cashbook.php',
    'Funeral Report'      => 'finance/funeral_report.php',
    'Reports'             => 'finance/reports.php',
    'Financial Settings'  => 'finance/financial_settings.php',
    'WhatsApp Groups'     => 'wa_groups.php',
    'Bulk Message'        => 'bulk_message.php',
    'My Password'         => 'change_password.php',
],

  /* ---------- CATECHIST ---------- */
  'catechist' => [
    'Dashboard'         => 'catechist/index.php',
    'Sacraments'        => 'catechist/sacraments.php',
    'Members'           => 'catechist/members.php',
    'Attendance'        => 'catechist/attendance.php',
    'Attendance Report' => 'catechist/attendance_report.php',
    'WhatsApp Groups'   => 'catechist/wa_groups.php',
    'My Password'       => 'change_password.php',
],

  /* ---------- MEMBER ---------- */
  'member' => [
    'My Dashboard'  => 'member/index.php',
    'My Profile'    => 'member/my_profile.php',
    'My Sacraments' => 'member/my_sacraments.php',
    'My Giving'     => 'member/my_giving.php',
    'Day Born'      => 'member/my_dayborn.php',
    'My Password'   => 'change_password.php',
],
];

$menu = $MENUS[$u['role']] ?? ['Dashboard' => 'index.php'];
$current = basename($_SERVER['PHP_SELF']);
$pageTitle = array_search($current, $menu) ?: 'Dashboard';

/* ============================================================
   Friendly role label for the user tag
   ============================================================ */
$roleLabel = match($u['role']) {
    'sysadmin'  => 'System Administrator',
    'admin'     => 'Station Administrator',
    'secretary' => 'Station Secretary',
    'finance'   => 'Finance Officer',
    'catechist' => 'Catechist',
    'member'    => 'Member',
    default     => ucfirst((string)$u['role']),
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> · <?= e($settings['church_name']) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header>
  <div>
    <h2><?= e($pageTitle) ?></h2>
    <div class="user-tag"><?= e($u['name']) ?> · <?= e($roleLabel) ?></div>
  </div>
  <a class="logout-btn" href="logout.php">Logout</a>
</header>
<nav class="tabs">
  <?php foreach ($menu as $label => $url): ?>
    <a class="<?= $current === $url ? 'active' : '' ?>" href="<?= $url ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>
<main>
<?php if ($f = flash()): ?>
  <div class="flash"><?= e($f) ?></div>
<?php endif; ?>