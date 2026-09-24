<?php
/* ============================================================
   DATABASE BROWSER
   ------------------------------------------------------------
   Read-only view of the database tables and their contents.
   ============================================================ */

require_once __DIR__ . '/../includes/auth.php';
requireSysadmin();

$u = currentUser();

/* ============================================================
   HANDLE EXPORT
   ============================================================ */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', get('table'));

    if ($table === '') {
        flash('❌ Invalid table');
        redirect('database.php');
    }

    try {
        /* Check the table exists */
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if (!$stmt->fetchColumn()) {
            flash('❌ Table not found');
            redirect('database.php');
        }

        /* Get all rows */
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll();

        /* Send CSV */
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $table . '_' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');

        /* Column headers */
        if (!empty($rows)) {
            fputcsv($out, array_keys($rows[0]));
        }

        /* Data rows */
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }

        fclose($out);
        exit;
    } catch (PDOException $e) {
        flash('❌ Export failed: ' . $e->getMessage());
        redirect('database.php');
    }
}

/* ============================================================
   NOW include layout (HTML output starts here)
   ============================================================ */
require_once __DIR__ . '/_layout.php';

/* ============================================================
   LOAD TABLES
   ============================================================ */
$tables = [];
try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $tables = is_array($tables) ? $tables : [];
} catch (PDOException $e) { $tables = []; }

/* Per-table info */
$tableInfo = [];
foreach ($tables as $t) {
    try {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        $size = $pdo->query("
            SELECT ROUND((data_length + index_length) / 1024, 2) AS kb
            FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = " . $pdo->quote($t)
        )->fetch();
        $tableInfo[] = [
            'name' => $t,
            'rows' => $count,
            'size' => (float)($size['kb'] ?? 0),
        ];
    } catch (PDOException $e) {
        $tableInfo[] = ['name' => $t, 'rows' => 0, 'size' => 0];
    }
}

$totalSize = array_sum(array_column($tableInfo, 'size'));
$totalRows = array_sum(array_column($tableInfo, 'rows'));
$dbName    = $pdo->query("SELECT DATABASE()")->fetchColumn() ?: '—';

/* Selected table */
$viewTable = preg_replace('/[^a-zA-Z0-9_]/', '', get('view'));
$viewRows  = [];
$viewCols  = [];
if ($viewTable && in_array($viewTable, $tables, true)) {
    try {
        $viewRows = $pdo->query("SELECT * FROM `$viewTable` ORDER BY 1 DESC LIMIT 50")->fetchAll();
        if (!empty($viewRows)) {
            $viewCols = array_keys($viewRows[0]);
        } else {
            /* No rows — get columns another way */
            $stmt = $pdo->prepare("DESCRIBE `$viewTable`");
            $stmt->execute();
            foreach ($stmt->fetchAll() as $col) {
                $viewCols[] = $col['Field'];
            }
        }
    } catch (PDOException $e) {
        $viewRows = [];
    }
}
?>

<style>
.db-hero {
  background: linear-gradient(135deg,#1a365d 0%,#2c5282 60%,#4299e1 100%);
  color:#fff; border-radius:16px; padding:24px;
  margin-bottom:18px; box-shadow:0 8px 30px rgba(26,54,93,0.25);
}
.db-hero h1 { font-size:22px; margin:0 0 4px; font-weight:800; }
.db-hero p  { font-size:13px; opacity:0.9; margin:0; }

.db-tiles {
  display:grid; grid-template-columns:repeat(2,1fr); gap:10px;
  margin-bottom:18px;
}
@media (min-width:700px) { .db-tiles { grid-template-columns:repeat(4,1fr); } }
.db-tile {
  background:#fff; border-radius:12px; padding:16px;
  box-shadow:0 2px 8px rgba(0,0,0,0.05); border:1px solid #edf2f7;
  text-align:center; border-left:4px solid #1a365d;
}
.db-tile .n { font-size:20px; font-weight:800; color:#1a365d; line-height:1.2; }
.db-tile .lbl {
  font-size:10px; color:#718096; text-transform:uppercase;
  font-weight:600; letter-spacing:0.4px; margin-top:4px;
}

.db-section {
  background:#fff; border-radius:14px; padding:20px; margin-bottom:16px;
  box-shadow:0 2px 8px rgba(0,0,0,0.05); border:1px solid #edf2f7;
}
.db-section h3 {
  font-size:15px; color:#1a365d; margin:0 0 4px; font-weight:700;
  display:flex; align-items:center; gap:8px;
}
.db-section .subtitle {
  font-size:12px; color:#a0aec0; margin:0 0 16px; line-height:1.5;
}

.db-row {
  display:flex; align-items:center; gap:12px;
  padding:12px 14px; background:#f7fafc; border-radius:10px;
  margin-bottom:6px; border:1px solid #edf2f7;
  transition:all 0.15s ease;
}
.db-row:hover { background:#fff; border-color:#cbd5e0; }
.db-row.active { background:#ebf8ff; border-color:#90cdf4; }
.db-icon {
  width:38px; height:38px; border-radius:10px;
  display:flex; align-items:center; justify-content:center;
  font-size:18px; flex-shrink:0;
  background:linear-gradient(135deg,#1a365d,#2c5282);
  color:#fff;
}
.db-info { flex:1; min-width:0; }
.db-name {
  font-size:13px; font-weight:700; color:#2d3748;
  font-family:monospace; word-break:break-all;
}
.db-meta { font-size:11px; color:#718096; margin-top:2px; }
.db-actions { display:flex; gap:4px; flex-shrink:0; }

.db-data-table {
  width:100%; border-collapse:collapse; font-size:12px;
}
.db-data-table th {
  background:#f7fafc; padding:10px; text-align:left;
  border-bottom:2px solid #edf2f7; color:#4a5568;
  font-size:11px; font-weight:700; text-transform:uppercase;
}
.db-data-table td {
  padding:10px; border-bottom:1px solid #edf2f7; color:#2d3748;
  max-width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
}
.db-data-table tr:hover { background:#f7fafc; }
</style>

<!-- ============================================================
     HERO
     ============================================================ -->
<div class="db-hero">
  <h1>🗄 Database Browser</h1>
  <p>Read-only view of the tables and their contents. Use this to inspect data or export to CSV.</p>
</div>

<!-- ============================================================
     SUMMARY TILES
     ============================================================ -->
<div class="db-tiles">
  <div class="db-tile">
    <div class="n"><?= count($tables) ?></div>
    <div class="lbl">Tables</div>
  </div>
  <div class="db-tile">
    <div class="n"><?= number_format($totalRows) ?></div>
    <div class="lbl">Total Rows</div>
  </div>
  <div class="db-tile">
    <div class="n"><?= number_format($totalSize, 1) ?> KB</div>
    <div class="lbl">Database Size</div>
  </div>
  <div class="db-tile">
    <div class="n"><?= e($dbName) ?></div>
    <div class="lbl">Database</div>
  </div>
</div>

<!-- ============================================================
     TABLE LIST
     ============================================================ -->
<div class="db-section">
  <h3>
    📊 Tables
    <span class="badge" style="margin-left:6px;"><?= count($tableInfo) ?></span>
  </h3>
  <p class="subtitle">Click "View" to browse the first 50 rows of a table.</p>

  <?php if (!$tableInfo): ?>
    <div class="empty">No tables found.</div>
  <?php else: foreach ($tableInfo as $t):
    $isActive = ($viewTable === $t['name']);
  ?>
    <div class="db-row <?= $isActive ? 'active' : '' ?>">
      <div class="db-icon">📄</div>
      <div class="db-info">
        <div class="db-name"><?= e($t['name']) ?></div>
        <div class="db-meta">
          <?= number_format($t['rows']) ?> row<?= $t['rows']===1?'':'s' ?>
          · <?= number_format($t['size'], 1) ?> KB
        </div>
      </div>
      <div class="db-actions">
        <a class="btn-sm btn-primary" style="padding:6px 12px;font-size:12px;"
           href="?view=<?= urlencode($t['name']) ?>">View</a>
        <a class="btn-sm btn-ghost" style="padding:6px 12px;font-size:12px;"
           href="?export=csv&table=<?= urlencode($t['name']) ?>"
           title="Export CSV">⬇</a>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<!-- ============================================================
     TABLE DATA
     ============================================================ -->
<?php if ($viewTable && in_array($viewTable, $tables, true)): ?>
  <div class="db-section">
    <h3>
      📋 <?= e($viewTable) ?>
      <span class="badge" style="margin-left:6px;"><?= count($viewRows) ?> rows (max 50)</span>
    </h3>

    <?php if (!$viewRows): ?>
      <div class="empty">Table is empty.</div>
    <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="db-data-table">
          <thead>
            <tr>
              <?php foreach ($viewCols as $col): ?>
                <th><?= e($col) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($viewRows as $r): ?>
              <tr>
                <?php foreach ($viewCols as $col): ?>
                  <td title="<?= e((string)($r[$col] ?? '')) ?>">
                    <?= e(is_null($r[$col] ?? null) ? '—' : (string)$r[$col]) ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div style="margin-top:14px;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;">
        <a class="btn-sm btn-ghost" style="padding:10px 16px;text-decoration:none;"
           href="database.php">↺ Close Table</a>
        <a class="btn-sm btn-primary" style="padding:10px 16px;text-decoration:none;"
           href="?export=csv&table=<?= urlencode($viewTable) ?>">
          ⬇ Export as CSV
        </a>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- ============================================================
     INFO NOTE
     ============================================================ -->
<div class="db-section" style="background:#ebf8ff;border-left:4px solid #3182ce;">
  <h3 style="color:#2c5282;">ℹ️ About the Database Browser</h3>
  <p style="font-size:13px;color:#2c5282;line-height:1.7;">
    This page is <strong>read-only</strong>. You cannot edit or delete data here.
    Use it to:
  </p>
  <ul style="font-size:13px;color:#2c5282;line-height:1.9;padding-left:20px;margin-top:6px;">
    <li>Inspect table structure and contents</li>
    <li>Verify data was recorded correctly</li>
    <li>Export any table as CSV (opens in Excel)</li>
    <li>Troubleshoot issues (e.g., missing columns)</li>
  </ul>
  <p style="font-size:12px;color:#2c5282;margin-top:10px;">
    For editing data, use the <strong>phpMyAdmin</strong> tool provided by XAMPP.
  </p>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>