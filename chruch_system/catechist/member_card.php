<?php
require_once __DIR__ . '/_layout.php';

$id = (int)get('id');
if (!$id) { flash('❌ Invalid member'); redirect('members.php'); }

$stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
$stmt->execute([$id]);
$member = $stmt->fetch();
if (!$member) { flash('❌ Member not found'); redirect('members.php'); }

$stmt = $pdo->prepare("SELECT type, date_received FROM sacraments
                       WHERE member_id = ? ORDER BY date_received");
$stmt->execute([$id]);
$sacraments = $stmt->fetchAll();

$photo = memberPhoto($member);
if ($photo) $photo = '../' . ltrim($photo, '/');
?>

<a class="btn-sm btn-ghost no-print" style="display:inline-block;margin-bottom:12px;"
   href="members.php">← Back to Members</a>

<div class="id-card">
  <div class="id-card-header">
    <div class="id-cross">✝️</div>
    <div class="id-church"><?= e($settings['church_name'] ?? 'Church') ?></div>
    <div class="id-subtitle">Station Member Card</div>
  </div>

  <div class="id-card-body">
    <div class="id-photo">
      <?php if ($photo): ?>
        <img src="<?= e($photo) ?>" alt="">
      <?php else: ?>
        <div class="id-photo-initial">
          <?= e(strtoupper(substr($member['full_name'], 0, 1))) ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="id-details">
      <div class="id-name"><?= e($member['full_name']) ?></div>

      <div class="id-row">
        <span class="id-label">Member ID</span>
        <span class="id-value"><?= str_pad($member['id'], 5, '0', STR_PAD_LEFT) ?></span>
      </div>
      <div class="id-row">
        <span class="id-label">Gender</span>
        <span class="id-value"><?= e($member['gender'] ?: '—') ?></span>
      </div>
      <div class="id-row">
        <span class="id-label">Date of Birth</span>
        <span class="id-value"><?= e(displayDob($member)) ?></span>
      </div>
      <div class="id-row">
        <span class="id-label">Day Born</span>
        <span class="id-value"><?= e($member['day_born'] ? $member['day_born'] . ' Born' : '—') ?></span>
      </div>
      <div class="id-row">
        <span class="id-label">Baptism</span>
        <span class="id-value"><?= e($member['baptism'] ?: '—') ?></span>
      </div>
      <div class="id-row">
        <span class="id-label">Phone</span>
        <span class="id-value"><?= e($member['phone'] ?: '—') ?></span>
      </div>
    </div>
  </div>

  <?php if ($sacraments): ?>
  <div class="id-sacraments">
    <div class="id-sacraments-title">Sacraments Received</div>
    <div class="id-sacraments-list">
      <?php foreach ($sacraments as $s): ?>
        <span class="id-sacrament-badge">
          <?= e($s['type']) ?>
          <?= $s['date_received'] ? ' · ' . 'date'('M Y', 'strtotime'($s['date_received'])) : '' ?>
        </span>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="id-card-footer">
    <div>Issued: <?= 'date'('jS F Y') ?></div>
    <div>Station Office</div>
  </div>
</div>

<div class="no-print" style="text-align:center;margin-top:20px;">
  <button class="btn-sm btn-primary" style="max-width:220px;margin:0 auto;"
          onclick="window.print()">🖨 Print / Save as PDF</button>
</div>

<style>
.id-card { max-width: 480px; margin: 20px auto; background: #fff;
           border-radius: 18px; overflow: hidden;
           box-shadow: 0 8px 30px rgba(0,0,0,0.12);
           border: 1px solid #e2e8f0; }
.id-card-header { background: linear-gradient(135deg, #9c4221, #dd6b20);
                  color: #fff; padding: 18px; text-align: center; }
.id-cross { font-size: 26px; }
.id-church { font-size: 16px; font-weight: 700; margin-top: 4px; }
.id-subtitle { font-size: 12px; opacity: 0.85; margin-top: 2px; letter-spacing: 1px; }
.id-card-body { display: flex; gap: 16px; padding: 20px; align-items: flex-start; }
.id-photo { flex-shrink: 0; }
.id-photo img, .id-photo-initial {
  width: 100px; height: 100px; border-radius: 12px; object-fit: cover;
  border: 3px solid #e2e8f0; background: #9c4221; color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-size: 40px; font-weight: 700;
}
.id-details { flex: 1; min-width: 0; }
.id-name { font-size: 18px; font-weight: 800; margin-bottom: 10px;
           color: #9c4221; word-break: break-word; }
.id-row { display: flex; justify-content: space-between; gap: 10px;
          padding: 4px 0; border-bottom: 1px dashed #edf2f7; font-size: 12px; }
.id-row:last-child { border-bottom: none; }
.id-label { color: #718096; }
.id-value { font-weight: 600; text-align: right; color: #2d3748; }
.id-sacraments { background: #f7fafc; padding: 12px 20px; border-top: 1px solid #edf2f7; }
.id-sacraments-title { font-size: 11px; font-weight: 700; text-transform: uppercase;
                       color: #718096; margin-bottom: 6px; }
.id-sacraments-list { display: flex; flex-wrap: wrap; gap: 6px; }
.id-sacrament-badge { background: #feebc8; color: #7b341e;
                      padding: 3px 10px; border-radius: 999px;
                      font-size: 11px; font-weight: 600; }
.id-card-footer { display: flex; justify-content: space-between;
                  background: #2d3748; color: #fff; padding: 10px 20px; font-size: 11px; }
@media print {
  header, nav.tabs, footer, .no-print, .flash { display: none !important; }
  body { background: #fff !important; }
  main { padding: 0 !important; }
  .id-card { box-shadow: none; border: 1px solid #cbd5e0;
             margin: 0 auto; page-break-inside: avoid; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>