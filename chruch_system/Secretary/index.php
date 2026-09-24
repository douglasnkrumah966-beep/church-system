<?php
require_once __DIR__ . '/_layout.php';

/* ============================================================
   SECRETARY DASHBOARD
   ------------------------------------------------------------
   - Today's summary tiles
   - Quick actions
   - Today's birthdays
   - Recently registered members
   - Birthdays this week
   - Recent sacrament records
   ============================================================ */

/* ---------- Stats ---------- */
$totalMembers  = (int)$pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
$totalSacr     = (int)$pdo->query("SELECT COUNT(*) FROM sacraments")->fetchColumn();

$totalSessions = 0;
try {
    $totalSessions = (int)$pdo->query("SELECT COUNT(*) FROM attendance_sessions")->fetchColumn();
} catch (PDOException $e) {}

/* Members added this month */
$monthStart = date('Y-m-01');
$stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE created_at >= ?");
try {
    $stmt->execute([$monthStart]);
    $newThisMonth = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $newThisMonth = 0;
}

/* ---------- Today's birthdays ---------- */
$stmt = $pdo->prepare("SELECT * FROM members
                       WHERE dob IS NOT NULL AND DATE_FORMAT(dob,'%m-%d') = ?");
$stmt->execute([date('m-d')]);
$birthdays = $stmt->fetchAll();
if (!is_array($birthdays)) $birthdays = [];

/* ---------- Birthdays this week ---------- */
$upcoming = [];
for ($i = 1; $i <= 7; $i++) {
    $key = date('m-d', strtotime("+$i day"));
    $stmt = $pdo->prepare("SELECT id, full_name, photo, day_born, dob
                           FROM members WHERE dob IS NOT NULL
                             AND DATE_FORMAT(dob,'%m-%d') = ?");
    $stmt->execute([$key]);
    foreach ($stmt->fetchAll() as $m) $upcoming[] = $m;
}

/* ---------- Recently registered members ---------- */
$recentMembers = [];
try {
    $rows = $pdo->query("SELECT id, full_name, photo, day_born, phone
                         FROM members ORDER BY id DESC LIMIT 6")->fetchAll();
    $recentMembers = is_array($rows) ? $rows : [];
} catch (PDOException $e) {}

/* ---------- Recent sacraments ---------- */
$recentSacraments = [];
try {
    $rows = $pdo->query("
        SELECT s.*, m.full_name, m.photo
        FROM sacraments s
        LEFT JOIN members m ON m.id = s.member_id
        ORDER BY s.id DESC LIMIT 5
    ")->fetchAll();
    $recentSacraments = is_array($rows) ? $rows : [];
} catch (PDOException $e) {}
?>

<!-- ============================================================
     SUMMARY TILES
     ============================================================ -->
<div class="stat-grid">
  <div class="stat" style="background:linear-gradient(135deg,#3182ce,#2c5282);">
    <div class="num"><?= $totalMembers ?></div>
    <div class="lbl">Total Members</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#805ad5,#553c9a);">
    <div class="num"><?= $totalSacr ?></div>
    <div class="lbl">Sacraments</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#38a169,#276749);">
    <div class="num"><?= $totalSessions ?></div>
    <div class="lbl">Attendance Sessions</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#dd6b20,#9c4221);">
    <div class="num"><?= count($birthdays) ?></div>
    <div class="lbl">Birthdays Today</div>
  </div>
</div>

<div class="stat-grid" style="margin-top:12px;">
  <div class="stat" style="background:linear-gradient(135deg,#319795,#234e52);">
    <div class="num"><?= $newThisMonth ?></div>
    <div class="lbl">New This Month</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#d53f8c,#97266d);">
    <div class="num"><?= count($upcoming) ?></div>
    <div class="lbl">Birthdays This Week</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#6b46c1,#553c9a);">
    <div class="num"><?= count($recentSacraments) ?></div>
    <div class="lbl">Recent Sacraments</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#2c5282,#1a365d);">
    <div class="num"><?= date('M Y') ?></div>
    <div class="lbl">Current Period</div>
  </div>
</div>

<!-- ============================================================
     QUICK ACTIONS
     ============================================================ -->
<div class="card" style="margin-top:16px;">
  <h3>⚡ Quick Actions</h3>
  <div class="grid grid-2">
    <a class="btn-sm btn-primary" style="text-align:center;padding:12px;"
       href="members.php">➕ Register Member</a>
    <a class="btn-sm btn-primary" style="text-align:center;padding:12px;"
       href="sacraments.php">✝️ Record Sacrament</a>
    <a class="btn-sm btn-primary" style="text-align:center;padding:12px;"
       href="attendance.php">📋 Mark Attendance</a>
    <a class="btn-sm btn-primary" style="text-align:center;padding:12px;"
       href="dayborn.php">🎂 Day Born</a>
    <a class="btn-sm btn-success" style="text-align:center;padding:12px;"
       href="wa_groups.php">💬 WhatsApp Groups</a>
    <a class="btn-sm btn-success" style="text-align:center;padding:12px;"
       href="bulk_message.php">✉️ Bulk Message</a>
  </div>
</div>

<!-- ============================================================
     TODAY'S BIRTHDAYS
     ============================================================ -->
<?php if ($birthdays): ?>
<div class="card" style="background:linear-gradient(135deg,#fbb6ce,#f687b3);color:#fff;">
  <h3 style="color:#fff;">🎂 Happy Birthday Today! (<?= count($birthdays) ?>)</h3>
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
      <span class="badge green">🎉 Celebrate</span>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ============================================================
     RECENTLY REGISTERED
     ============================================================ -->
<div class="card">
  <h3>
    🆕 Recently Registered
    <a class="btn-sm btn-ghost" style="float:right;padding:6px 12px;font-size:12px;"
       href="members.php">View All →</a>
  </h3>
  <?php if (!$recentMembers): ?>
    <div class="empty">No members yet.</div>
  <?php else: ?>
    <div style="display:grid;grid-template-columns:1fr;gap:8px;">
      <?php foreach ($recentMembers as $m): ?>
        <div class="member-card">
          <?= memberAvatar($m, 40) ?>
          <div class="member-info">
            <div class="name"><?= e($m['full_name']) ?></div>
            <div class="meta">
              <?= e($m['day_born'] ? $m['day_born'] . ' Born · ' : '') ?>
              <?= e($m['phone'] ?: 'no phone') ?>
            </div>
          </div>
          <a class="btn-sm btn-ghost" style="padding:6px 10px;font-size:12px;"
             href="member_edit.php?id=<?= (int)$m['id'] ?>">✏️</a>
          <a class="btn-sm btn-ghost" style="padding:6px 10px;font-size:12px;"
             href="member_card.php?id=<?= (int)$m['id'] ?>">🪪</a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ============================================================
     RECENT SACRAMENTS
     ============================================================ -->
<?php if ($recentSacraments): ?>
<div class="card">
  <h3>
    ✝️ Recent Sacraments
    <a class="btn-sm btn-ghost" style="float:right;padding:6px 12px;font-size:12px;"
       href="sacraments.php">View All →</a>
  </h3>
  <?php foreach ($recentSacraments as $s):
    $mr = !empty($s['full_name'])
        ? ['full_name' => $s['full_name'], 'photo' => $s['photo']]
        : null;
  ?>
    <div class="member-card">
      <?php if ($mr): ?>
        <?= memberAvatar($mr, 40) ?>
      <?php else: ?>
        <div class="avatar">?</div>
      <?php endif; ?>
      <div class="member-info">
        <div class="name"><?= e($s['full_name'] ?? 'Unknown') ?></div>
        <div class="meta">
          <span class="badge"><?= e($s['type']) ?></span>
          <?= !empty($s['date_received'])
              ? ' · ' . date('jS M Y', strtotime($s['date_received']))
              : '' ?>
          <?= !empty($s['minister']) ? ' · ' . e($s['minister']) : '' ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ============================================================
     BIRTHDAYS THIS WEEK
     ============================================================ -->
<?php if ($upcoming): ?>
<div class="card">
  <h3>📅 Birthdays This Week (<?= count($upcoming) ?>)</h3>
  <?php foreach ($upcoming as $m): ?>
    <div class="member-card">
      <?= memberAvatar($m, 40) ?>
      <div class="member-info">
        <div class="name"><?= e($m['full_name']) ?></div>
        <div class="meta">
          <?= date('l, jS F', strtotime($m['dob'])) ?>
          · <?= e($m['day_born'] ?: '—') ?> Born
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>