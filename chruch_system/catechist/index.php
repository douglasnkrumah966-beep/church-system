<?php
require_once __DIR__ . '/_layout.php';

/* ---------- Stats ---------- */
$totalMembers  = (int)$pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
$totalSacr     = (int)$pdo->query("SELECT COUNT(*) FROM sacraments")->fetchColumn();

$totalSessions = 0;
try {
    $totalSessions = (int)$pdo->query("SELECT COUNT(*) FROM attendance_sessions")->fetchColumn();
} catch (PDOException $e) {}

/* Members celebrating birthdays today */
$stmt = $pdo->prepare("SELECT * FROM members
                       WHERE dob IS NOT NULL AND DATE_FORMAT(dob,'%m-%d') = ?");
$stmt->execute(['date'('m-d')]);
$birthdays = $stmt->fetchAll();
if (!is_array($birthdays)) $birthdays = [];

/* Recent sacraments */
$recentSacraments = [];
try {
    $rows = $pdo->query("
        SELECT s.*, m.full_name AS member_name, m.photo AS member_photo
        FROM sacraments s
        LEFT JOIN members m ON m.id = s.member_id
        ORDER BY s.id DESC LIMIT 5
    ")->fetchAll();
    $recentSacraments = is_array($rows) ? $rows : [];
} catch (PDOException $e) {}

/* Upcoming birthdays this week */
$upcoming = [];
for ($i = 1; $i <= 7; $i++) {
    $key = 'date'('m-d', 'strtotime'("+$i day"));
    $stmt = $pdo->prepare("SELECT id, full_name, photo, day_born, dob
                           FROM members WHERE dob IS NOT NULL
                             AND DATE_FORMAT(dob,'%m-%d') = ?");
    $stmt->execute([$key]);
    foreach ($stmt->fetchAll() as $m) $upcoming[] = $m;
}
?>

<!-- Summary tiles -->
<div class="stat-grid">
  <div class="stat" style="background:linear-gradient(135deg,#dd6b20,#9c4221);">
    <div class="num"><?= $totalMembers ?></div>
    <div class="lbl">Members</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#805ad5,#553c9a);">
    <div class="num"><?= $totalSacr ?></div>
    <div class="lbl">Sacraments</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#38a169,#276749);">
    <div class="num"><?= $totalSessions ?></div>
    <div class="lbl">Catechism Sessions</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#d53f8c,#97266d);">
    <div class="num"><?= count($birthdays) ?></div>
    <div class="lbl">Birthdays Today</div>
  </div>
</div>

<!-- Quick actions -->
<div class="card" style="margin-top:16px;">
  <h3>⚡ Quick Actions</h3>
  <div class="grid grid-2">
    <a class="btn-sm btn-primary" style="text-align:center;padding:12px;"
       href="sacraments.php">✝️ Record Sacrament</a>
    <a class="btn-sm btn-primary" style="text-align:center;padding:12px;"
       href="attendance.php">📋 Mark Attendance</a>
    <a class="btn-sm btn-ghost" style="text-align:center;padding:12px;"
       href="members.php">👥 View Members</a>
    <a class="btn-sm btn-ghost" style="text-align:center;padding:12px;"
       href="attendance_report.php">📊 Attendance Report</a>
  </div>
</div>

<!-- Today's birthdays -->
<?php if ($birthdays): ?>
<div class="card" style="background:linear-gradient(135deg,#fbb6ce,#f687b3);color:#fff;">
  <h3 style="color:#fff;">🎂 Happy Birthday Today!</h3>
  <?php foreach ($birthdays as $m): ?>
    <div class="member-card" style="background:rgba(255,255,255,0.18);">
      <?= memberAvatar($m, 44) ?>
      <div class="member-info">
        <div class="name" style="color:#fff;"><?= e($m['full_name']) ?></div>
        <div class="meta" style="color:rgba(255,255,255,0.92);">
          Born on a <?= e($m['day_born'] ?: '—') ?>
          <?= $m['phone'] ? ' · 📞 ' . e($m['phone']) : '' ?>
        </div>
      </div>
      <span class="badge green">🎉</span>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Recent sacraments -->
<?php if ($recentSacraments): ?>
<div class="card">
  <h3>
    ✝️ Recent Sacraments
    <a class="btn-sm btn-ghost" style="float:right;padding:6px 12px;font-size:12px;"
       href="sacraments.php">View All →</a>
  </h3>
  <?php foreach ($recentSacraments as $s):
    $mr = !empty($s['member_name'])
        ? ['full_name' => $s['member_name'], 'photo' => $s['member_photo']]
        : null;
  ?>
    <div class="member-card">
      <?php if ($mr): ?>
        <?= memberAvatar($mr, 40) ?>
      <?php else: ?>
        <div class="avatar">?</div>
      <?php endif; ?>
      <div class="member-info">
        <div class="name"><?= e($s['member_name'] ?? 'Unknown') ?></div>
        <div class="meta">
          <span class="badge"><?= e($s['type']) ?></span>
          <?= !empty($s['date_received'])
              ? ' · ' . 'date'('jS M Y', 'strtotime'($s['date_received']))
              : '' ?>
          <?= !empty($s['minister']) ? ' · ' . e($s['minister']) : '' ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Upcoming birthdays -->
<?php if ($upcoming): ?>
<div class="card">
  <h3>📅 Birthdays This Week (<?= count($upcoming) ?>)</h3>
  <?php foreach ($upcoming as $m): ?>
    <div class="member-card">
      <?= memberAvatar($m, 40) ?>
      <div class="member-info">
        <div class="name"><?= e($m['full_name']) ?></div>
        <div class="meta">
          <?= 'date'('l, jS F', 'strtotime'($m['dob'])) ?>
          · <?= e($m['day_born'] ?: '—') ?> Born
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>