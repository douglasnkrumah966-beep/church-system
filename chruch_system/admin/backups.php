<?php
/* ============================================================
   BACKUPS — System Administrator
   ------------------------------------------------------------
   All POST/GET handling and redirects MUST happen BEFORE
   including _layout.php (which prints HTML).
   ============================================================ */

require_once __DIR__ . '/../includes/auth.php';
requireSysadmin();

$u = currentUser();

/* ============================================================
   BACKUP DIRECTORY
   ============================================================ */
$BACKUP_DIR = __DIR__ . '/../backups/';
if (!is_dir($BACKUP_DIR)) {
    @mkdir($BACKUP_DIR, 0775, true);
}

/* ============================================================
   CREATE BACKUP
   ============================================================ */
if (isset($_GET['create'])) {
    $filename = 'backup_' . 'date'('Y-m-d_His') . '.sql';
    $filepath = $BACKUP_DIR . $filename;

    try {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();

        $sql  = "-- ============================================\n";
        $sql .= "-- Station Management System — Database Backup\n";
        $sql .= "-- Generated: " . 'date'('c') . "\n";
        $sql .= "-- Database: " . $dbName . "\n";
        $sql .= "-- ============================================\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $sql .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
        $sql .= "SET time_zone = '+00:00';\n\n";

        foreach ($tables as $t) {
            $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch();
            $sql .= "-- --------------------------------------------\n";
            $sql .= "-- Table: `$t`\n";
            $sql .= "-- --------------------------------------------\n\n";
            $sql .= "DROP TABLE IF EXISTS `$t`;\n";
            $sql .= $create['Create Table'] . ";\n\n";

            $rows = $pdo->query("SELECT * FROM `$t`")->fetchAll();
            if ($rows) {
                foreach ($rows as $row) {
                    $vals = array_map(
                        fn($v) => is_null($v) ? 'NULL' : $pdo->quote((string)$v),
                        $row
                    );
                    $sql .= "INSERT INTO `$t` VALUES (" . implode(',', $vals) . ");\n";
                }
                $sql .= "\n";
            }
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        if (file_put_contents($filepath, $sql) !== false) {
            flash('✅ Backup created: ' . $filename);
            try {
                $pdo->prepare("INSERT INTO activity_logs (user_id, username, action, details, ip)
                               VALUES (?,?,?,?,?)")
                    ->execute([
                        $u['id'], $u['username'],
                        'backup', $filename, $_SERVER['REMOTE_ADDR'] ?? ''
                    ]);
            } catch (PDOException $e) {}
        } else {
            flash('❌ Could not write backup file. Check folder permissions.');
        }
    } catch (PDOException $e) {
        flash('❌ Backup failed: ' . $e->getMessage());
    }
    redirect('backups.php');
}

/* ============================================================
   DELETE BACKUP
   ============================================================ */
if (isset($_GET['delete'])) {
    $file = basename(get('delete'));
    $path = $BACKUP_DIR . $file;
    if (is_file($path) && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
        @unlink($path);
        flash('🗑 Backup deleted');
    } else {
        flash('❌ File not found');
    }
    redirect('backups.php');
}

/* ============================================================
   DOWNLOAD BACKUP
   ============================================================ */
if (isset($_GET['download'])) {
    $file = basename(get('download'));
    $path = $BACKUP_DIR . $file;
    if (is_file($path) && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
        if (ob_get_level()) ob_end_clean();

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($path));
        header('Pragma: no-cache');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        readfile($path);
        exit;
    }
    flash('❌ File not found');
    redirect('backups.php');
}

/* ============================================================
   NOW include layout (HTML output starts here)
   ============================================================ */
require_once __DIR__ . '/_layout.php';

/* ============================================================
   LOAD BACKUPS
   ============================================================ */
$backups = [];
$totalSize = 0;
if (is_dir($BACKUP_DIR)) {
    foreach (glob($BACKUP_DIR . '*.sql') as $file) {
        $backups[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'time' => filemtime($file),
        ];
        $totalSize += filesize($file);
    }
    usort($backups, fn($a, $b) => $b['time'] <=> $a['time']);
}

$latestBackup = $backups[0] ?? null;
$daysSince    = $latestBackup ? floor(('time'() - $latestBackup['time']) / 86400) : null;

$diskFree  = @disk_free_space($BACKUP_DIR);
$diskTotal = @disk_total_space($BACKUP_DIR);
?>

<style>
.bk-tiles {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;
  margin-bottom: 18px;
}
@media (max-width: 500px) {
  .bk-tiles { grid-template-columns: 1fr; }
}
.bk-tile {
  background: #fff; border-radius: 12px; padding: 16px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #edf2f7;
  text-align: center;
}
.bk-tile .n {
  font-size: 20px; font-weight: 800; color: #1a365d; line-height: 1.2;
}
.bk-tile .lbl {
  font-size: 10px; color: #718096; text-transform: uppercase;
  font-weight: 600; letter-spacing: 0.4px; margin-top: 4px;
}

.bk-section {
  background: #fff; border-radius: 14px; padding: 20px; margin-bottom: 16px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #edf2f7;
}
.bk-section h3 {
  font-size: 15px; color: #1a365d; margin: 0 0 4px;
  font-weight: 700; display: flex; align-items: center; gap: 8px;
}
.bk-section .subtitle {
  font-size: 12px; color: #a0aec0; margin: 0 0 16px; line-height: 1.5;
}

.bk-action {
  display: flex; gap: 16px; align-items: center;
  padding: 18px; background: linear-gradient(135deg, #f0fff4, #c6f6d5);
  border-radius: 12px; border: 2px dashed #68d391;
}
.bk-action .icon { font-size: 42px; flex-shrink: 0; }
.bk-action .body { flex: 1; min-width: 0; }
.bk-action .title { font-size: 15px; font-weight: 700; color: #22543d; margin-bottom: 4px; }
.bk-action .desc  { font-size: 12px; color: #2f855a; line-height: 1.5; }
.bk-action a.button {
  padding: 14px 24px; font-size: 14px; background: #2f855a; color: #fff;
  border: none; border-radius: 10px; font-weight: 700; cursor: pointer;
  white-space: nowrap; box-shadow: 0 4px 14px rgba(47,133,90,0.3);
  transition: transform 0.1s ease; text-decoration: none;
  display: inline-block;
}
.bk-action a.button:hover { background: #276749; }
.bk-action a.button:active { transform: scale(0.97); }
@media (max-width: 620px) {
  .bk-action { flex-direction: column; text-align: center; }
  .bk-action a.button { width: 100%; }
}

.bk-item {
  display: flex; align-items: center; gap: 12px;
  padding: 14px; background: #f7fafc; border-radius: 12px;
  margin-bottom: 8px; border: 1px solid #edf2f7;
  transition: all 0.15s ease;
}
.bk-item:hover { background: #fff; border-color: #cbd5e0; }
.bk-item.latest { background: #f0fff4; border-color: #9ae6b4; }
.bk-icon {
  width: 42px; height: 42px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; flex-shrink: 0;
  background: linear-gradient(135deg, #2c5282, #1a365d);
  color: #fff;
}
.bk-item.latest .bk-icon { background: linear-gradient(135deg, #2f855a, #22543d); }
.bk-info { flex: 1; min-width: 0; }
.bk-name {
  font-size: 13px; font-weight: 700; color: #2d3748;
  font-family: monospace; word-break: break-all;
}
.bk-meta { font-size: 11px; color: #718096; margin-top: 2px; }
.bk-actions { display: flex; gap: 4px; flex-shrink: 0; }

.warn-box {
  background: #fffaf0; border-left: 4px solid #dd6b20;
  padding: 14px 16px; border-radius: 10px;
  display: flex; gap: 12px; align-items: flex-start;
  margin-top: 12px;
}
.warn-box .ic { font-size: 22px; flex-shrink: 0; }
.warn-box .title { font-size: 14px; font-weight: 700; color: #7b341e; margin-bottom: 4px; }
.warn-box .desc  { font-size: 12px; color: #7b341e; line-height: 1.6; }

.guide-step {
  display: flex; gap: 12px; padding: 12px 14px;
  background: #f7fafc; border-radius: 10px; margin-bottom: 8px;
  border-left: 3px solid #3182ce;
}
.guide-step .n {
  width: 28px; height: 28px; border-radius: 50%;
  background: #3182ce; color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 700; flex-shrink: 0;
}
.guide-step .txt { flex: 1; font-size: 13px; color: #2d3748; line-height: 1.5; }
</style>

<!-- ============================================================
     TILES
     ============================================================ -->
<div class="bk-tiles">
  <div class="bk-tile">
    <div class="n"><?= count($backups) ?></div>
    <div class="lbl">Backups</div>
  </div>
  <div class="bk-tile">
    <div class="n"><?= number_format($totalSize / 1024, 1) ?> KB</div>
    <div class="lbl">Total Size</div>
  </div>
  <div class="bk-tile">
    <div class="n">
      <?php if ($latestBackup): ?>
        <?= $daysSince === 0 ? 'Today' : ($daysSince === 1 ? 'Yesterday' : $daysSince . 'd ago') ?>
      <?php else: ?>
        Never
      <?php endif; ?>
    </div>
    <div class="lbl">Last Backup</div>
  </div>
</div>

<!-- ============================================================
     CREATE
     ============================================================ -->
<div class="bk-section">
  <h3>➕ Create a New Backup</h3>
  <p class="subtitle">Generates a complete SQL file of every table and row in the database.</p>

  <div class="bk-action">
    <div class="icon">💾</div>
    <div class="body">
      <div class="title">Save the current state of the database</div>
      <div class="desc">Takes a few seconds. When it finishes, download the file and store it safely.</div>
    </div>
    <a class="button" href="?create=1"
       onclick="return confirm('Create a new backup now?');">
      💾 Create Backup
    </a>
  </div>

  <?php if ($latestBackup && $daysSince !== null && $daysSince >= 7): ?>
    <div class="warn-box">
      <div class="ic">⚠️</div>
      <div>
        <div class="title">It's been <?= $daysSince ?> days since your last backup</div>
        <div class="desc">Backups should be made at least once a week.</div>
      </div>
    </div>
  <?php elseif (!$latestBackup): ?>
    <div class="warn-box">
      <div class="ic">⚠️</div>
      <div>
        <div class="title">No backups yet</div>
        <div class="desc">Create your first backup to protect the station's records.</div>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- ============================================================
     LIST
     ============================================================ -->
<div class="bk-section">
  <h3>
    📦 Available Backups
    <span class="badge" style="margin-left:6px;"><?= count($backups) ?></span>
  </h3>
  <p class="subtitle">Download any backup to keep a copy on your computer or USB drive.</p>

  <?php if (!$backups): ?>
    <div class="empty">
      No backups yet. Click <strong>💾 Create Backup</strong> above to make one.
    </div>
  <?php else: foreach ($backups as $i => $b):
    $sizeKb = $b['size'] / 1024;
    $age = floor(('time'() - $b['time']) / 86400);
    $isLatest = ($i === 0);
  ?>
    <div class="bk-item <?= $isLatest ? 'latest' : '' ?>">
      <div class="bk-icon"><?= $isLatest ? '✅' : '📄' ?></div>
      <div class="bk-info">
        <div class="bk-name"><?= e($b['name']) ?></div>
        <div class="bk-meta">
          <?= number_format($sizeKb, 1) ?> KB
          · <?= 'date'('jS M Y, H:i', $b['time']) ?>
          · <?= $age === 0 ? 'Today' : ($age === 1 ? 'Yesterday' : $age . ' days ago') ?>
          <?php if ($isLatest): ?>
            <span class="badge green" style="margin-left:6px;">Latest</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="bk-actions">
        <a class="btn-sm btn-primary" style="padding:8px 14px;font-size:12px;"
           href="?download=<?= urlencode($b['name']) ?>" title="Download">⬇</a>
        <a class="btn-sm btn-danger" style="padding:8px 14px;font-size:12px;"
           data-confirm="Delete this backup permanently?"
           href="?delete=<?= urlencode($b['name']) ?>" title="Delete">🗑</a>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<!-- ============================================================
     GUIDE
     ============================================================ -->
<div class="bk-section" style="background:linear-gradient(135deg,#ebf8ff,#fff);border-left:4px solid #3182ce;">
  <h3 style="color:#2c5282;">📖 How to Use These Backups</h3>
  <p class="subtitle" style="color:#2c5282;">Follow this simple routine to keep the station's records safe.</p>

  <div class="guide-step">
    <div class="n">1</div>
    <div class="txt"><strong>Create a backup</strong> every week (or after big events like Harvest or Easter).</div>
  </div>
  <div class="guide-step">
    <div class="n">2</div>
    <div class="txt"><strong>Download</strong> the newest file to your computer, USB drive, or Google Drive.</div>
  </div>
  <div class="guide-step">
    <div class="n">3</div>
    <div class="txt"><strong>Keep 4–5 recent backups</strong>. Delete older ones to save space.</div>
  </div>
  <div class="guide-step">
    <div class="n">4</div>
    <div class="txt">If the database is lost or corrupted, <strong>import the latest backup</strong> via <em>phpMyAdmin → Import</em>.</div>
  </div>
</div>

<!-- ============================================================
     RESTORE WARNING
     ============================================================ -->
<div class="bk-section">
  <h3 style="color:#9b2c2c;">⚠️ Restoring a Backup</h3>
  <p class="subtitle">Restoring overwrites the live database with the backup file, so it must be done carefully.</p>

  <div class="warn-box">
    <div class="ic">🚨</div>
    <div>
      <div class="title">Restoring will overwrite the current database</div>
      <div class="desc">
        <strong>1.</strong> Download the backup file you want.<br>
        <strong>2.</strong> Open <em>phpMyAdmin</em> → select your database → <em>Import</em>.<br>
        <strong>3.</strong> Choose the SQL file → click <em>Go</em>.<br>
        <strong>4.</strong> Log back in after the import.
      </div>
    </div>
  </div>
</div>

<!-- ============================================================
     DISK SPACE
     ============================================================ -->
<?php if ($diskFree !== false && $diskTotal !== false):
  $diskUsedPct = round(($diskTotal - $diskFree) / $diskTotal * 100, 1);
  $diskColor = $diskUsedPct > 90 ? '#e53e3e' : ($diskUsedPct > 75 ? '#dd6b20' : '#48bb78');
?>
  <div class="bk-section">
    <h3>💽 Disk Space</h3>
    <p class="subtitle">How much space is available for backups and files.</p>

    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;">
      <span style="color:#718096;">Used: <?= number_format($diskUsedPct, 1) ?>%</span>
      <span style="font-weight:700;color:#2d3748;">
        <?= number_format($diskFree / 1024 / 1024 / 1024, 2) ?> GB free
        of <?= number_format($diskTotal / 1024 / 1024 / 1024, 2) ?> GB
      </span>
    </div>
    <div style="background:#edf2f7;height:14px;border-radius:999px;overflow:hidden;">
      <div style="background:<?= $diskColor ?>;height:100%;width:<?= $diskUsedPct ?>%;
                  border-radius:999px;transition:width 0.4s ease;"></div>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>