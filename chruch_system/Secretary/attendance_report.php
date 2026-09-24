<?php
require_once __DIR__ . '/_layout.php';

/* ============================================================
   ATTENDANCE REPORT — Secretary
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
$totPresent = 0;
$totAbsent  = 0;
$totExcused = 0;
foreach ($sessions as $s) {
    $totPresent += (int)($s['present'] ?? 0);
    $totAbsent  += (int)($s['absent']  ?? 0);
    $totExcused += (int)($s['excused'] ?? 0);
}

$overallRate = ($totPresent + $totAbsent + $totExcused) > 0
    ? round($totPresent / ($totPresent + $totAbsent + $totExcused) * 100)
    : 0;
?>

<!-- ============================================================
     FILTERS
     ============================================================ -->
<div class="card no-print">
  <h3>📅 Report Period</h3>
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
      <button class="btn-primary" style="margin-top:0;">🔎 Generate Report</button>
      <a class="btn-sm btn-ghost" style="text-align:center;padding:12px;"
         href="attendance_report.php">↺ Reset</a>
    </div>
  </form>
</div>

<!-- ============================================================
     SUMMARY TILES
     ============================================================ -->
<div class="stat-grid">
  <div class="stat" style="background:linear-gradient(135deg,#3182ce,#2c5282);">
    <div class="num"><?= $totSessions ?></div>
    <div class="lbl">Sessions</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#38a169,#276749);">
    <div class="num"><?= $totPresent ?></div>
    <div class="lbl">Present</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#dd6b20,#9c4221);">
    <div class="num"><?= $totExcused ?></div>
    <div class="lbl">Excused</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#e53e3e,#9b2c2c);">
    <div class="num"><?= $totAbsent ?></div>
    <div class="lbl">Absent</div>
  </div>
</div>

<!-- ============================================================
     PRINT-ONLY HEADER
     ============================================================ -->
<div class="card print-only" style="display:none;">
  <h3 style="text-align:center;">
    <?= e($settings['church_name'] ?? 'Church') ?><br>
    <small style="font-weight:400;color:#718096;">
      Attendance Report ·
      <?= 'date'('jS F Y', 'strtotime'($from)) ?> — <?= 'date'('jS F Y', 'strtotime'($to)) ?>
    </small>
  </h3>
  <p style="text-align:center;font-size:13px;color:#718096;">
    Overall attendance rate: <strong><?= $overallRate ?>%</strong>
  </p>
</div>

<!-- ============================================================
     PER-MEMBER SUMMARY
     ============================================================ -->
<div class="card">
  <h3>
    👥 Attendance by Member
    <span class="badge" style="margin-left:8px;"><?= count($rows) ?></span>
  </h3>

  <?php if (!$rows): ?>
    <div class="empty">No attendance records in this period.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead>
        <tr>
          <th>Member</th>
          <th style="text-align:center;">Present</th>
          <th style="text-align:center;">Excused</th>
          <th style="text-align:center;">Absent</th>
          <th style="text-align:center;">Rate</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r):
          if (!is_array($r)) continue;
          $total = (int)($r['total'] ?? 0);
          $rate  = $total > 0 ? round((int)$r['present'] / $total * 100) : 0;
          $color = $rate >= 75 ? '#38a169' : ($rate >= 50 ? '#dd6b20' : '#e53e3e');
        ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:8px;">
                <?= memberAvatar($r, 32) ?>
                <div>
                  <strong style="font-size:13px;"><?= e($r['full_name'] ?? '') ?></strong>
                  <?php if (!empty($r['day_born'])): ?>
                    <div style="font-size:11px;color:#718096;">
                      <?= e($r['day_born']) ?> Born
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td style="text-align:center;color:#22543d;font-weight:700;">
              <?= (int)($r['present'] ?? 0) ?>
            </td>
            <td style="text-align:center;color:#7b341e;font-weight:700;">
              <?= (int)($r['excused'] ?? 0) ?>
            </td>
            <td style="text-align:center;color:#742a2a;font-weight:700;">
              <?= (int)($r['absent'] ?? 0) ?>
            </td>
            <td style="text-align:center;">
              <div style="display:inline-block;min-width:60px;">
                <div style="font-weight:700;color:<?= $color ?>;"><?= $rate ?>%</div>
                <div style="background:#edf2f7;height:6px;border-radius:999px;
                            margin-top:4px;overflow:hidden;">
                  <div style="background:<?= $color ?>;height:100%;width:<?= $rate ?>%;"></div>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<!-- ============================================================
     PER-SESSION SUMMARY
     ============================================================ -->
<div class="card">
  <h3>
    📚 Attendance by Session
    <span class="badge" style="margin-left:8px;"><?= count($sessions) ?></span>
  </h3>

  <?php if (!$sessions): ?>
    <div class="empty">No sessions in this period.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Session</th>
          <th>Category</th>
          <th style="text-align:center;">Present</th>
          <th style="text-align:center;">Excused</th>
          <th style="text-align:center;">Absent</th>
          <th style="text-align:center;">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sessions as $s):
          if (!is_array($s)) continue;
        ?>
          <tr>
            <td style="font-size:12px;">
              <?= !empty($s['session_date']) ? 'date'('d/m/y', 'strtotime'($s['session_date'])) : '—' ?>
            </td>
            <td><strong style="font-size:13px;"><?= e($s['title'] ?? '') ?></strong></td>
            <td><span class="badge"><?= e($s['category'] ?? '') ?></span></td>
            <td style="text-align:center;color:#22543d;font-weight:700;">
              <?= (int)($s['present'] ?? 0) ?>
            </td>
            <td style="text-align:center;color:#7b341e;font-weight:700;">
              <?= (int)($s['excused'] ?? 0) ?>
            </td>
            <td style="text-align:center;color:#742a2a;font-weight:700;">
              <?= (int)($s['absent'] ?? 0) ?>
            </td>
            <td style="text-align:center;"><?= (int)($s['total'] ?? 0) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<!-- ============================================================
     PRINT BUTTON
     ============================================================ -->
<div class="card no-print" style="text-align:center;">
  <button class="btn-sm btn-primary" style="max-width:220px;margin:0 auto;"
          onclick="window.print()">🖨 Print / Save as PDF</button>
</div>

<style>
@media print {
  header, nav.tabs, footer, .no-print, .flash { display: none !important; }
  body { background: #fff !important; }
  main { padding: 0 !important; max-width: 100% !important; }
  .card { box-shadow: none !important; border: 1px solid #cbd5e0;
          page-break-inside: avoid; margin-bottom: 10px; }
  .stat { background: #fff !important; color: #000 !important;
          border: 1px solid #cbd5e0; }
  .stat .num { font-size: 16px; }
  table { font-size: 12px; }
  .print-only { display: block !important; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>