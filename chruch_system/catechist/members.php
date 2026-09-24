<?php
require_once __DIR__ . '/_layout.php';

/* Search */
$search = get('q');
if ($search !== '') {
    $stmt = $pdo->prepare("SELECT * FROM members
        WHERE full_name LIKE ? OR phone LIKE ?
        ORDER BY full_name");
    $stmt->execute(["%$search%", "%$search%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM members ORDER BY full_name");
}
$members = $stmt->fetchAll();
if (!is_array($members)) $members = [];
?>

<div class="card">
  <h3>👥 All Members (<?= count($members) ?>)</h3>
  <p style="color:#718096;font-size:13px;margin-bottom:12px;">
    📖 View only — the Station Office manages member registration.
  </p>

  <form method="get" class="search-box">
    <input name="q" value="<?= e($search) ?>" placeholder="🔍 Search name or phone...">
  </form>

  <?php if (!$members): ?>
    <div class="empty">No members found.</div>
  <?php else: ?>
    <div class="member-grid">
      <?php foreach ($members as $m): ?>
        <div class="member-tile">
          <div class="member-tile-photo"><?= memberAvatar($m, 64) ?></div>
          <div class="member-tile-body">
            <div class="member-tile-name"><?= e($m['full_name']) ?></div>
            <div class="member-tile-meta">
              <?= e($m['gender'] ?: '—') ?>
              <?= $m['day_born'] ? ' · ' . e($m['day_born']) . ' Born' : '' ?>
            </div>
            <div class="member-tile-meta">🎂 <?= e(displayDob($m)) ?></div>
            <div class="member-tile-meta">📞 <?= e($m['phone'] ?: 'no phone') ?></div>
            <div class="member-tile-meta">✝️ <?= e($m['baptism'] ?: '—') ?></div>
          </div>
          <div class="member-tile-actions">
            <a class="btn-sm btn-ghost" href="member_card.php?id=<?= (int)$m['id'] ?>"
               title="View Card">🪪</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<style>
.member-grid { display: grid; grid-template-columns: 1fr; gap: 12px; }
@media (min-width: 600px) { .member-grid { grid-template-columns: repeat(2, 1fr); } }
@media (min-width: 900px) { .member-grid { grid-template-columns: repeat(3, 1fr); } }
.member-tile {
  position: relative; display: flex; gap: 12px; padding: 12px;
  background: #f7fafc; border-radius: 14px; border: 1px solid #edf2f7;
}
.member-tile-photo { flex-shrink: 0; }
.member-tile-body { flex: 1; min-width: 0; }
.member-tile-name { font-weight: 700; font-size: 15px; margin-bottom: 4px; word-break: break-word; }
.member-tile-meta { font-size: 12px; color: #718096; line-height: 1.5; }
.member-tile-actions {
  position: absolute; top: 8px; right: 8px;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>