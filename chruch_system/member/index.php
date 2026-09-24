<?php
/* ============================================================
   MEMBER DASHBOARD
   ------------------------------------------------------------
   Validation and redirects MUST happen before including
   _layout.php (which prints HTML).
   ============================================================ */

require_once __DIR__ . '/../includes/auth.php';
requireRole(['member']);

/* Redirect non-members away first (no output yet) */
$u = currentUser();
if ($u['role'] !== 'member') {
    redirect('../index.php');
}

/* Fetch member record */
$member = myMember($pdo);

/* If no member record, redirect with a message BEFORE any HTML */
if (!$member) {
    flash('⚠️ Your account is not linked to a member record. Please contact the Station Office.');
    redirect('my_profile.php'); // or any safe fallback in /member/
}

/* ============================================================
   NOW we can include the layout (which prints HTML)
   ============================================================ */
require_once __DIR__ . '/_layout.php';

/* ---------- Sacraments ---------- */
$sacraments = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM sacraments
        WHERE member_id = ?
        ORDER BY date_received IS NULL, date_received DESC");
    $stmt->execute([$member['id']]);
    $rows = $stmt->fetchAll();
    $sacraments = is_array($rows) ? $rows : [];
} catch (PDOException $e) {
    $sacraments = [];
}

/* ---------- Contributions this year ---------- */
$contributions = [];
$myTotal = 0;
$yearStart = 'date'('Y-01-01');
try {
    $ph = implode(',', array_fill(0, count(CONTRIBUTION_TYPES), '?'));
    $stmt = $pdo->prepare("SELECT * FROM finances
        WHERE member_id = ? AND type = 'income'
          AND category IN ($ph)
          AND date >= ?
        ORDER BY date DESC");
    $stmt->execute(array_merge([$member['id']], CONTRIBUTION_TYPES, [$yearStart]));
    $rows = $stmt->fetchAll();
    $contributions = is_array($rows) ? $rows : [];
    foreach ($contributions as $c) $myTotal += (float)$c['amount'];
} catch (PDOException $e) {
    $contributions = [];
}

/* ---------- Birthday countdown ---------- */
$daysToBday = null;
$nextBday   = null;
if (!empty($member['dob'])) {
    try {
        $d = new DateTime($member['dob']);
        $t = new DateTime();
        $d->setDate((int)$t->format('Y'), (int)$d->format('m'), (int)$d->format('d'));
        if ($d < $t) $d->modify('+1 year');
        $daysToBday = (int)$t->diff($d)->format('%a');
        $nextBday = $d;
    } catch (Exception $e) {
        $daysToBday = null;
        $nextBday = null;
    }
}

/* ---------- Upcoming birthdays this week ---------- */
$upcoming = [];
for ($i = 1; $i <= 7; $i++) {
    $key = 'date'('m-d', 'strtotime'("+$i day"));
    try {
        $stmt = $pdo->prepare("SELECT id, full_name, photo, day_born, dob
            FROM members
            WHERE dob IS NOT NULL AND DATE_FORMAT(dob,'%m-%d') = ?
              AND id <> ?
            LIMIT 5");
        $stmt->execute([$key, (int)$member['id']]);
        foreach ($stmt->fetchAll() as $m) $upcoming[] = $m;
    } catch (PDOException $e) {}
}
?>

<!-- ============================================================
     PROFILE CARD
     ============================================================ -->
<div class="card">
  <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
    <?= memberAvatar($member, 80) ?>
    <div style="flex:1;min-width:0;">
      <div style="font-size:20px;font-weight:800;color:#553c9a;">
        <?= e($member['full_name']) ?>
      </div>
      <div style="font-size:13px;color:#718096;margin-top:4px;">
        <?= e($member['day_born'] ? $member['day_born'] . ' Born' : '—') ?>
        <?= !empty($member['dob']) ? ' · ' . 'date'('jS F Y', 'strtotime'($member['dob'])) : '' ?>
      </div>
      <div style="font-size:13px;color:#718096;">
        <?= $member['phone'] ? '📞 ' . e($member['phone']) : '' ?>
      </div>
      <div style="font-size:13px;color:#718096;">
        <?= $member['baptism'] ? '✝️ ' . e($member['baptism']) : '' ?>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================
     BIRTHDAY COUNTDOWN
     ============================================================ -->
<?php if ($daysToBday !== null && $nextBday): ?>
  <div class="card" style="background:linear-gradient(135deg,#d6bcfa,#805ad5);color:#fff;">
    <h3 style="color:#fff;">🎂 My Birthday</h3>
    <div style="font-size:16px;font-weight:600;">
      <?= $nextBday->format('l, jS F') ?>
    </div>
    <div style="font-size:13px;opacity:0.95;margin-top:4px;">
      <?php if ($daysToBday === 0): ?>
        🎉 Today is your birthday — Happy Birthday!
      <?php else: ?>
        In <?= $daysToBday ?> day<?= $daysToBday === 1 ? '' : 's' ?>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<!-- ============================================================
     SUMMARY TILES
     ============================================================ -->
<div class="stat-grid">
  <div class="stat" style="background:linear-gradient(135deg,#805ad5,#553c9a);">
    <div class="num"><?= count($sacraments) ?></div>
    <div class="lbl">My Sacraments</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#38a169,#276749);">
    <div class="num"><?= money($myTotal) ?></div>
    <div class="lbl">My Giving (<?= 'date'('Y') ?>)</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#3182ce,#2c5282);">
    <div class="num"><?= count($contributions) ?></div>
    <div class="lbl">Contributions</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#dd6b20,#9c4221);">
    <div class="num"><?= count($upcoming) ?></div>
    <div class="lbl">Others Celebrating</div>
  </div>
</div>

<!-- ============================================================
     MY SACRAMENTS
     ============================================================ -->
<div class="card">
  <h3>
    ✝️ My Sacraments
    <a class="btn-sm btn-ghost" style="float:right;padding:6px 12px;font-size:12px;"
       href="my_sacraments.php">View All →</a>
  </h3>
  <?php if (!$sacraments): ?>
    <div class="empty">No sacraments recorded yet.</div>
  <?php else: foreach (array_slice($sacraments, 0, 4) as $s): ?>
    <div class="member-card">
      <div class="avatar" style="background:#805ad5;">
        <?= e(strtoupper(substr($s['type'] ?? '?', 0, 1))) ?>
      </div>
      <div class="member-info">
        <div class="name"><?= e($s['type'] ?? '') ?></div>
        <div class="meta">
          <?= !empty($s['date_received'])
              ? 'date'('jS F Y', 'strtotime'($s['date_received']))
              : 'date not recorded' ?>
          <?= !empty($s['minister']) ? ' · ' . e($s['minister']) : '' ?>
        </div>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<!-- ============================================================
     MY GIVING
     ============================================================ -->
<div class="card">
  <h3>
    💰 My Giving (<?= 'date'('Y') ?>)
    <a class="btn-sm btn-ghost" style="float:right;padding:6px 12px;font-size:12px;"
       href="my_giving.php">View All →</a>
  </h3>

  <div style="background:#f7fafc;padding:14px;border-radius:10px;margin-bottom:12px;">
    <div style="font-size:11px;color:#718096;text-transform:uppercase;font-weight:600;">
      Total Given This Year
    </div>
    <div style="font-size:24px;font-weight:800;color:#276749;margin-top:4px;">
      <?= money($myTotal) ?>
    </div>
  </div>

  <?php if (!$contributions): ?>
    <div class="empty">No contributions recorded this year.</div>
  <?php else:
    $last = $contributions[0]; ?>
    <div style="background:#f7fafc;border-radius:10px;padding:12px;">
      <div style="font-size:12px;color:#718096;font-weight:600;text-transform:uppercase;">
        Last Contribution
      </div>
      <div style="font-weight:600;margin-top:4px;">
        <?= e($last['category'] ?? '') ?> — <?= money($last['amount'] ?? 0) ?>
      </div>
      <div style="font-size:12px;color:#718096;">
        <?= !empty($last['date']) ? 'date'('jS F Y', 'strtotime'($last['date'])) : '' ?>
        <?= !empty($last['beneficiary_name'])
            ? ' · For ' . e($last['beneficiary_name'])
            : '' ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- ============================================================
     UPCOMING BIRTHDAYS
     ============================================================ -->
<?php if ($upcoming): ?>
  <div class="card">
    <h3>🎉 Others Celebrating This Week</h3>
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
        <span class="badge">🎂</span>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>