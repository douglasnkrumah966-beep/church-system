<?php
require_once __DIR__ . '/_layout.php';

/* ============================================================
   ATTENDANCE REPORT — Catechist
   Summary of attendance per member and per session.
   ============================================================ */

$from = get('from') ?: 'date'('Y-m-01');
$to   = get('to')   ?: 'date'('Y-m-t');

/* ============================================================
   PER-MEMBER SUMMARY
   ============================================================ */
$rows = [];
try {
    $stmt = $pdo->prepare("
        SELECT m.id, m.full_name, m.photo, m.day_born,
               SUM(CASE WHEN ar.status='present' THEN 1 ELSE 0 END) AS present,
               SUM(CASE WHEN ar.status='absent'  THEN 1 ELSE 0 END) AS absent,
               SUM(CASE WHEN ar.status='excused' THEN 1 ELSE 0 END) AS excused,
               COUNT(ar.id) AS total
        FROM members m
        LEFT JOIN attendance_records ar ON ar.member_id = m.id
        LEFT JOIN attendance_sessions s ON s.id = ar.session_id
        WHERE (s.session_date BETWEEN ? AND ?) OR s.session_date IS NULL
        GROUP BY m.id, m.full_name, m.photo, m.day_born
        ORDER BY m.full_name
    ");
    $stmt->execute([$from, $to]);
    $rows = $stmt->fetchAll();
    if (!is_array($rows)) $rows = [];
} catch (PDOException $e) {
    $rows = [];
}

/* ============================================================
   PER-SESSION SUMMARY
   ============================================================ */
$sessions = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.id, s.title, s.session_date, s.category,
               SUM(CASE WHEN ar.status='present' THEN 1 ELSE 0 END) AS present,
               SUM(CASE WHEN ar.status='absent'  THEN 1 ELSE 0 END) AS absent,
               SUM(CASE WHEN ar.status='excused' THEN 1 ELSE 0 END) AS excused,
               COUNT(ar.id) AS total
        FROM attendance_sessions s
        LEFT JOIN attendance_records ar ON ar.session_id = s.id
        WHERE s.session_date BETWEEN ? AND ?
        GROUP BY s.id, s.title, s.session_date, s.category
        ORDER BY s.session_date DESC, s.id DESC
    ");
    $stmt->execute([$from, $to]);
    $sessions = $stmt->fetchAll();
    if (!is_array($sessions)) $sessions = [];
} catch (PDOException $e) {
    $sessions = [];
}

/* ============================================================
   OVERALL TOTALS
   ============================================================ */
$totSessions = count($sessions);
$totPresent  = 0;
$totAbsent   = 0;
$totExcused  = 0;

foreach ($sessions as $s) {
    $totPresent += (int)($s['present'] ?? 0);
    $totAbsent  += (int)($s['absent']  ?? 0);
    $totExcused += (int)($s['excused'] ?? 0);
}

$totAll = $totPresent + $totAbsent + $totExcused;
$overallRate = $totAll > 0 ? round($totPresent / $totAll * 100) : 0;
?>

<style>
.cat-ar-hero {
  background: linear-gradient(135deg, #9c4221 0%, #dd6b20 100%);
  color: #fff;
  border-radius: 16px;
  padding: 22px;
  margin-bottom: 18px;
  box-shadow: 0 8px 30px rgba(156,66,33,0.25);
}
.cat-ar-hero h1 { font-size: 20px; margin: 0 0 4px; font-weight: 800; }
.cat-ar-hero p  { font-size: 13px; opacity: 0.9; margin: 0; }

.cat-ar-section {
  background: #fff;
  border-radius: 14px;
  padding: 20px;
  margin-bottom: 16px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  border: 1px solid #edf2f7;
}
.cat-ar-section h3 {
  font-size: 15px;
  color: #9c4221;
  margin: 0 0 4px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 8px;
}
.cat-ar-section .subtitle {
  font-size: 12px;
  color: #a0aec0;
  margin: 0 0 16px;
  line-height: 1.5;
}

/* Rate bar */
.rate-bar {
  background: #edf2f7;
  height: 8px;
  border-radius: 999px;
  overflow: hidden;
  margin-top: 6px;
  min-width: 60px;
}
.rate-bar-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.4s ease;
}

/* Member row */
.cat-ar-member {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  background: #f7fafc;
  border-radius: 10px;
  margin-bottom: 6px;
  border: 1px solid #edf2f7;
  transition: all 0.15s ease;
}
.cat-ar-member:hover { background: #fff; border-color: #cbd5e0; }

.cat-ar-info { flex: 1; min-width: 0; }
.cat-ar-name {
  font-size: 14px;
  font-weight: 700;
  color: #2d3748;
  word-break: break-word;
}
.cat-ar-meta {
  font-size: 11px;
  color: #718096;
  margin-top: 2px;
}

.cat-ar-stats {
  display: flex;
  gap: 6px;
  flex-shrink: 0;
  flex-wrap: wrap;
  justify-content: flex-end;
}
.cat-ar-stat {
  font-size: 11px;
  padding: 4px 10px;
  border-radius: 999px;
  font-weight: 700;
  text-align: center;
  min-width: 40px;
}
.cat-ar-stat.present { background: #c6f6d5; color: #22543d; }
.cat-ar-stat.excused { background: #feebc8; color: #7b341e; }
.cat-ar-stat.absent  { background: #fed7d7; color: #742a2a; }

/* Session row */
.cat-ar-session {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  background: #f7fafc;
  border-radius: 10px;
  margin-bottom: 6px;
  border: 1px solid #edf2f7;
  transition: all 0.15s ease;
}
.cat-ar-session:hover { background: #fff; border-color: #cbd5e0; }

.cat-ar-icon {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  flex-shrink: 0;
  background: linear-gradient(135deg, #dd6b20, #9c4221);
  color: #fff;
}
.cat-ar-session-info { flex: 1; min-width: 0; }
.cat-ar-session-name {
  font-size: 14px;
  font-weight: 700;
  color: #2d3748;
}
.cat-ar-session-meta {
  font-size: 11px;
  color: #718096;
  margin-top: 2px;
}

/* Summary tiles */
.cat-ar-tiles {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
  margin-bottom: 18px;
}
@media (min-width: 700px) {
  .cat-ar-tiles { grid-template-columns: repeat(4, 1fr); }
}
.cat-ar-tile {
  background: #fff;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  border: 1px solid #edf2f7;
  border-left: 4px solid #dd6b20;
  text-align: center;
}
.cat-ar-tile.green  { border-left-color: #38a169; }
.cat-ar-tile.orange { border-left-color: #dd6b20; }
.cat-ar-tile.red    { border-left-color: #e53e3e; }
.cat-ar-tile.blue   { border-left-color: #3182ce; }
.cat-ar-tile .n {
  font-size: 22px;
  font-weight: 800;
  color: #2d3748;
  line-height: 1.2;
}
.cat-ar-tile .lbl {
  font-size: 10px;
  color: #718096;
  text-transform: uppercase;
  font-weight: 600;
  letter-spacing: 0.4px;
  margin-top: 4px;
}

@media (max-width: 600px) {
  .cat-ar-member, .cat-ar-session {
    flex-wrap: wrap;
  }
  .cat-ar-stats {
    width: 100%;
    justify-content: flex-start;
    margin-top: 6px;
  }
}
</style>

<!-- ============================================================
     HERO
     ============================================================ -->
<div class="cat-ar-hero">
  <h1>📊 Attendance Report</h1>
  <p>See how often members attended catechism classes. Track patterns and follow up where needed.</p>
</div>

<!-- ============================================================
     FILTERS
     ============================================================ -->
<div class="cat-ar-section no-print">
  <h3>📅 Report Period</h3>
  <p class="subtitle">
    Choose a date range to focus the report. Defaults to the current month.
  </p>

  <form method="get">
    <div class="grid grid-2">
      <div>
        <label>From</label>
        <input name="from" type="date" value="<?= e($from) ?>">
      </div>
      <div>
        <label>To</label>
        <input name="to" type="date" value="<?= e($to) ?>">
      </div>
    </div>
    <div class="grid grid-2" style="margin-top:12px;">
      <button class="btn-primary" style="margin-top:0;background:#dd6b20;">
        🔎 Generate Report
      </button>
      <a class="btn-sm btn-ghost" style="text-align:center;padding:12px;text-decoration:none;"
         href="attendance_report.php">↺ Reset to This Month</a>
    </div>
  </form>
</div>

<!-- ============================================================
     SUMMARY TILES
     ============================================================ -->
<div class="cat-ar-tiles">
  <div class="cat-ar-tile blue">
    <div class="n"><?= $totSessions ?></div>
    <div class="lbl">Sessions</div>
  </div>
  <div class="cat-ar-tile green">
    <div class="n"><?= $totPresent ?></div>
    <div class="lbl">Present</div>
  </div>
  <div class="cat-ar-tile orange">
    <div class="n"><?= $totExcused ?></div>
    <div class="lbl">Excused</div>
  </div>
  <div class="cat-ar-tile red">
    <div class="n"><?= $totAbsent ?></div>
    <div class="lbl">Absent</div>
  </div>
</div>

<!-- Overall rate card -->
<?php if ($totAll > 0):
  $color = $overallRate >= 75 ? '#38a169' : ($overallRate >= 50 ? '#dd6b20' : '#e53e3e');
?>
  <div class="cat-ar-section">
    <h3>📈 Overall Attendance Rate</h3>
    <p class="subtitle">
      Percentage of present marks out of all attendance records in this period.
    </p>

    <div style="display:flex;align-items:center;gap:16px;">
      <div style="font-size:38px;font-weight:800;color:<?= $color ?>;line-height:1;">
        <?= $overallRate ?>%
      </div>
      <div style="flex:1;">
        <div class="rate-bar" style="height:14px;">
          <div class="rate-bar-fill"
               style="width:<?= $overallRate ?>%;background:<?= $color ?>;"></div>
        </div>
        <div style="font-size:12px;color:#718096;margin-top:6px;">
          <?= $totPresent ?> present · <?= $totExcused ?> excused · <?= $totAbsent ?> absent
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- ============================================================
     PRINT-ONLY HEADER
     ============================================================ -->
<div class="cat-ar-section print-only" style="display:none;text-align:center;">
  <h3 style="justify-content:center;font-size:18px;color:#9c4221;">
    <?= e($settings['church_name'] ?? 'Church') ?>
  </h3>
  <p style="font-size:13px;color:#718096;margin-top:4px;">
    Catechism Attendance Report ·
    <?= 'date'('jS F Y', 'strtotime'($from)) ?> — <?= 'date'('jS F Y', 'strtotime'($to)) ?>
  </p>
</div>

<!-- ============================================================
     PER-MEMBER SUMMARY
     ============================================================ -->
<div class="cat-ar-section">
  <h3>
    👥 Attendance by Member
    <span class="badge" style="margin-left:6px;background:#dd6b20;color:#fff;">
      <?= count($rows) ?>
    </span>
  </h3>
  <p class="subtitle">
    Sorted alphabetically. Rate shows the percentage of present marks out of all sessions.
  </p>

  <?php if (!$rows): ?>
    <div class="empty">
      No attendance records in this period. Create a session in
      <a href="attendance.php" style="color:#dd6b20;">Attendance</a> to start tracking.
    </div>
  <?php else: foreach ($rows as $r):
    if (!is_array($r)) continue;
    $total = (int)($r['total'] ?? 0);
    $rate  = $total > 0 ? round((int)($r['present'] ?? 0) / $total * 100) : 0;
    $color = $rate >= 75 ? '#38a169' : ($rate >= 50 ? '#dd6b20' : '#e53e3e');
  ?>
    <div class="cat-ar-member">
      <?= memberAvatar($r, 40) ?>
      <div class="cat-ar-info">
        <div class="cat-ar-name"><?= e($r['full_name'] ?? '') ?></div>
        <div class="cat-ar-meta">
          <?php if (!empty($r['day_born'])): ?>
            <?= e($r['day_born']) ?> Born · 
          <?php endif; ?>
          <?= $total ?> session<?= $total === 1 ? '' : 's' ?> ·
          <strong style="color:<?= $color ?>;"><?= $rate ?>% rate</strong>
        </div>
        <div class="rate-bar">
          <div class="rate-bar-fill"
               style="width:<?= $rate ?>%;background:<?= $color ?>;"></div>
        </div>
      </div>
      <div class="cat-ar-stats">
        <span class="cat-ar-stat present" title="Present">
          ✓ <?= (int)($r['present'] ?? 0) ?>
        </span>
        <span class="cat-ar-stat excused" title="Excused">
          🕊 <?= (int)($r['excused'] ?? 0) ?>
        </span>
        <span class="cat-ar-stat absent" title="Absent">
          ✕ <?= (int)($r['absent'] ?? 0) ?>
        </span>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<!-- ============================================================
     PER-SESSION SUMMARY
     ============================================================ -->
<div class="cat-ar-section">
  <h3>
    📚 Attendance by Session
    <span class="badge" style="margin-left:6px;background:#dd6b20;color:#fff;">
      <?= count($sessions) ?>
    </span>
  </h3>
  <p class="subtitle">
    Most recent sessions first.
  </p>

  <?php if (!$sessions): ?>
    <div class="empty">No sessions in this period.</div>
  <?php else: foreach ($sessions as $s):
    if (!is_array($s)) continue;
    $total = (int)($s['total'] ?? 0);
    $rate  = $total > 0 ? round((int)($s['present'] ?? 0) / $total * 100) : 0;
    $color = $rate >= 75 ? '#38a169' : ($rate >= 50 ? '#dd6b20' : '#e53e3e');
  ?>
    <div class="cat-ar-session">
      <div class="cat-ar-icon">
        <?= e(strtoupper(substr($s['category'] ?? '?', 0, 1))) ?>
      </div>
      <div class="cat-ar-session-info">
        <div class="cat-ar-session-name"><?= e($s['title'] ?? 'Untitled') ?></div>
        <div class="cat-ar-session-meta">
          <?= !empty($s['session_date'])
              ? 'date'('jS M Y', 'strtotime'($s['session_date']))
              : '—' ?>
          · <?= e($s['category'] ?? '') ?>
          · <strong style="color:<?= $color ?>;"><?= $rate ?>% present</strong>
        </div>
      </div>
      <div class="cat-ar-stats">
        <span class="cat-ar-stat present" title="Present">
          ✓ <?= (int)($s['present'] ?? 0) ?>
        </span>
        <span class="cat-ar-stat excused" title="Excused">
          🕊 <?= (int)($s['excused'] ?? 0) ?>
        </span>
        <span class="cat-ar-stat absent" title="Absent">
          ✕ <?= (int)($s['absent'] ?? 0) ?>
        </span>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<!-- ============================================================
     PRINT BUTTON
     ============================================================ -->
<div class="cat-ar-section no-print" style="text-align:center;">
  <button class="btn-sm btn-primary"
          style="max-width:220px;margin:0 auto;padding:14px 24px;background:#dd6b20;"
          onclick="window.print()">
    🖨 Print / Save as PDF
  </button>
</div>

<!-- ============================================================
     INFO NOTE
     ============================================================ -->
<div class="cat-ar-section no-print"
     style="background:#fffaf0;border-left:4px solid #dd6b20;">
  <h3 style="color:#7b341e;">💡 How to Read This Report</h3>
  <ul style="font-size:13px;color:#7b341e;line-height:1.9;padding-left:20px;margin-top:8px;">
    <li><strong>Rate %</strong> — the percentage of times a member was present out of all sessions</li>
    <li><strong style="color:#38a169;">Green ≥ 75%</strong> — excellent attendance</li>
    <li><strong style="color:#dd6b20;">Amber 50–74%</strong> — moderate attendance, worth a friendly reminder</li>
    <li><strong style="color:#e53e3e;">Red &lt; 50%</strong> — low attendance, consider following up with parents</li>
    <li><strong>Excused</strong> — did not attend but with a valid reason (does not count against the child)</li>
    <li><strong>Absent</strong> — did not attend without notice</li>
  </ul>
  <p style="font-size:12px;color:#7b341e;margin-top:10px;">
    <strong>Tip:</strong> Use <strong>Print / Save as PDF</strong> to send reports home to parents
    or bring them to parent-teacher meetings.
  </p>
</div>

<!-- ============================================================
     PRINT STYLES
     ============================================================ -->
<style>
@media print {
  header, nav.tabs, footer, .no-print, .flash { display: none !important; }
  body { background: #fff !important; }
  main { padding: 0 !important; max-width: 100% !important; }
  .cat-ar-section {
    box-shadow: none !important;
    border: 1px solid #cbd5e0;
    page-break-inside: avoid;
    margin-bottom: 12px;
  }
  .cat-ar-tile {
    background: #fff !important;
    border: 1px solid #cbd5e0;
  }
  .cat-ar-hero { display: none; }
  .print-only { display: block !important; }
  .rate-bar-fill {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .cat-ar-stat {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>