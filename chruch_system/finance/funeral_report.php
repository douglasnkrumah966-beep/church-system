<?php
require_once __DIR__ . '/_layout.php';

$beneficiary = trim(get('beneficiary'));
$from        = get('from') ?: '';
$to          = get('to')   ?: '';

$allBeneficiaries = $pdo->query("
    SELECT DISTINCT beneficiary_name FROM finances
    WHERE beneficiary_name IS NOT NULL AND beneficiary_name <> ''
    ORDER BY beneficiary_name")->fetchAll();

$where  = "f.category = 'Funeral Contribution' AND f.beneficiary_name IS NOT NULL AND f.beneficiary_name <> ''";
$params = [];
if ($beneficiary !== '') { $where .= " AND f.beneficiary_name LIKE ?"; $params[] = "%$beneficiary%"; }
if ($from !== '') { $where .= " AND f.date >= ?"; $params[] = $from; }
if ($to !== '')   { $where .= " AND f.date <= ?"; $params[] = $to; }

$stmt = $pdo->prepare("SELECT f.*, m.full_name AS member_name, m.phone AS member_phone, m.photo AS member_photo
                       FROM finances f LEFT JOIN members m ON m.id = f.member_id
                       WHERE $where ORDER BY f.beneficiary_name, f.date ASC, f.id ASC");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$grouped = [];
foreach ($rows as $r) {
    $key = $r['beneficiary_name'];
    if (!isset($grouped[$key])) {
        $grouped[$key] = ['beneficiary' => $key, 'relationship' => $r['relationship'] ?? '',
                         'contributors' => [], 'total' => 0];
    }
    $grouped[$key]['contributors'][] = $r;
    $grouped[$key]['total'] += (float)$r['amount'];
    if (!$grouped[$key]['relationship'] && !empty($r['relationship'])) {
        $grouped[$key]['relationship'] = $r['relationship'];
    }
}
$grandTotal = array_sum(array_column($rows, 'amount'));

function ghPhone(?string $p): string {
    $p = preg_replace('/[^0-9]/', '', $p ?? '');
    if ($p === '') return '';
    if (strpos($p, '0') === 0)       return '233' . substr($p, 1);
    if (strpos($p, '233') !== 0)     return '233' . $p;
    return $p;
}
?>

<a class="btn-sm btn-ghost no-print" style="display:inline-block;margin-bottom:12px;"
   href="index.php">← Finance Dashboard</a>

<div class="card no-print">
  <h3>🔎 Search Funeral Contributions</h3>
  <form method="get">
    <div class="grid grid-2">
      <div style="grid-column:1/-1;">
        <label>Deceased / Beneficiary Name</label>
        <input name="beneficiary" list="beneficiaryList" value="<?= e($beneficiary) ?>"
               placeholder="e.g. Late Kwame Mensah">
        <datalist id="beneficiaryList">
          <?php foreach ($allBeneficiaries as $b): ?>
            <option value="<?= e($b['beneficiary_name']) ?>">
          <?php endforeach; ?>
        </datalist>
      </div>
      <div><label>From (optional)</label>
        <input name="from" type="date" value="<?= e($from) ?>"></div>
      <div><label>To (optional)</label>
        <input name="to" type="date" value="<?= e($to) ?>"></div>
    </div>
    <div class="grid grid-2" style="margin-top:12px;">
      <button class="btn-primary" style="margin-top:0;">🔎 Generate</button>
      <a class="btn-sm btn-ghost" style="text-align:center;padding:12px;"
         href="funeral_report.php">↺ Reset</a>
    </div>
  </form>
</div>

<div class="stat-grid">
  <div class="stat" style="background:linear-gradient(135deg,#2c5282,#1a365d);">
    <div class="num"><?= count($grouped) ?></div><div class="lbl">Beneficiaries</div></div>
  <div class="stat" style="background:linear-gradient(135deg,#6b46c1,#553c9a);">
    <div class="num"><?= count($rows) ?></div><div class="lbl">Contributions</div></div>
  <div class="stat" style="background:linear-gradient(135deg,#38a169,#276749);">
    <div class="num"><?= money($grandTotal) ?></div><div class="lbl">Total</div></div>
  <div class="stat" style="background:linear-gradient(135deg,#dd6b20,#9c4221);">
    <div class="num"><?= $rows ? money($grandTotal / count($rows)) : money(0) ?></div>
    <div class="lbl">Average</div></div>
</div>

<?php if (!$grouped): ?>
  <div class="card"><div class="empty">No funeral contributions found.</div></div>
<?php else: foreach ($grouped as $g): ?>
  <div class="card" style="margin-top:16px;">
    <div style="display:flex;align-items:center;gap:14px;
                background:linear-gradient(135deg,#2c5282,#1a365d);
                color:#fff;padding:16px;border-radius:12px;">
      <div style="font-size:32px;">🕊</div>
      <div>
        <div style="font-size:20px;font-weight:800;"><?= e($g['beneficiary']) ?></div>
        <?php if ($g['relationship']): ?>
          <div style="font-size:13px;opacity:0.9;">Relationship: <?= e($g['relationship']) ?></div>
        <?php endif; ?>
        <div style="font-size:13px;opacity:0.9;">
          <?= count($g['contributors']) ?> contributors · Total: <strong><?= money($g['total']) ?></strong>
        </div>
      </div>
    </div>

    <div class="table-wrap" style="margin-top:14px;"><table>
      <tr><th>#</th><th>Date</th><th>Contributor</th><th>Phone</th>
          <th>Payment</th><th style="text-align:right;">Amount</th><th class="no-print">Thank</th></tr>
      <?php foreach ($g['contributors'] as $i => $c):
        $mr = $c['member_name'] ? ['full_name'=>$c['member_name'],'photo'=>$c['member_photo']] : null;
        $phone = ghPhone($c['member_phone'] ?? '');
        $waMsg = rawurlencode("Dear " . ($c['member_name'] ?? 'friend') . ",\n\n" .
            "Thank you for your kind funeral contribution of " . money($c['amount']) .
            " in memory of " . $g['beneficiary'] . ".\n\nMay God bless you.\n\n— " .
            ($settings['church_name'] ?? 'Station'));
      ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= 'date'('d/m/y', 'strtotime'($c['date'])) ?></td>
          <td>
            <?php if ($mr): ?>
              <div style="display:flex;align-items:center;gap:8px;">
                <?= memberAvatar($mr, 30) ?>
                <strong style="font-size:13px;"><?= e($mr['full_name']) ?></strong>
              </div>
            <?php else: ?><span style="color:#a0aec0;">—</span><?php endif; ?>
          </td>
          <td><?= e($c['member_phone'] ?: '—') ?></td>
          <td><?= e($c['payment_method'] ?: '—') ?></td>
          <td style="text-align:right;"><strong><?= money($c['amount']) ?></strong></td>
          <td class="no-print">
            <?php if ($phone): ?>
              <a class="btn-sm btn-success" style="padding:6px 10px;font-size:12px;"
                 target="_blank" rel="noopener"
                 href="https://wa.me/<?= e($phone) ?>?text=<?= $waMsg ?>">💬</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <tr style="background:#f7fafc;font-weight:700;">
        <td colspan="5" style="text-align:right;">Total for <?= e($g['beneficiary']) ?></td>
        <td style="text-align:right;"><?= money($g['total']) ?></td>
        <td class="no-print"></td>
      </tr>
    </table></div>

    <div class="no-print" style="margin-top:12px;">
      <button class="btn-sm btn-primary" onclick="window.print()">🖨 Print Report</button>
    </div>
  </div>
<?php endforeach; endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>