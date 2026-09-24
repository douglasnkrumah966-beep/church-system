<?php
require_once __DIR__ . '/_layout.php';

/* ============================================================
   SYSTEM ADMINISTRATOR DASHBOARD
   ------------------------------------------------------------
   Technical / system management only.
   No church operations, no finances shown here.
   ============================================================ */

/* ---------- Auto-create log tables if missing ---------- */
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        level ENUM('info','warning','error') DEFAULT 'info',
        message VARCHAR(500) NOT NULL,
        context VARCHAR(255) DEFAULT NULL,
        user_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        username VARCHAR(50) DEFAULT NULL,
        action VARCHAR(80) NOT NULL,
        details VARCHAR(500) DEFAULT NULL,
        ip VARCHAR(45) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {}

/* ============================================================
   SAFE QUERY HELPERS
   ============================================================ */
function safeCount(PDO $pdo, string $sql, int $default = 0): int {
    try {
        $v = $pdo->query($sql)->fetchColumn();
        return $v === false ? $default : (int)$v;
    } catch (PDOException $e) { return $default; }
}
function safeAll(PDO $pdo, string $sql, int $limit = 10): array {
    try {
        $rows = $pdo->query($sql)->fetchAll();
        return is_array($rows) ? $rows : [];
    } catch (PDOException $e) { return []; }
}
function safeVal(PDO $pdo, string $sql, $default = null) {
    try {
        $v = $pdo->query($sql)->fetchColumn();
        return $v === false ? $default : $v;
    } catch (PDOException $e) { return $default; }
}

/* ============================================================
   STATS
   ============================================================ */
$totalUsers     = safeCount($pdo, "SELECT COUNT(*) FROM users");
$totalSysadmins = safeCount($pdo, "SELECT COUNT(*) FROM users WHERE role='sysadmin'");
$totalMembers   = safeCount($pdo, "SELECT COUNT(*) FROM members");
$totalTables    = safeCount($pdo, "SELECT COUNT(*) FROM information_schema.tables
                                   WHERE table_schema = DATABASE()");
$recentErrors   = safeCount($pdo, "SELECT COUNT(*) FROM system_logs WHERE level='error'");
$actions24h     = safeCount($pdo, "SELECT COUNT(*) FROM activity_logs
                                   WHERE created_at > NOW() - INTERVAL 1 DAY");

$dbSize = safeVal($pdo, "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2)
                         FROM information_schema.tables
                         WHERE table_schema = DATABASE()", 0);

/* ============================================================
   SYSTEM INFO
   ============================================================ */
$phpVersion  = PHP_VERSION;
$serverSoft  = $_SERVER['SERVER_SOFTWARE'] ?? '—';
$uploadMax   = ini_get('upload_max_filesize');
$postMax     = ini_get('post_max_size');
$memoryLimit = ini_get('memory_limit');
$timezone    = date_default_timezone_get();
$dbName      = safeVal($pdo, "SELECT DATABASE()", '—');

/* ---------- Disk ---------- */
$diskFree  = @disk_free_space('.');
$diskTotal = @disk_total_space('.');
$diskUsed  = ($diskTotal !== false && $diskFree !== false) ? ($diskTotal - $diskFree) : null;
$diskPct   = ($diskTotal && $diskTotal > 0 && $diskUsed !== null)
             ? round($diskUsed / $diskTotal * 100, 1) : null;

/* ---------- Backups ---------- */
$backupDir    = __DIR__ . '/../backups/';
$backupCount  = 0;
$backupLatest = null;
if (is_dir($backupDir)) {
    $files = glob($backupDir . '*.sql');
    if (is_array($files)) {
        $backupCount = count($files);
        if ($backupCount > 0) {
            usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
            $backupLatest = ['name' => basename($files[0]), 'time' => filemtime($files[0])];
        }
    }
}

/* ---------- Recent system activity ---------- */
$recentActivity = safeAll($pdo, "
    SELECT * FROM activity_logs
    WHERE action IN ('login','logout','create_user','reset_password',
                     'view_admin','backup','restore','security_update',
                     'change_role','delete_user')
    ORDER BY id DESC LIMIT 8
");

$recentErrorsList = safeAll($pdo, "
    SELECT * FROM system_logs
    WHERE level='error' ORDER BY id DESC LIMIT 5
");

/* ---------- Table health ---------- */
$expectedTables = ['users','members','sacraments','finances','settings',
                   'roles','permissions','system_logs','activity_logs'];
$existingTables = [];
try {
    $existingTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $existingTables = is_array($existingTables) ? $existingTables : [];
} catch (PDOException $e) {}
$missingTables = array_diff($expectedTables, $existingTables);

/* ---------- Backup reminder ---------- */
$backupDays = $backupLatest ? floor((time() - $backupLatest['time']) / 86400) : null;
$backupOverdue = (!$backupLatest) || ($backupDays !== null && $backupDays >= 7);
?>

<style>
.back-bar {
  background: #fff; border-radius: 12px; padding: 12px 16px;
  margin-bottom: 14px; box-shadow: 0 2px 6px rgba(0,0,0,0.05);
  border: 1px solid #edf2f7;
  display: flex; justify-content: space-between; align-items: center;
  gap: 10px; flex-wrap: wrap;
}
.back-bar .txt { font-size: 12px; color: #718096; }
.back-bar .txt strong { color: #1a365d; }
.back-bar a {
  padding: 10px 16px; font-size: 13px; text-decoration: none;
  border-radius: 8px; font-weight: 600; white-space: nowrap;
  background: #1a365d; color: #fff;
}

.ad-tiles {
  display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;
  margin-bottom: 16px;
}
@media (min-width: 700px) { .ad-tiles { grid-template-columns: repeat(4, 1fr); } }
.ad-tile {
  background: #fff; border-radius: 14px; padding: 16px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #edf2f7;
  display: flex; flex-direction: column; gap: 4px;
  text-decoration: none; transition: all 0.15s ease;
  border-left: 4px solid #1a365d;
}
.ad-tile:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.08); }
.ad-tile .ic { font-size: 22px; }
.ad-tile .n {
  font-size: 24px; font-weight: 800; color: #2d3748; line-height: 1.1;
}
.ad-tile .lbl {
  font-size: 11px; color: #718096; text-transform: uppercase;
  letter-spacing: 0.4px; font-weight: 600;
}
.ad-tile.blue  { border-left-color: #3182ce; }
.ad-tile.green { border-left-color: #38a169; }
.ad-tile.purple{ border-left-color: #805ad5; }
.ad-tile.red   { border-left-color: #e53e3e; }
.ad-tile.orange{ border-left-color: #dd6b20; }
.ad-tile.teal  { border-left-color: #319795; }
.ad-tile.pink  { border-left-color: #d53f8c; }
.ad-tile.dark  { border-left-color: #1a365d; }

.ad-section {
  background: #fff; border-radius: 14px; padding: 20px; margin-bottom: 16px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #edf2f7;
}
.ad-section h3 {
  font-size: 15px; color: #1a365d; margin: 0 0 12px;
  font-weight: 700; display: flex; align-items: center;
  justify-content: space-between; gap: 8px;
}
.ad-section .view-all {
  font-size: 12px; font-weight: 600; color: #3182ce; text-decoration: none;
}

.health-grid {
  display: grid; grid-template-columns: 1fr; gap: 8px;
}
@media (min-width: 700px) { .health-grid { grid-template-columns: repeat(2, 1fr); } }
.health-item {
  display: flex; justify-content: space-between; align-items: center;
  padding: 10px 14px; border-radius: 10px; background: #f7fafc;
  font-size: 13px; border-left: 4px solid #cbd5e0;
}
.health-item.ok   { border-left-color: #38a169; background: #f0fff4; }
.health-item.warn { border-left-color: #dd6b20; background: #fffaf0; }
.health-item.bad  { border-left-color: #e53e3e; background: #fff5f5; }
.health-item .lbl { color: #4a5568; font-weight: 600; }
.health-item .val { color: #2d3748; font-weight: 700; text-align: right; font-size: 12px; }

.qa-grid {
  display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;
}
@media (min-width: 700px) { .qa-grid { grid-template-columns: repeat(3, 1fr); } }
.qa-btn {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; gap: 6px; padding: 16px 10px;
  background: #f7fafc; border-radius: 12px; text-decoration: none;
  color: #2d3748; font-weight: 600; font-size: 12px; text-align: center;
  border: 2px solid transparent; transition: all 0.15s ease;
}
.qa-btn:hover { background: #fff; border-color: #3182ce; transform: translateY(-2px); }
.qa-btn .ic { font-size: 24px; }

.act-row {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px; background: #f7fafc; border-radius: 10px;
  margin-bottom: 6px; font-size: 13px;
}
.act-row .time { font-size: 11px; color: #718096; min-width: 60px; }
.act-row .who  { font-weight: 600; color: #1a365d; min-width: 90px; }
.act-row .what { flex: 1; color: #4a5568; }
.act-row .badge {
  font-size: 10px; padding: 2px 8px; border-radius: 999px;
  background: #bee3f8; color: #2c5282; font-weight: 700;
}

.err-row {
  background: #fff5f5; padding: 10px 12px; border-radius: 10px;
  margin-bottom: 6px; font-size: 13px; border-left: 3px solid #e53e3e;
}
.err-row .msg { font-weight: 700; color: #742a2a; }
.err-row .meta { font-size: 11px; color: #a0aec0; margin-top: 2px; }

.reminder {
  background: linear-gradient(135deg, #fffaf0, #feebc8);
  border-left: 4px solid #dd6b20;
  border-radius: 14px; padding: 18px; margin-bottom: 16px;
  display: flex; align-items: center; gap: 16px;
}
.reminder .icon { font-size: 36px; flex-shrink: 0; }
.reminder .body { flex: 1; }
.reminder .title {
  font-size: 15px; font-weight: 700; color: #7b341e; margin-bottom: 4px;
}
.reminder .desc { font-size: 12px; color: #7b341e; line-height: 1.5; }
.reminder a {
  padding: 12px 18px; background: #dd6b20; color: #fff;
  border-radius: 10px; text-decoration: none; font-weight: 700;
  font-size: 13px; white-space: nowrap;
  box-shadow: 0 4px 12px rgba(221,107,32,0.3);
}
@media (max-width: 600px) {
  .reminder { flex-direction: column; text-align: center; }
  .reminder a { width: 100%; text-align: center; }
}

.scope-note {
  background: #ebf8ff; border-left: 4px solid #3182ce;
  padding: 14px 16px; border-radius: 12px; margin-bottom: 16px;
  font-size: 13px; color: #2c5282; line-height: 1.6;
}
.scope-note strong { color: #1a365d; }
</style>

<!-- ============================================================
     BACK TO STATION BAR
     ============================================================ -->
<div class="back-bar">
  <div class="txt">
    🔧 You are in the <strong>System Administration</strong> area
  </div>
  <a href="../index.php">← Back to Station Dashboard</a>
</div>

<!-- ============================================================
     BACKUP REMINDER (only if overdue)
     ============================================================ -->
<?php if ($backupOverdue): ?>
<div class="reminder">
  <div class="icon">⚠️</div>
  <div class="body">
    <div class="title">
      <?= $backupLatest
          ? "It's been {$backupDays} days since your last backup"
          : 'No backups yet' ?>
    </div>
    <div class="desc">
      <?= $backupLatest
          ? 'Backups should be made at least once a week to protect the station\'s records.'
          : 'Create your first backup to protect the station\'s records.' ?>
    </div>
  </div>
  <a href="backups.php?create=1"
     onclick="return confirm('Create a backup now?')">💾 Create Backup</a>
</div>
<?php endif; ?>

<!-- ============================================================
     STAT TILES
     ============================================================ -->
<div class="ad-tiles">
  <div class="ad-tile blue">
    <div class="ic">👥</div>
    <div class="n"><?= $totalUsers ?></div>
    <div class="lbl">User Accounts</div>
  </div>
  <div class="ad-tile dark">
    <div class="ic">🔧</div>
    <div class="n"><?= $totalSysadmins ?></div>
    <div class="lbl">System Admins</div>
  </div>
  <div class="ad-tile green">
    <div class="ic">🗄</div>
    <div class="n"><?= $totalTables ?></div>
    <div class="lbl">Database Tables</div>
  </div>
  <div class="ad-tile <?= $recentErrors > 0 ? 'red' : 'green' ?>">
    <div class="ic"><?= $recentErrors > 0 ? '⚠️' : '✅' ?></div>
    <div class="n"><?= $recentErrors ?></div>
    <div class="lbl">System Errors</div>
  </div>
</div>

<div class="ad-tiles">
  <div class="ad-tile orange">
    <div class="ic">📊</div>
    <div class="n"><?= $dbSize ?> MB</div>
    <div class="lbl">Database Size</div>
  </div>
  <div class="ad-tile purple">
    <div class="ic">💾</div>
    <div class="n"><?= $backupCount ?></div>
    <div class="lbl">Backup Files</div>
  </div>
  <div class="ad-tile teal">
    <div class="ic">⚡</div>
    <div class="n"><?= $actions24h ?></div>
    <div class="lbl">Actions (24h)</div>
  </div>
  <div class="ad-tile pink">
    <div class="ic">🙏</div>
    <div class="n"><?= $totalMembers ?></div>
    <div class="lbl">Member Records</div>
  </div>
</div>

<!-- ============================================================
     QUICK ACTIONS
     ============================================================ -->
<div class="ad-section">
  <h3>⚡ Quick Actions</h3>
  <div class="qa-grid">
    <a class="qa-btn" href="users.php">
      <span class="ic">👥</span>
      Manage Users
    </a>
    <a class="qa-btn" href="backups.php?create=1"
       onclick="return confirm('Create a backup now?')">
      <span class="ic">💾</span>
      Create Backup
    </a>
    <a class="qa-btn" href="security.php">
      <span class="ic">🔒</span>
      Security
    </a>
    <a class="qa-btn" href="settings.php">
      <span class="ic">⚙️</span>
      Settings
    </a>
    <a class="qa-btn" href="database.php">
      <span class="ic">🗄</span>
      Browse Database
    </a>
    <a class="qa-btn" href="activity_logs.php">
      <span class="ic">📋</span>
      Activity Logs
    </a>
  </div>
</div>

<!-- ============================================================
     SYSTEM HEALTH
     ============================================================ -->
<div class="ad-section">
  <h3>🩺 System Health</h3>
  <div class="health-grid">
    <div class="health-item <?= version_compare(PHP_VERSION, '7.4.0', '>=') ? 'ok' : 'warn' ?>">
      <span class="lbl">PHP Version</span>
      <span class="val"><?= e($phpVersion) ?></span>
    </div>
    <div class="health-item <?= $dbName && $dbName !== '—' ? 'ok' : 'bad' ?>">
      <span class="lbl">Database</span>
      <span class="val"><?= e($dbName ?: 'not connected') ?></span>
    </div>
    <div class="health-item ok">
      <span class="lbl">Server</span>
      <span class="val"><?= e($serverSoft) ?></span>
    </div>
    <div class="health-item ok">
      <span class="lbl">Timezone</span>
      <span class="val"><?= e($timezone) ?></span>
    </div>
    <div class="health-item ok">
      <span class="lbl">Upload Max</span>
      <span class="val"><?= e($uploadMax) ?></span>
    </div>
    <div class="health-item ok">
      <span class="lbl">Memory Limit</span>
      <span class="val"><?= e($memoryLimit) ?></span>
    </div>
    <?php if ($diskTotal): ?>
      <div class="health-item <?= $diskPct !== null && $diskPct > 90 ? 'bad' : ($diskPct !== null && $diskPct > 75 ? 'warn' : 'ok') ?>">
        <span class="lbl">Disk</span>
        <span class="val">
          <?= number_format($diskFree / 1024 / 1024 / 1024, 1) ?> GB free
          <?= $diskPct !== null ? '· ' . $diskPct . '% used' : '' ?>
        </span>
      </div>
    <?php endif; ?>
    <div class="health-item <?= empty($missingTables) ? 'ok' : 'warn' ?>">
      <span class="lbl">Database Tables</span>
      <span class="val">
        <?= count($existingTables) ?> present
        <?= empty($missingTables) ? '✓' : '· ' . count($missingTables) . ' missing' ?>
      </span>
    </div>
    <div class="health-item <?= $backupLatest ? 'ok' : 'warn' ?>">
      <span class="lbl">Last Backup</span>
      <span class="val">
        <?= $backupLatest ? date('d/m/y H:i', $backupLatest['time']) : 'never' ?>
      </span>
    </div>
  </div>

  <?php if (!empty($missingTables)): ?>
    <div style="margin-top:12px;background:#fffaf0;border-left:4px solid #dd6b20;
                padding:10px 14px;border-radius:8px;font-size:13px;">
      <strong>⚠️ Missing tables:</strong>
      <?= e(implode(', ', $missingTables)) ?>
    </div>
  <?php endif; ?>
</div>

<!-- ============================================================
     RECENT SYSTEM ACTIVITY
     ============================================================ -->
<div class="ad-section">
  <h3>
    🕒 Recent System Activity
    <a class="view-all" href="activity_logs.php">View All →</a>
  </h3>

  <?php if (!$recentActivity): ?>
    <div class="empty">No system activity logged yet.</div>
  <?php else: foreach ($recentActivity as $a): ?>
    <div class="act-row">
      <div class="time"><?= date('d/m H:i', strtotime($a['created_at'])) ?></div>
      <div class="who"><?= e($a['username'] ?? '—') ?></div>
      <div class="what"><?= e($a['details'] ?? '') ?></div>
      <span class="badge"><?= e($a['action']) ?></span>
    </div>
  <?php endforeach; endif; ?>
</div>

<!-- ============================================================
     RECENT ERRORS
     ============================================================ -->
<?php if ($recentErrorsList): ?>
<div class="ad-section" style="border-left:4px solid #e53e3e;">
  <h3>
    ⚠️ Recent System Errors
    <a class="view-all" href="system_logs.php?level=error">View All →</a>
  </h3>
  <?php foreach ($recentErrorsList as $err): ?>
    <div class="err-row">
      <div class="msg"><?= e($err['message']) ?></div>
      <div class="meta">
        <?= date('jS M Y H:i', strtotime($err['created_at'])) ?>
        <?= $err['context'] ? ' · ' . e($err['context']) : '' ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ============================================================
     SCOPE NOTE
     ============================================================ -->
<div class="scope-note">
  <strong>ℹ️ Your Scope as System Administrator</strong><br>
  This dashboard is for <strong>technical and system management only</strong>.
  Station operations — members, sacraments, finances, day-born, attendance —
  are handled by the <strong>Station Administrator</strong>, <strong>Station Secretary</strong>,
  <strong>Finance Officer</strong>, and <strong>Catechist</strong>.
</div>

<!-- ============================================================
     BOTTOM BACK BUTTON
     ============================================================ -->
<div class="back-bar" style="margin-bottom:0;">
  <div class="txt">
    Done here? Head back to the station.
  </div>
  <a href="index.php">← Back to Station Dashboard</a>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>