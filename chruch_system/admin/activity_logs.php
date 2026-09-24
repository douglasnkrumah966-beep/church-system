<?php
/* ============================================================
   ACTIVITY LOGS
   ------------------------------------------------------------
   All POST/GET handling and redirects BEFORE _layout.php.
   ============================================================ */

require_once __DIR__ . '/../includes/auth.php';
requireSysadmin();

$u = currentUser();

/* ============================================================
   ENSURE TABLE EXISTS
   ============================================================ */
try {
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
   HANDLE ACTIONS
   ============================================================ */

/* ---------- Clear all logs ---------- */
if (isset($_GET['clear']) && $_GET['clear'] === 'all') {
    try {
        $pdo->exec("DELETE FROM activity_logs WHERE id > 0");
        flash('🗑 All activity logs cleared');
    } catch (PDOException $e) {
        flash('❌ Could not clear activity logs');
    }
    redirect('activity_logs.php');
}

/* ---------- Clear old logs (older than 30 days) ---------- */
if (isset($_GET['clear']) && $_GET['clear'] === 'old') {
    try {
        $stmt = $pdo->exec("DELETE FROM activity_logs WHERE created_at < NOW() - INTERVAL 30 DAY");
        flash('🗑 ' . (int)$stmt . ' old logs cleared (older than 30 days)');
    } catch (PDOException $e) {
        flash('❌ Could not clear old logs');
    }
    redirect('activity_logs.php');
}

/* ---------- Delete a single log ---------- */
if (isset($_GET['delete'])) {
    try {
        $pdo->prepare("DELETE FROM activity_logs WHERE id = ?")->execute([(int)$_GET['delete']]);
        flash('🗑 Log entry deleted');
    } catch (PDOException $e) {
        flash('❌ Could not delete log');
    }
    redirect('activity_logs.php');
}

/* ============================================================
   NOW include layout (HTML output starts here)
   ============================================================ */
require_once __DIR__ . '/_layout.php';

/* ============================================================
   FILTERS
   ============================================================ */
$filterUser    = get('user');
$filterAction  = get('action');
$filterDate    = get('date');   // today | week | month | all

$where  = "WHERE 1=1";
$params = [];

if ($filterUser !== '') {
    $where .= " AND username LIKE ?";
    $params[] = '%' . $filterUser . '%';
}
if ($filterAction !== '') {
    $where .= " AND action LIKE ?";
    $params[] = '%' . $filterAction . '%';
}
switch ($filterDate) {
    case 'today':
        $where .= " AND DATE(created_at) = CURDATE()";
        break;
    case 'week':
        $where .= " AND created_at >= NOW() - INTERVAL 7 DAY";
        break;
    case 'month':
        $where .= " AND created_at >= NOW() - INTERVAL 30 DAY";
        break;
}

/* ============================================================
   LOAD LOGS
   ============================================================ */
$logs = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM activity_logs $where
                           ORDER BY id DESC LIMIT 500");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $logs = is_array($rows) ? $rows : [];
} catch (PDOException $e) { $logs = []; }

/* Distinct actions for filter dropdown */
$actions = [];
try {
    $rows = $pdo->query("SELECT DISTINCT action FROM activity_logs
                         ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
    $actions = is_array($rows) ? $rows : [];
} catch (PDOException $e) {}

/* Stats */
$stats = ['today' => 0, 'week' => 0, 'month' => 0, 'total' => 0];
try {
    $stats['total'] = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
    $stats['today'] = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs
                                        WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $stats['week']  = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs
                                        WHERE created_at >= NOW() - INTERVAL 7 DAY")->fetchColumn();
    $stats['month'] = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs
                                        WHERE created_at >= NOW() - INTERVAL 30 DAY")->fetchColumn();
} catch (PDOException $e) {}
?>

<style>
.al-hero {
  background: linear-gradient(135deg,#1a365d 0%,#2c5282 60%,#4299e1 100%);
  color:#fff; border-radius:16px; padding:24px;
  margin-bottom:18px; box-shadow:0 8px 30px rgba(26,54,93,0.25);
}
.al-hero h1 { font-size:22px; margin:0 0 4px; font-weight:800; }
.al-hero p  { font-size:13px; opacity:0.9; margin:0; }

.al-tiles {
  display:grid; grid-template-columns:repeat(2,1fr); gap:10px;
  margin-bottom:18px;
}
@media (min-width:700px) { .al-tiles { grid-template-columns:repeat(4,1fr); } }
.al-tile {
  background:#fff; border-radius:12px; padding:16px;
  box-shadow:0 2px 8px rgba(0,0,0,0.05); border:1px solid #edf2f7;
  text-align:center; border-left:4px solid #3182ce;
}
.al-tile.today { border-left-color:#38a169; }
.al-tile.week  { border-left-color:#dd6b20; }
.al-tile.month { border-left-color:#805ad5; }
.al-tile .n { font-size:22px; font-weight:800; color:#2d3748; line-height:1.2; }
.al-tile .lbl {
  font-size:10px; color:#718096; text-transform:uppercase;
  font-weight:600; letter-spacing:0.4px; margin-top:4px;
}

.al-section {
  background:#fff; border-radius:14px; padding:20px; margin-bottom:16px;
  box-shadow:0 2px 8px rgba(0,0,0,0.05); border:1px solid #edf2f7;
}
.al-section h3 {
  font-size:15px; color:#1a365d; margin:0 0 12px;
  font-weight:700; display:flex; align-items:center;
  justify-content:space-between; gap:8px; flex-wrap:wrap;
}
.al-section .subtitle {
  font-size:12px; color:#a0aec0; margin:0 0 16px; line-height:1.5;
}

.al-filter {
  display:flex; gap:6px; flex-wrap:wrap; align-items:center;
  margin-bottom:14px;
}
.al-filter a {
  padding:8px 14px; font-size:13px; font-weight:600;
  text-decoration:none; border-radius:8px;
  background:#f7fafc; color:#4a5568; border:2px solid transparent;
}
.al-filter a:hover { background:#edf2f7; }
.al-filter a.active { background:#1a365d; color:#fff; border-color:#1a365d; }

.al-row {
  display:flex; align-items:flex-start; gap:12px;
  padding:12px 14px; background:#f7fafc; border-radius:10px;
  margin-bottom:6px; border:1px solid #edf2f7;
  font-size:13px; transition:all 0.15s ease;
}
.al-row:hover { background:#fff; border-color:#cbd5e0; }

.al-user {
  width:36px; height:36px; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  color:#fff; font-weight:700; font-size:14px; flex-shrink:0;
  background:linear-gradient(135deg,#1a365d,#2c5282);
}

.al-body { flex:1; min-width:0; }
.al-name {
  font-weight:700; color:#1a365d; font-size:13px;
}
.al-action {
  display:inline-block; padding:2px 10px; border-radius:999px;
  background:#bee3f8; color:#1a365d; font-weight:700;
  font-size:11px; margin-left:6px;
}
.al-details {
  font-size:12px; color:#4a5568; margin-top:4px; line-height:1.5;
  word-break:break-word;
}
.al-meta {
  font-size:11px; color:#a0aec0; margin-top:4px;
}

.al-actions { flex-shrink:0; }
</style>

<!-- ============================================================
     HERO
     ============================================================ -->
<div class="al-hero">
  <h1>📋 Activity Logs</h1>
  <p>Every action taken by every user — logins, edits, backups, password resets.</p>
</div>

<!-- ============================================================
     SUMMARY TILES
     ============================================================ -->
<div class="al-tiles">
  <div class="al-tile today">
    <div class="n"><?= $stats['today'] ?></div>
    <div class="lbl">Today</div>
  </div>
  <div class="al-tile week">
    <div class="n"><?= $stats['week'] ?></div>
    <div class="lbl">This Week</div>
  </div>
  <div class="al-tile month">
    <div class="n"><?= $stats['month'] ?></div>
    <div class="lbl">This Month</div>
  </div>
  <div class="al-tile">
    <div class="n"><?= $stats['total'] ?></div>
    <div class="lbl">All Time</div>
  </div>
</div>

<!-- ============================================================
     FILTERS
     ============================================================ -->
<div class="al-section">
  <h3>🔎 Filter Activity</h3>

  <!-- Date presets -->
  <div class="al-filter">
    <a class="<?= $filterDate === '' ? 'active' : '' ?>"
       href="activity_logs.php">All Time</a>
    <a class="<?= $filterDate === 'today' ? 'active' : '' ?>"
       href="?date=today">Today</a>
    <a class="<?= $filterDate === 'week' ? 'active' : '' ?>"
       href="?date=week">This Week</a>
    <a class="<?= $filterDate === 'month' ? 'active' : '' ?>"
       href="?date=month">This Month</a>

    <?php if ($stats['total'] > 0): ?>
      <a style="margin-left:auto;background:#fed7d7;color:#742a2a;"
         data-confirm="Delete logs older than 30 days?"
         href="?clear=old">🧹 Clean Old</a>
      <a style="background:#fed7d7;color:#742a2a;"
         data-confirm="Clear ALL activity logs? This cannot be undone."
         href="?clear=all">🗑 Clear All</a>
    <?php endif; ?>
  </div>

  <!-- Search form -->
  <form method="get" style="display:grid;gap:10px;
                            grid-template-columns:1fr 1fr;align-items:end;">
    <?php if ($filterDate): ?>
      <input type="hidden" name="date" value="<?= e($filterDate) ?>">
    <?php endif; ?>

    <div>
      <label>Username</label>
      <input name="user" value="<?= e($filterUser) ?>" placeholder="e.g. admin">
    </div>
    <div>
      <label>Action</label>
      <select name="action">
        <option value="">All actions</option>
        <?php foreach ($actions as $a): ?>
          <option <?= $filterAction === $a ? 'selected' : '' ?>><?= e($a) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="grid-column:1/-1;display:flex;gap:8px;justify-content:flex-end;">
      <button class="btn-sm btn-primary" style="padding:10px 20px;">
        🔍 Apply Filters
      </button>
      <?php if ($filterUser || $filterAction || $filterDate): ?>
        <a class="btn-sm btn-ghost" style="padding:10px 20px;text-decoration:none;"
           href="activity_logs.php">↺ Reset</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- ============================================================
     LOGS LIST
     ============================================================ -->
<div class="al-section">
  <h3>
    📋 Activity Entries
    <span class="badge" style="margin-left:6px;"><?= count($logs) ?></span>
  </h3>
  <p class="subtitle">
    Showing the most recent 500 entries. Logs are kept indefinitely unless cleared.
  </p>

  <?php if (!$logs): ?>
    <div class="empty">
      No activity found with these filters.
    </div>
  <?php else: foreach ($logs as $log):
    $initial = strtoupper(substr($log['username'] ?? '?', 0, 1));
    $username = $log['username'] ?? 'system';
    $action   = $log['action'] ?? '';
    $details  = $log['details'] ?? '';
    $ip       = $log['ip'] ?? '';
    $when     = $log['created_at'] ?? '';
  ?>
    <div class="al-row">
      <div class="al-user"><?= e($initial) ?></div>
      <div class="al-body">
        <div class="al-name">
          <?= e($username) ?>
          <span class="al-action"><?= e($action) ?></span>
        </div>
        <?php if ($details !== ''): ?>
          <div class="al-details"><?= e($details) ?></div>
        <?php endif; ?>
        <div class="al-meta">
          🕒 <?= $when ? 'date'('jS M Y, H:i:s', 'strtotime'($when)) : '—' ?>
          <?php if ($ip): ?>
            · 🌐 <?= e($ip) ?>
          <?php endif; ?>
        </div>
      </div>
      <div class="al-actions">
        <a class="btn-sm btn-danger" style="padding:6px 10px;font-size:12px;"
           data-confirm="Delete this log entry?"
           href="?delete=<?= (int)$log['id'] ?>"
           title="Delete">🗑</a>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<!-- ============================================================
     INFO NOTE
     ============================================================ -->
<div class="al-section" style="background:#ebf8ff;border-left:4px solid #3182ce;">
  <h3 style="color:#2c5282;">ℹ️ About Activity Logs</h3>
  <p style="font-size:13px;color:#2c5282;line-height:1.7;">
    Every action by every user is logged automatically — logins, logouts,
    user creation, password resets, backups, permission changes, and more.
    Use this to:
  </p>
  <ul style="font-size:13px;color:#2c5282;line-height:1.9;padding-left:20px;margin-top:6px;">
    <li><strong>Audit</strong> who did what, and when</li>
    <li><strong>Debug</strong> — see which admin action caused an issue</li>
    <li><strong>Security</strong> — detect unusual login patterns</li>
    <li><strong>Compliance</strong> — keep a record for church governance</li>
  </ul>
  <p style="font-size:12px;color:#2c5282;margin-top:10px;">
    <strong>Housekeeping:</strong> Click "🧹 Clean Old" to remove logs older than 30 days.
    This keeps the database small while preserving recent activity.
  </p>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>