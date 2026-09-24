<?php
require_once __DIR__ . '/_layout.php';

$member = myMember($pdo);
$sacraments = [];

if ($member) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM sacraments
            WHERE member_id = ?
            ORDER BY date_received IS NULL, date_received DESC");
        $stmt->execute([$member['id']]);
        $sacraments = $stmt->fetchAll();
        if (!is_array($sacraments)) $sacraments = [];
    } catch (PDOException $e) { $sacraments = []; }
}
?>

<div class="card">
  <h3>✝️ My Sacraments</h3>
  <?php if (!$member): ?>
    <div class="empty">No member record linked to your account.</div>
  <?php elseif (!$sacraments): ?>
    <div class="empty">No sacraments recorded yet.</div>
  <?php else: foreach ($sacraments as $s): ?>
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
        </div>
        <?php if (!empty($s['minister']) || !empty($s['place'])): ?>
          <div class="meta">
            <?= !empty($s['minister']) ? e($s['minister']) : '' ?>
            <?= !empty($s['place']) ? ' · ' . e($s['place']) : '' ?>
          </div>
        <?php endif; ?>
      </div>
      <?php if (!empty($s['certificate_no'])): ?>
        <span class="badge"><?= e($s['certificate_no']) ?></span>
      <?php endif; ?>
    </div>
  <?php endforeach; endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>