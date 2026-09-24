<?php
require_once __DIR__ . '/_layout.php';

/* ============================================================
   DAY BORN DASHBOARD — Secretary
   ============================================================ */

$GROUP_ORDER = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

/* ---------- Count members per group ---------- */
$counts = array_fill_keys($GROUP_ORDER, 0);
$stmt = $pdo->query("SELECT day_born FROM members WHERE day_born IS NOT NULL");
foreach ($stmt->fetchAll() as $row) {
    $d = $row['day_born'];
    if (isset($counts[$d])) $counts[$d]++;
}

/* ---------- Today's celebrants — only members with a full DOB ---------- */
$today = date('m-d');
$stmt = $pdo->prepare("SELECT * FROM members
                       WHERE dob IS NOT NULL
                         AND DATE_FORMAT(dob,'%m-%d') = ?");
$stmt->execute([$today]);
$todayList = $stmt->fetchAll();

/* ---------- Upcoming this week ---------- */
$upcoming = [];
for ($i = 1; $i <= 7; $i++) {
    $key = date('m-d', strtotime("+$i day"));
    $stmt = $pdo->prepare("SELECT * FROM members
                           WHERE dob IS NOT NULL
                             AND DATE_FORMAT(dob,'%m-%d') = ?");
    $stmt->execute([$key]);
    foreach ($stmt->fetchAll() as $m) $upcoming[] = $m;
}
?>

<!-- ============================================================
     STAT TILES
     ============================================================ -->
<div class="stat-grid">
  <div class="stat" style="background:linear-gradient(135deg,#f687b3,#d53f8c);">
    <div class="num"><?= count($todayList) ?></div>
    <div class="lbl">Birthdays Today</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#dd6b20,#9c4221);">
    <div class="num"><?= count($upcoming) ?></div>
    <div class="lbl">Upcoming This Week</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#3182ce,#2c5282);">
    <div class="num"><?= array_sum($counts) ?></div>
    <div class="lbl">Total Members</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#805ad5,#553c9a);">
    <div class="num">7</div>
    <div class="lbl">Day Groups</div>
  </div>
</div>

<!-- ============================================================
     DAY BORN GROUPS
     ============================================================ -->
<div class="card" style="margin-top:16px;">
  <h3>📆 Day Born Groups — Tap to Open</h3>
  <div class="group-grid">
    <?php foreach ($GROUP_ORDER as $day):
      $cnt = $counts[$day];
      $color = [
        'Sunday'    => '#805ad5',
        'Monday'    => '#3182ce',
        'Tuesday'   => '#38a169',
        'Wednesday' => '#dd6b20',
        'Thursday'  => '#d53f8c',
        'Friday'    => '#319795',
        'Saturday'  => '#b7791f',
      ][$day];
    ?>
      <a class="group-card" style="background: linear-gradient(135deg, <?= $color ?>, <?= $color ?>cc);"
         href="dayborn_group.php?day=<?= urlencode($day) ?>">
        <div class="group-name"><?= $day ?> Born</div>
        <div class="group-count"><?= $cnt ?></div>
        <div class="group-label"><?= $cnt === 1 ? 'member' : 'members' ?></div>
        <div class="group-arrow">→</div>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- ============================================================
     TODAY'S CELEBRANTS
     ============================================================ -->
<div class="card">
  <h3>🎂 Today's Celebrants (<?= count($todayList) ?>)</h3>
  <?php if (!$todayList): ?>
    <div class="empty">No birthdays today.</div>
  <?php else: foreach ($todayList as $m): ?>
    <div class="member-card">
      <?= memberAvatar($m, 44) ?>
      <div class="member-info">
        <div class="name"><?= e($m['full_name']) ?></div>
        <div class="meta">
          Born on a <?= e($m['day_born'] ?: '—') ?> · <?= e($m['phone'] ?: 'no phone') ?>
        </div>
      </div>
      <span class="badge green">🎉 Happy Birthday!</span>
    </div>
  <?php endforeach; endif; ?>
</div>

<!-- ============================================================
     UPCOMING THIS WEEK
     ============================================================ -->
<div class="card">
  <h3>📅 Upcoming This Week (<?= count($upcoming) ?>)</h3>
  <?php if (!$upcoming): ?>
    <div class="empty">No upcoming birthdays this week.</div>
  <?php else: foreach ($upcoming as $m): ?>
    <div class="member-card">
      <?= memberAvatar($m, 44) ?>
      <div class="member-info">
        <div class="name"><?= e($m['full_name']) ?></div>
        <div class="meta">
          <?= date('jS F', strtotime($m['dob'])) ?>
          · <?= e($m['day_born'] ?: '—') ?> Born
        </div>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<!-- ============================================================
     STYLES
     ============================================================ -->
<style>
.group-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}
@media (min-width: 700px) {
  .group-grid { grid-template-columns: repeat(4, 1fr); }
}
.group-card {
  position: relative; display: block; padding: 16px 14px;
  border-radius: 14px; color: #fff; text-decoration: none;
  box-shadow: 0 4px 12px rgba(0,0,0,0.12);
  transition: transform 0.12s ease; min-height: 110px;
}
.group-card:active { transform: scale(0.97); }
.group-card .group-name { font-size: 14px; font-weight: 700; opacity: 0.95; }
.group-card .group-count { font-size: 34px; font-weight: 800; line-height: 1; margin: 8px 0 2px; }
.group-card .group-label { font-size: 11px; opacity: 0.85; }
.group-card .group-arrow { position: absolute; bottom: 12px; right: 14px; font-size: 20px; opacity: 0.7; }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>