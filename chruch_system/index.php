<?php
require_once 'includes/auth.php';
requireLogin();

$u = currentUser();

/* ============================================================
   MEMBER DASHBOARD (personal)
   ============================================================ */
if ($u['role'] === 'member') {

    $member = myMember($pdo);

    // Sacraments for this member
    $sacraments = [];
    if ($member) {
        $stmt = $pdo->prepare("SELECT * FROM sacraments
            WHERE member_id = ? ORDER BY date_received IS NULL, date_received");
        $stmt->execute([$member['id']]);
        $sacraments = $stmt->fetchAll();
    }

    // My contributions this year
    $yearStart = 'date'('Y-01-01');
    $myContributions = [];
    $myTotal = 0;
    $myLast  = null;
    if ($member) {
        $stmt = $pdo->prepare("SELECT * FROM finances
            WHERE member_id = ?
              AND type='income'
              AND category IN (" . implode(',', array_fill(0, count(CONTRIBUTION_TYPES), '?')) . ")
              AND date >= ?
            ORDER BY date DESC");
        $stmt->execute(array_merge([$member['id']], CONTRIBUTION_TYPES, [$yearStart]));
        $myContributions = $stmt->fetchAll();
        foreach ($myContributions as $c) $myTotal += (float)$c['amount'];
        $myLast = $myContributions[0] ?? null;
    }

    // Days until my next birthday
    $daysToBday = null;
    $nextBday   = null;
    if ($member && !empty($member['dob'])) {
        $d = new DateTime($member['dob']);
        $t = new DateTime();
        $d->setDate($t->format('Y'), $d->format('m'), $d->format('d'));
        if ($d < $t) $d->modify('+1 year');
        $daysToBday = (int)$t->diff($d)->format('%a');
        $nextBday   = $d;
    }

    // Upcoming birthdays this week
    $upcoming = [];
    for ($i = 1; $i <= 7; $i++) {
        $key = 'date'('m-d', 'strtotime'("+$i day"));
        $stmt = $pdo->prepare("SELECT id, full_name, photo, day_born, dob
            FROM members
            WHERE dob IS NOT NULL AND DATE_FORMAT(dob,'%m-%d') = ?
              AND id <> ?
            LIMIT 5");
        $stmt->execute([$key, $member['id'] ?? 0]);
        foreach ($stmt->fetchAll() as $m) $upcoming[] = $m;
    }

    include 'includes/header.php';
    ?>

    <!-- PROFILE CARD -->
    <div class="card">
      <?php if ($member): ?>
        <div style="display:flex;align-items:center;gap:16px;">
          <?= memberAvatar($member, 72) ?>
          <div style="flex:1;min-width:0;">
            <div style="font-size:20px;font-weight:800;color:#2c5282;">
              <?= e($member['full_name']) ?>
            </div>
            <div style="font-size:13px;color:#718096;margin-top:2px;">
              <?= e($member['day_born'] ? $member['day_born'] . ' Born' : '—') ?>
              <?= $member['dob'] ? ' · ' . 'date'('jS F Y', 'strtotime'($member['dob'])) : '' ?>
            </div>
            <div style="font-size:13px;color:#718096;">
              <?= $member['phone'] ? '📞 ' . e($member['phone']) : '' ?>
            </div>
            <div style="font-size:13px;color:#718096;">
              <?= $member['baptism'] ? '✝️ ' . e($member['baptism']) : '' ?>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="empty">
          No member record linked to your account.<br>
          Please contact the parish office.
        </div>
      <?php endif; ?>
    </div>

    <?php if ($member && $daysToBday !== null): ?>
    <!-- BIRTHDAY COUNTDOWN -->
    <div class="card" style="background:linear-gradient(135deg,#fbb6ce,#f687b3);color:#fff;">
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

    <!-- MY SACRAMENTS -->
    <div class="card">
      <h3>✝️ My Sacraments</h3>
      <?php if (!$sacraments): ?>
        <div class="empty">No sacraments recorded yet.</div>
      <?php else: foreach ($sacraments as $s): ?>
        <div class="member-card">
          <div class="avatar" style="background:#805ad5;">
            <?= e(strtoupper(substr($s['type'],0,1))) ?>
          </div>
          <div class="member-info">
            <div class="name"><?= e($s['type']) ?></div>
            <div class="meta">
              <?= $s['date_received'] ? 'date'('jS F Y', 'strtotime'($s['date_received'])) : 'date not recorded' ?>
              <?= $s['minister'] ? ' · ' . e($s['minister']) : '' ?>
              <?= $s['place'] ? ' · ' . e($s['place']) : '' ?>
            </div>
          </div>
          <?php if ($s['certificate_no']): ?>
            <span class="badge"><?= e($s['certificate_no']) ?></span>
          <?php endif; ?>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- MY GIVING -->
    <div class="card">
      <h3>💰 My Contributions (<?= 'date'('Y') ?>)</h3>

      <div class="stat-grid" style="margin-bottom:16px;">
        <div class="stat" style="background:linear-gradient(135deg,#38a169,#276749);">
          <div class="num"><?= money($myTotal) ?></div>
          <div class="lbl">Total Given This Year</div>
        </div>
        <div class="stat" style="background:linear-gradient(135deg,#3182ce,#2c5282);">
          <div class="num"><?= count($myContributions) ?></div>
          <div class="lbl">Contributions</div>
        </div>
      </div>

      <?php if ($myLast): ?>
        <div style="background:#f7fafc;border-radius:10px;padding:12px;margin-bottom:12px;">
          <div style="font-size:12px;color:#718096;font-weight:600;text-transform:uppercase;">
            Last Contribution
          </div>
          <div style="font-weight:600;margin-top:4px;">
            <?= e($myLast['category']) ?> — <?= money($myLast['amount']) ?>
          </div>
          <div style="font-size:12px;color:#718096;">
            <?= 'date'('jS F Y', 'strtotime'($myLast['date'])) ?>
            <?= $myLast['beneficiary_name'] ? ' · For ' . e($myLast['beneficiary_name']) : '' ?>
          </div>
        </div>
      <?php endif; ?>

      <a class="btn-sm btn-ghost" href="my_giving.php">View All My Contributions →</a>
    </div>

    <!-- UPCOMING BIRTHDAYS -->
    <?php if ($upcoming): ?>
    <div class="card">
      <h3>🎉 Celebrating This Week</h3>
      <?php foreach ($upcoming as $m): ?>
        <div class="member-card">
          <?= memberAvatar($m, 40) ?>
          <div class="member-info">
            <div class="name"><?= e($m['full_name']) ?></div>
            <div class="meta">
              <?= 'date'('l, jS F', 'strtotime'($m['dob'])) ?>
              · <?= e($m['day_born']) ?> Born
            </div>
          </div>
          <span class="badge green">🎂</span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php
    include 'includes/footer.php';
    exit;
}

/* ============================================================
   ADMIN / SECRETARY / FINANCE / CATECHIST DASHBOARD (unchanged)
   ============================================================ */
$totalMembers = $pdo->query("SELECT COUNT(*) c FROM members")->fetch()['c'];
$totalSacr    = $pdo->query("SELECT COUNT(*) c FROM sacraments")->fetch()['c'];
$income       = $pdo->query("SELECT COALESCE(SUM(amount),0) s FROM finances WHERE type='income'")->fetch()['s'];
$expense      = $pdo->query("SELECT COALESCE(SUM(amount),0) s FROM finances WHERE type='expense'")->fetch()['s'];
$balance      = $income - $expense;
$showFinance  = in_array($u['role'], ['admin','finance']);

$stmt = $pdo->prepare("SELECT * FROM members WHERE dob IS NOT NULL AND DATE_FORMAT(dob,'%m-%d')=?");
$stmt->execute(['date'('m-d')]);
$birthdays = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="card">
  <h3>Welcome, <?= e($u['name']) ?> 👋</h3>
  <p style="color:#718096;font-size:14px;"><?= 'date'('l, jS F Y') ?></p>
</div>

<div class="stat-grid">
  <div class="stat"><div class="num"><?= $totalMembers ?></div><div class="lbl">Members</div></div>
  <div class="stat"><div class="num"><?= $totalSacr ?></div><div class="lbl">Sacraments</div></div>
  <?php if ($showFinance): ?>
    <div class="stat"><div class="num"><?= money($balance) ?></div><div class="lbl">Balance</div></div>
  <?php endif; ?>
  <div class="stat"><div class="num"><?= count($birthdays) ?></div><div class="lbl">Birthdays Today</div></div>
</div>

<?php if ($birthdays): ?>
<div class="card" style="margin-top:16px;">
  <h3>🎂 Happy Birthday Today!</h3>
  <?php foreach ($birthdays as $m): ?>
    <div class="member-card">
      <?= memberAvatar($m, 44) ?>
      <div class="member-info">
        <div class="name"><?= e($m['full_name']) ?></div>
        <div class="meta">Born on a <?= e($m['day_born']) ?> · <?= e($m['phone']) ?></div>
      </div>
      <span class="badge green">🎉 Celebrate</span>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>