<?php
require_once __DIR__ . '/_layout.php';

$member = myMember($pdo);
$contributions = [];
$total = 0;

if ($member) {
    try {
        $ph = implode(',', array_fill(0, count(CONTRIBUTION_TYPES), '?'));
        $stmt = $pdo->prepare("SELECT * FROM finances
            WHERE member_id = ?
              AND type = 'income'
              AND category IN ($ph)
            ORDER BY date DESC");
        $stmt->execute(array_merge([$member['id']], CONTRIBUTION_TYPES));
        $contributions = $stmt->fetchAll();
        if (!is_array($contributions)) $contributions = [];
        foreach ($contributions as $c) $total += (float)$c['amount'];
    } catch (PDOException $e) { $contributions = []; }
}
?>

<div class="stat-grid">
  <div class="stat" style="background:linear-gradient(135deg,#38a169,#276749);">
    <div class="num"><?= money($total) ?></div>
    <div class="lbl">Total Given (all time)</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#3182ce,#2c5282);">
    <div class="num"><?= count($contributions) ?></div>
    <div class="lbl">Contributions</div>
  </div>
</div>

<div class="card" style="margin-top:16px;">
  <h3>💰 My Contribution History</h3>
  <?php if (!$member): ?>
    <div class="empty">No member record linked to your account.</div>
  <?php elseif (!$contributions): ?>
    <div class="empty">No contributions recorded yet.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <tr>
        <th>Date</th>
        <th>Type</th>
        <th>For</th>
        <th style="text-align:right;">Amount</th>
      </tr>
      <?php foreach ($contributions as $c): ?>
        <tr>
          <td><?= !empty($c['date']) ? 'date'('jS M Y', 'strtotime'($c['date'])) : '—' ?></td>
          <td><span class="badge"><?= e($c['category']) ?></span></td>
          <td>
            <?php if (!empty($c['beneficiary_name'])): ?>
              🕊 <?= e($c['beneficiary_name']) ?>
              <?= !empty($c['relationship']) ? ' (' . e($c['relationship']) . ')' : '' ?>
            <?php else: ?>
              <span style="color:#a0aec0;">—</span>
            <?php endif; ?>
          </td>
          <td style="text-align:right;"><strong><?= money($c['amount']) ?></strong></td>
        </tr>
      <?php endforeach; ?>
      <tr style="background:#f7fafc;font-weight:700;">
        <td colspan="3" style="text-align:right;">Total</td>
        <td style="text-align:right;"><?= money($total) ?></td>
      </tr>
    </table></div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>