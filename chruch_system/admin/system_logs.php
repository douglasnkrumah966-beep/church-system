<?php
/* ============================================================
   SYSTEM LOGS
   ------------------------------------------------------------
   All POST/GET handling and redirects MUST happen BEFORE
   including _layout.php (which prints HTML).
   ============================================================ */

require_once __DIR__ . '/../includes/auth.php';
requireSysadmin();

$u = currentUser();

/* ============================================================
   ENSURE system_logs TABLE EXISTS
   ============================================================ */
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        level ENUM('info','warning','error') DEFAULT 'info',
        message VARCHAR(500) NOT NULL,
        context VARCHAR(255) DEFAULT NULL,
        user_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {}

/* ============================================================
   HANDLE ACTIONS
   ============================================================ */

/* ---------- Clear all logs ---------- */
if (isset($_GET['clear']) && $_GET['clear'] === 'all') {
    try {
        $pdo->exec("DELETE FROM system_logs WHERE id > 0");
        flash('🗑 All system logs cleared');
    } catch (PDOException $e) {
        flash('❌ Could not clear logs');
    }
    redirect('system_logs.php');
}

/* ---------- Clear only errors ---------- */
if (isset($_GET['clear']) && $_GET['clear'] === 'errors') {
    try {
        $pdo->exec("DELETE FROM system_logs WHERE level = 'error'");
        flash('🗑 All error logs cleared');
    } catch (PDOException $e) {
        flash('❌ Could not clear error logs');
    }
    redirect('system_logs.php');
}

/* ---------- Delete a single log ---------- */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM system_logs WHERE id = ?")->execute([$id]);
        flash('🗑 Log entry deleted');
    } catch (PDOException $e) {
        flash('❌ Could not delete log entry');
    }
    redirect('system_logs.php');
}

/* ---------- Add a test log (dev) ---------- */
if (isset($_GET['test'])) {
    $level = in_array($_GET['test'], ['info','warning','error'], true) ? $_GET['test'] : 'info';
    try {
        $pdo->prepare("INSERT INTO system_logs (level, message, context, user_id)
                       VALUES (?,?,?,?)")
            ->execute([
                $level,
                'Test ' . $level . ' log entry',
                'system_logs.php',
                currentUser()['id'] ?? null
            ]);
        flash('✅ Test ' . $level . ' log created');
    } catch (PDOException $e) {
        flash('❌ Could not create test log');
    }
    redirect('system_logs.php');
}

/* ============================================================
   NOW include layout (HTML output starts here)
   ============================================================ */
require_once __DIR__ . '/_layout.php';

/* ============================================================
   FILTERS
   ============================================================ */
$level   = get('level');
$search  = get('q');

$where  = "WHERE 1=1";
$params = [];

if (in_array($level, ['info','warning','error'], true)) {
    $where .= " AND level = ?";
    $params[] = $level;
}

if ($search !== '') {
    $where .= " AND (message LIKE ? OR context LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

/* ============================================================
   LOAD LOGS
   ============================================================ */
$logs = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM system_logs $where
                           ORDER BY id DESC LIMIT 500");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $logs = is_array($rows) ? $rows : [];
} catch (PDOException $e) {
    $logs = [];
}

/* ============================================================
   COUNTS BY LEVEL
   ============================================================ */
$counts = ['info' => 0, 'warning' => 0, 'error' => 0];
try {
    foreach ($pdo->query("SELECT level, COUNT(*) c FROM system_logs GROUP BY level") as $r) {
        if (isset($counts[$r['level']])) $counts[$r['level']] = (int)$r['c'];
    }
} catch (PDOException $e) {}
$totalLogs = array_sum($counts);
?>

<style>
.sl-hero {
  background: linear-gradient(135deg,#1a365d 0%,#2c5282 60%,#4299e1 100%);
  color:#fff; border-radius:16px; padding:24px;
  margin-bottom:18px; box-shadow:0 8px 30px rgba(26,54,93,0.25);
}
.sl-hero h1 { font-size:22px; margin:0 0 4px; font-weight:800; }
.sl-hero p  { font-size:13px; opacity:0.9; margin:0; }

.sl-tiles {
  display: grid; grid-template-columns: repeat(2, 1fr); gap:10px;
  margin-bottom:18px;
}
@media (min-width:700px) { .sl-tiles { grid-template-columns: repeat(4, 1fr); } }
.sl-tile {
  background:#fff; border-radius:12px; padding:16px;
  box-shadow:0 2px 8px rgba(0,0,0,0.05); border:1px solid #edf2f7;
  text-align:center; border-left:4px solid #cbd5e0;
}
.sl-tile.info    { border-left-color:#3182ce; }
.sl-tile.warning { border-left-color:#dd6b20; }
.sl-tile.error   { border-left-color:#e53e3e; }
.sl-tile.total   { border-left-color:#1a365d; }
.sl-tile .n {
  font-size:24px; font-weight:800; color:#2d3748; line-height:1.2;
}
.sl-tile .lbl {
  font-size:10px; color:#718096; text-transform:uppercase;
  font-weight:600; letter-spacing:0.4px; margin-top:4px;
}

.sl-section {
  background:#fff; border-radius:14px; padding:20px; margin-bottom:16px;
  box-shadow:0 2px 8px rgba(0,0,0,0.05); border:1px solid #edf2f7;
}
.sl-section h3 {
  font-size:15px; color:#1a365d; margin:0 0 12px;
  font-weight:700; display:flex; align-items:center;
  justify-content:space-between; gap:8px; flex-wrap:wrap;
}
.sl-section .subtitle {
  font-size:12px; color:#a0aec0; margin:0 0 16px; line-height:1.5;
}

.sl-filter {
  display:flex; gap:6px; flex-wrap:wrap; align-items:center;
}
.sl-filter a {
  padding:8px 14px; font-size:13px; font-weight:600;
  text-decoration:none; border-radius:8px;
  background:#f7fafc; color:#4a5568; border:2px solid transparent;
}
.sl-filter a:hover { background:#edf2f7; }
.sl-filter a.active { background:#1a365d; color:#fff; border-color:#1a365d; }
.sl-filter a.info.active    { background:#3182ce; border-color:#3182ce; }
.sl-filter a.warning.active { background:#dd6b20; border-color:#dd6b20; }
.sl-filter a.error.active   { background:#e53e3e; border-color:#e53e3e; }

.sl-row {
  display:flex; gap:12px; align-items:flex-start;
  padding:14px; border-radius:12px;
  margin-bottom:8px; border:1px solid #edf2f7;
  background:#f7fafc;
}
.sl-row.info    { background:#ebf8ff; border-color:#bee3f8; }
.sl-row.warning { background:#fffaf0; border-color:#feebc8; }
.sl-row.error   { background:#fff5f5; border-color:#fed7d7; }

.sl-badge {
  flex-shrink:0; padding:3px 10px; border-radius:999px;
  font-size:11px; font-weight:700; text-transform:uppercase;
  letter-spacing:0.4px;
}
.sl-badge.info    { background:#3182ce; color:#fff; }
.sl-badge.warning { background:#dd6b20; color:#fff; }
.sl-badge.error   { background:#e53e3e; color:#fff; }

.sl-body { flex:1; min-width:0; }
.sl-msg {
  font-size:14px; font-weight:600; color:#2d3748;
  word-break:break-word;
}
.sl-meta {
  font-size:11px; color:#718096; margin-top:4px;
}
.sl-actions { flex-shrink:0; }

.sl-note {
  background:#ebf8ff; border-left:4px solid #3182ce;
  padding:14px 16px; border-radius:12px;
  font-size:13px; color:#2c5282; line-height:1.6;
}
</style>

<!-- ============================================================
     HERO
     ============================================================ -->
<div class="sl-hero">
  <h1>📋 System Logs</h1>
  <p>Technical errors, warnings, and informational events recorded by the system.</p>
</div>

<!-- ============================================================
     SUMMARY TILES
     ============================================================ -->
<div class="sl-tiles">
  <div class="sl-tile info">
    <div class="n"><?= $counts['info'] ?></div>
    <div class="lbl">Info</div>
  </div>
  <div class="sl-tile warning">
    <div class="n"><?= $counts['warning'] ?></div>
    <div class="lbl">Warnings</div>
  </div>
  <div class="sl-tile error">
    <div class="n"><?= $counts['error'] ?></div>
    <div class="lbl">Errors</div>
  </div>
  <div class="sl-tile total">
    <div class="n"><?= $totalLogs ?></div>
    <div class="lbl">Total</div>
  </div>
</div>

<!-- ============================================================
     FILTERS
     ============================================================ -->
<div class="sl-section">
  <h3>🔎 Filter Logs</h3>

  <div class="sl-filter" style="margin-bottom:12px;">
    <a class="<?= $level === '' ? 'active' : '' ?>"
       href="system_logs.php">All</a>
    <a class="info <?= $level === 'info' ? 'active' : '' ?>"
       href="?level=info">Info (<?= $counts['info'] ?>)</a>
    <a class="warning <?= $level === 'warning' ? 'active' : '' ?>"
       href="?level=warning">Warnings (<?= $counts['warning'] ?>)</a>
    <a class="error <?= $level === 'error' ? 'active' : '' ?>"
       href="?level=error">Errors (<?= $counts['error'] ?>)</a>

    <?php if ($counts['error'] > 0): ?>
      <a style="margin-left:auto;background:#fed7d7;color:#742a2a;"
         data-confirm="Delete ALL error logs?"
         href="?clear=errors">🗑 Clear Errors</a>
    <?php endif; ?>

    <?php if ($totalLogs > 0): ?>
      <a style="background:#fed7d7;color:#742a2a;"
         data-confirm="Clear ALL system logs?"
         href="?clear=all">🗑 Clear All</a>
    <?php endif; ?>
  </div>

  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;">
    <?php if ($level): ?>
      <input type="hidden" name="level" value="<?= e($level) ?>">
    <?php endif; ?>
    <input name="q" value="<?= e($search) ?>"
           placeholder="🔍 Search message or context..."
           style="flex:1;min-width:200px;">
    <button class="btn-sm btn-primary" style="padding:10px 18px;">Search</button>
    <?php if ($search): ?>
      <a class="btn-sm btn-ghost" style="padding:10px 18px;text-decoration:none;"
         href="system_logs.php<?= $level ? '?level='.urlencode($level) : '' ?>">Reset</a>
    <?php endif; ?>
  </form>
</div>

<!-- ============================================================
     LOGS LIST
     ============================================================ -->
<div class="sl-section">
  <h3>
    📋 Log Entries
    <span class="badge" style="margin-left:6px;"><?= count($logs) ?></span>
  </h3>

  <?php if (!$logs): ?>
    <div class="empty">
      No log entries found<?= $level ? ' for "' . e($level) . '"' : '' ?>.
      <div style="margin-top:8px;font-size:12px;color:#a0aec0;">
        Tip: Use
        <a href="?test=info" style="color:#3182ce;">?test=info</a>,
        <a href="?test=warning" style="color:#dd6b20;">?test=warning</a>, or
        <a href="?test=error" style="color:#e53e3e;">?test=error</a>
        to create a sample log.
      </div>
    </div>
  <?php else: foreach ($logs as $log):
    $lvl = $log['level'] ?? 'info';
  ?>
    <div class="sl-row <?= e($lvl) ?>">
      <span class="sl-badge <?= e($lvl) ?>"><?= e($lvl) ?></span>

      <div class="sl-body">
        <div class="sl-msg"><?= e($log['message'] ?? '') ?></div>
        <div class="sl-meta">
          🕒 <?= date('jS M Y, H:i:s', strtotime($log['created_at'])) ?>
          <?php if (!empty($log['context'])): ?>
            · 📍 <?= e($log['context']) ?>
          <?php endif; ?>
          <?php if (!empty($log['user_id'])): ?>
            · 👤 User #<?= (int)$log['user_id'] ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="sl-actions">
        <a class="btn-sm btn-danger" style="padding:6px 10px;font-size:12px;"
           data-confirm="Delete this log entry?"
           href="?delete=<?= (int)$log['id'] ?><?= $level ? '&level='.urlencode($level) : '' ?>"
           title="Delete">🗑</a>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<!-- ============================================================
     INFO NOTE
     ============================================================ -->
<div class="sl-note">
  <strong>ℹ️ What are system logs?</strong><br>
  These are technical events recorded by the system — errors, warnings,
  and informational messages. They help you diagnose problems.
  <br><br>
  <strong>Regular housekeeping:</strong> clear old logs every few months
  to keep the database small. Logs are stored in the <code>system_logs</code>
  table and can be safely deleted once reviewed.
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>