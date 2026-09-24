<?php
/* ============================================================
   MY DAY BORN — Secretary-style strict view
   ------------------------------------------------------------
   Do all validation BEFORE including _layout.php so redirects
   work correctly (no output yet).
   ============================================================ */

require_once __DIR__ . '/../includes/auth.php';
requireRole(['member']);

/* Validate member record first */
$member = myMember($pdo);
if (!$member) {
    flash('❌ Your account is not linked to a member record');
    redirect('index.php');
}

$myDay = trim((string)($member['day_born'] ?? ''));
$VALID_DAYS = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

if ($myDay === '' || !in_array($myDay, $VALID_DAYS, true)) {
    flash('❌ Your Day Born is not set correctly. Please contact the Station Office.');
    redirect('index.php');
}

/* ============================================================
   NOW we can safely include the layout (which prints HTML)
   ============================================================ */
require_once __DIR__ . '/_layout.php';

/* ---------- Load only MY group ---------- */
$groupMembers = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, full_name, photo, day_born, dob, phone
        FROM members
        WHERE day_born = ?
        ORDER BY CASE WHEN dob IS NULL THEN 1 ELSE 0 END,
                 DATE_FORMAT(dob,'%m-%d')
    ");
    $stmt->execute([$myDay]);
    $rows = $stmt->fetchAll();
    $groupMembers = is_array($rows) ? $rows : [];
} catch (PDOException $e) {
    $groupMembers = [];
}

/* ---------- Celebrants in my group ---------- */
$today = 'date'('m-d');
$todayCelebrants = [];
$thisMonthCelebrants = [];

foreach ($groupMembers as $m) {
    if (empty($m['dob'])) continue;
    if ('date'('m-d', 'strtotime'($m['dob'])) === $today) {
        $todayCelebrants[] = $m;
    }
    if ('date'('m', 'strtotime'($m['dob'])) === 'date'('m')) {
        $thisMonthCelebrants[] = $m;
    }
}

/* ---------- Group color ---------- */
$color = [
    'Sunday'    => '#805ad5',
    'Monday'    => '#3182ce',
    'Tuesday'   => '#38a169',
    'Wednesday' => '#dd6b20',
    'Thursday'  => '#d53f8c',
    'Friday'    => '#319795',
    'Saturday'  => '#b7791f',
][$myDay];
?>

<!-- ============================================================
     GROUP HERO
     ============================================================ -->
<div style="background:linear-gradient(135deg, <?= $color ?>, <?= $color ?>cc);
            color:#fff;border-radius:16px;padding:24px 20px;text-align:center;
            box-shadow:0 6px 20px rgba(0,0,0,0.15);">
  <div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;
              opacity:0.85;font-weight:600;">
    My Day Born Group
  </div>
  <div style="font-size:28px;font-weight:800;letter-spacing:0.5px;margin-top:4px;">
    <?= e($myDay) ?> Born
  </div>
  <div style="font-size:14px;opacity:0.95;margin-top:6px;">
    <?= count($groupMembers) ?> member<?= count($groupMembers) === 1 ? '' : 's' ?>
  </div>
</div>

<!-- ============================================================
     SUMMARY TILES
     ============================================================ -->
<div class="stat-grid" style="margin-top:16px;">
  <div class="stat" style="background:linear-gradient(135deg,#2c5282,#1a365d);">
    <div class="num"><?= count($groupMembers) ?></div>
    <div class="lbl">Total in My Group</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#d53f8c,#97266d);">
    <div class="num"><?= count($todayCelebrants) ?></div>
    <div class="lbl">Birthday Today</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#dd6b20,#9c4221);">
    <div class="num"><?= count($thisMonthCelebrants) ?></div>
    <div class="lbl">Celebrating This Month</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,<?= $color ?>,<?= $color ?>cc);">
    <div class="num"><?= e($myDay) ?></div>
    <div class="lbl">Day Born</div>
  </div>
</div>

<!-- ============================================================
     CELEBRATING TODAY
     ============================================================ -->
<?php if ($todayCelebrants): ?>
<div class="card" style="background:linear-gradient(135deg,#fbb6ce,#f687b3);color:#fff;">
  <h3 style="color:#fff;">🎂 Celebrating Today (<?= count($todayCelebrants) ?>)</h3>
  <?php foreach ($todayCelebrants as $m): ?>
    <div class="member-card" style="background:rgba(255,255,255,0.18);">
      <?= memberAvatar($m, 44) ?>
      <div class="member-info">
        <div class="name" style="color:#fff;">
          <?= e($m['full_name']) ?>
          <?php if ($m['id'] === $member['id']): ?>
            <span class="badge" style="margin-left:6px;background:#fff;color:#97266d;">
              you
            </span>
          <?php endif; ?>
        </div>
        <div class="meta" style="color:rgba(255,255,255,0.92);">
          Born on a <?= e($myDay) ?>
        </div>
      </div>
      <span class="badge green">🎉</span>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ============================================================
     ALL MEMBERS IN MY GROUP
     ============================================================ -->
<div class="card">
  <h3>👥 All <?= e($myDay) ?> Born Members (<?= count($groupMembers) ?>)</h3>

  <?php if (!$groupMembers): ?>
    <div class="empty">No members in this group yet.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead>
        <tr>
          <th>Member</th>
          <th>Date of Birth</th>
          <th>Phone</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($groupMembers as $m):
          $isMe = $m['id'] === $member['id'];
          $isToday = !empty($m['dob'])
                     && 'date'('m-d', 'strtotime'($m['dob'])) === $today;
        ?>
          <tr style="<?= $isMe ? 'background:#faf5ff;' : ($isToday ? 'background:#f0fff4;' : '') ?>">
            <td>
              <div style="display:flex;align-items:center;gap:10px;">
                <?= memberAvatar($m, 36) ?>
                <div>
                  <strong><?= e($m['full_name']) ?></strong>
                  <?php if ($isMe): ?>
                    <span class="badge" style="margin-left:6px;background:#805ad5;color:#fff;">
                      you
                    </span>
                  <?php elseif ($isToday): ?>
                    <span class="badge green" style="margin-left:6px;">🎂 Today</span>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td>
              <?= !empty($m['dob'])
                  ? 'date'('jS F Y', 'strtotime'($m['dob']))
                  : '—' ?>
            </td>
            <td><?= e($m['phone'] ?: '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<!-- ============================================================
     INFO NOTE
     ============================================================ -->
<div class="card" style="background:#faf5ff;border-left:4px solid #805ad5;">
  <h3 style="color:#553c9a;">ℹ️ About Your Group</h3>
  <p style="font-size:13px;color:#553c9a;line-height:1.7;">
    You are a <strong><?= e($myDay) ?> Born</strong> member. This page shows
    only members in your own Day Born group — as a way for you to know who
    else shares your day and celebrate together.
  </p>
  <p style="font-size:12px;color:#718096;margin-top:8px;">
    Only the Station Office can change your Day Born. Contact them if
    something looks wrong.
  </p>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>