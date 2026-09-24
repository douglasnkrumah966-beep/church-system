<?php
require_once __DIR__ . '/_layout.php';

/* ============================================================
   DAY BORN GROUP DASHBOARD — Secretary
   Shows only members born on a specific day of the week.
   URL: dayborn_group.php?day=Tuesday
   ============================================================ */

$GROUP_ORDER = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
$day = get('day');

if (!in_array($day, $GROUP_ORDER, true)) {
    flash('❌ Invalid day group');
    redirect('dayborn.php');
}

$groupName = $day . ' Born';

/* ---------- Fetch members of this group ---------- */
$stmt = $pdo->prepare("SELECT * FROM members
    WHERE day_born = ?
    ORDER BY CASE WHEN dob IS NULL THEN 1 ELSE 0 END,
             DATE_FORMAT(dob,'%m-%d')");
$stmt->execute([$day]);
$members = $stmt->fetchAll();

/* ---------- Celebrants ---------- */
$today = date('m-d');
$todayCelebrants = array_filter($members,
    fn($m) => !empty($m['dob']) && date('m-d', strtotime($m['dob'])) === $today
);

$thisMonth = date('m');
$thisMonthCelebrants = array_filter($members,
    fn($m) => !empty($m['dob']) && date('m', strtotime($m['dob'])) === $thisMonth
);

/* ---------- Group color ---------- */
$color = [
    'Sunday'    => '#805ad5',
    'Monday'    => '#3182ce',
    'Tuesday'   => '#38a169',
    'Wednesday' => '#dd6b20',
    'Thursday'  => '#d53f8c',
    'Friday'    => '#319795',
    'Saturday'  => '#b7791f',
][$day] ?? '#3182ce';
?>

<!-- Back button -->
<a class="btn-sm btn-ghost" style="display:inline-block;margin-bottom:12px;"
   href="dayborn.php">← Back to All Groups</a>

<!-- ============================================================
     HERO HEADER
     ============================================================ -->
<div style="background:linear-gradient(135deg, <?= $color ?>, <?= $color ?>cc);
            color:#fff;border-radius:16px;padding:24px 20px;text-align:center;
            box-shadow:0 6px 20px rgba(0,0,0,0.15);">
  <div style="font-size:26px;font-weight:800;letter-spacing:0.5px;">
    <?= e($groupName) ?>
  </div>
  <div style="font-size:14px;opacity:0.95;margin-top:6px;">
    <?= count($members) ?> members
  </div>
</div>

<!-- ============================================================
     SUMMARY TILES
     ============================================================ -->
<div class="stat-grid" style="margin-top:16px;">
  <div class="stat" style="background:linear-gradient(135deg,#2c5282,#1a365d);">
    <div class="num"><?= count($members) ?></div>
    <div class="lbl">Total Members</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#d53f8c,#97266d);">
    <div class="num"><?= count($todayCelebrants) ?></div>
    <div class="lbl">Birthday Today</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#dd6b20,#9c4221);">
    <div class="num"><?= count($thisMonthCelebrants) ?></div>
    <div class="lbl">This Month</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,<?= $color ?>,<?= $color ?>cc);">
    <div class="num"><?= e($day) ?></div>
    <div class="lbl">Day Born</div>
  </div>
</div>

<!-- ============================================================
     TODAY'S CELEBRANTS (this group only)
     ============================================================ -->
<?php if ($todayCelebrants): ?>
<div class="card" style="margin-top:16px;">
  <h3>🎂 Celebrating Today (<?= count($todayCelebrants) ?>)</h3>
  <?php foreach ($todayCelebrants as $m): ?>
    <div class="member-card">
      <?= memberAvatar($m, 44) ?>
      <div class="member-info">
        <div class="name"><?= e($m['full_name']) ?></div>
        <div class="meta"><?= e($m['phone'] ?: 'no phone') ?></div>
      </div>
      <span class="badge green">🎉 Happy Birthday!</span>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ============================================================
     ALL MEMBERS IN THIS GROUP
     ============================================================ -->
<div class="card">
  <h3>👥 All <?= e($groupName) ?> Members (<?= count($members) ?>)</h3>

  <?php if (!$members): ?>
    <div class="empty">No members registered as <?= e($groupName) ?> yet.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead>
        <tr>
          <th>Member</th>
          <th>Gender</th>
          <th>Date of Birth</th>
          <th>Phone</th>
          <th>Marital Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($members as $m):
          $isToday = !empty($m['dob']) && date('m-d', strtotime($m['dob'])) === $today;
        ?>
          <tr <?= $isToday ? 'style="background:#f0fff4;"' : '' ?>>
            <td>
              <div style="display:flex;align-items:center;gap:8px;">
                <?= memberAvatar($m, 36) ?>
                <div>
                  <strong><?= e($m['full_name']) ?></strong>
                  <?php if ($isToday): ?>
                    <span class="badge green" style="margin-left:6px;">🎂 Today</span>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td><?= e($m['gender'] ?: '—') ?></td>
            <td><?= e(displayDob($m)) ?></td>
            <td><?= e($m['phone'] ?: '—') ?></td>
            <td><?= e($m['marital_status'] ?: '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<!-- ============================================================
     QUICK SWITCH TO OTHER GROUPS
     ============================================================ -->
<div class="card">
  <h3>🔄 Switch Group</h3>
  <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;">
    <?php foreach ($GROUP_ORDER as $d):
      if ($d === $day) continue;
      $c = [
        'Sunday'    => '#805ad5',
        'Monday'    => '#3182ce',
        'Tuesday'   => '#38a169',
        'Wednesday' => '#dd6b20',
        'Thursday'  => '#d53f8c',
        'Friday'    => '#319795',
        'Saturday'  => '#b7791f',
      ][$d];
    ?>
      <a class="btn-sm" style="text-align:center;background:<?= $c ?>;
                              color:#fff;padding:12px;text-decoration:none;
                              border-radius:8px;font-weight:600;"
         href="dayborn_group.php?day=<?= urlencode($d) ?>">
        <?= $d ?> Born
      </a>
    <?php endforeach; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>