<?php
require_once __DIR__ . '/_layout.php';

$FUNERAL_TRIGGERS = ['Funeral Contribution','Welfare Contribution','Special Appeal'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $amount   = cleanAmount(post('amount'));
    $category = post('category');
    $memberId = (int)post('member_id');

    if ($amount <= 0) { flash('❌ Enter a valid amount'); redirect('contributions.php'); }
    if (!in_array($category, CONTRIBUTION_TYPES, true)) { flash('❌ Invalid type'); redirect('contributions.php'); }
    if (!$memberId) { flash('❌ Select a member'); redirect('contributions.php'); }

    $member = getMember($pdo, $memberId);
    if (!$member) { flash('❌ Member not found'); redirect('contributions.php'); }

    $beneficiary = $relationship = null;
    $paymentMethod = post('payment_method') ?: null;

    if (in_array($category, $FUNERAL_TRIGGERS, true)) {
        $beneficiary  = post('beneficiary_name');
        $relationship = post('relationship');
        if ($beneficiary === '') { flash('❌ Enter beneficiary name'); redirect('contributions.php'); }
    }

    $note = trim(post('note'));
    if ($beneficiary) {
        $note = 'For: ' . $beneficiary . ($relationship ? ' (' . $relationship . ')' : '')
              . ($note ? ' — ' . $note : '');
    }

    $stmt = $pdo->prepare("INSERT INTO finances
        (member_id, beneficiary_name, relationship, payment_method,
         type, category, amount, date, note, recorded_by)
        VALUES (?,?,?,?,'income',?,?,?,?,?)");
    $stmt->execute([$memberId, $beneficiary, $relationship, $paymentMethod,
                    $category, $amount, post('date') ?: 'date'('Y-m-d'),
                    $note, currentUser()['name']]);

    flash('✅ ' . $category . ' saved for ' . $member['full_name']);
    redirect('contributions.php');
}

if (isset($_GET['delete'])) {
    $ph = implode(',', array_fill(0, count(CONTRIBUTION_TYPES), '?'));
    $stmt = $pdo->prepare("DELETE FROM finances WHERE id=? AND type='income' AND category IN ($ph)");
    $stmt->execute(array_merge([(int)$_GET['delete']], CONTRIBUTION_TYPES));
    flash('🗑 Deleted');
    redirect('contributions.php');
}

$filterType   = get('type');
$filterMember = (int)get('member');

$where  = "f.type='income' AND f.category IN (" . implode(',', array_fill(0, count(CONTRIBUTION_TYPES), '?')) . ")";
$params = CONTRIBUTION_TYPES;
if ($filterType && in_array($filterType, CONTRIBUTION_TYPES, true)) {
    $where .= " AND f.category = ?"; $params[] = $filterType;
}
if ($filterMember) { $where .= " AND f.member_id = ?"; $params[] = $filterMember; }

$stmt = $pdo->prepare("SELECT f.*, m.full_name AS member_name, m.phone AS member_phone, m.photo AS member_photo
                       FROM finances f LEFT JOIN members m ON m.id = f.member_id
                       WHERE $where ORDER BY f.date DESC, f.id DESC");
$stmt->execute($params);
$rows = $stmt->fetchAll();
$total = array_sum(array_column($rows, 'amount'));

$stmt = $pdo->prepare("SELECT category, SUM(amount) total, COUNT(*) entries FROM finances
    WHERE type='income' AND category IN (" . implode(',', array_fill(0, count(CONTRIBUTION_TYPES), '?')) . ")
    GROUP BY category ORDER BY category");
$stmt->execute(CONTRIBUTION_TYPES);
$byType = $stmt->fetchAll();

$selectedMember = $filterMember ? getMember($pdo, $filterMember) : null;
$allMembers = $pdo->query("SELECT id, full_name, phone FROM members ORDER BY full_name")->fetchAll();
?>

<div class="stat-grid">
  <div class="stat"><div class="num"><?= money($total) ?></div>
    <div class="lbl"><?= $selectedMember ? 'For ' . e($selectedMember['full_name']) : 'Total' ?></div></div>
  <div class="stat"><div class="num"><?= count($rows) ?></div><div class="lbl">Entries</div></div>
</div>

<div class="card" style="margin-top:16px;">
  <h3>➕ Record Contribution</h3>
  <form method="post" id="contribForm" autocomplete="off">
    <label>Select Contributor *</label>
    <select name="member_id" id="memberId" required>
      <option value="">— Select —</option>
      <?php foreach ($allMembers as $m): ?>
        <option value="<?= $m['id'] ?>"><?= e($m['full_name']) ?><?= $m['phone'] ? ' ('.e($m['phone']).')' : '' ?></option>
      <?php endforeach; ?>
    </select>

    <div class="grid grid-2" style="margin-top:14px;">
      <div><label>Contribution Type *</label>
        <select name="category" id="contribType" required>
          <option value="">Select type</option>
          <?php foreach (CONTRIBUTION_TYPES as $c): ?>
            <option><?= e($c) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label>Amount (GH₵) *</label>
        <input name="amount" type="number" step="0.01" min="0.01" required></div>
      <div><label>Date *</label>
        <input name="date" type="date" value="<?= 'date'('Y-m-d') ?>" required></div>
      <div><label>Payment Method</label>
        <select name="payment_method">
          <option value="">— Select —</option>
          <option>Cash</option><option>Mobile Money</option>
          <option>Bank Transfer</option><option>Cheque</option>
        </select></div>
    </div>

    <div id="funeralFields" style="display:none;background:#fff5f5;border-left:4px solid #e53e3e;
                                   padding:14px;border-radius:8px;margin-top:14px;">
      <div style="font-weight:700;color:#9b2c2c;margin-bottom:10px;">🕊 In Memory Of</div>
      <div class="grid grid-2">
        <div><label>Contributing For *</label>
          <input name="beneficiary_name" id="beneficiaryName" placeholder="e.g. Late Kwame Mensah"></div>
        <div><label>Relationship *</label>
          <select name="relationship" id="relationshipSel">
            <option value="">— Select —</option>
            <option>Father</option><option>Mother</option><option>Husband</option><option>Wife</option>
            <option>Son</option><option>Daughter</option><option>Brother</option><option>Sister</option>
            <option>Grandfather</option><option>Grandmother</option><option>Uncle</option><option>Aunt</option>
            <option>Cousin</option><option>Nephew</option><option>Niece</option>
            <option>Friend</option><option>Church Member</option><option>Other</option>
          </select></div>
      </div>
    </div>

    <div style="margin-top:14px;"><label>Description (optional)</label>
      <input name="note" placeholder="e.g. Funeral support"></div>

    <button class="btn-primary" name="save" value="1">Save Contribution</button>
  </form>
</div>

<script>
document.addEventListener('change', e => {
  if (e.target && e.target.id === 'contribType') {
    const TRIGGERS = ['Funeral Contribution','Welfare Contribution','Special Appeal'];
    const on = TRIGGERS.includes(e.target.value);
    const block = document.getElementById('funeralFields');
    block.style.display = on ? 'block' : 'none';
    document.getElementById('beneficiaryName').required = on;
    document.getElementById('relationshipSel').required = on;
  }
});
</script>

<div class="card">
  <h3>🔎 Filter</h3>
  <form method="get" class="grid grid-2">
    <div><label>Type</label>
      <select name="type">
        <option value="">All types</option>
        <?php foreach (CONTRIBUTION_TYPES as $c): ?>
          <option <?= $filterType===$c?'selected':'' ?>><?= e($c) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div><label>Member</label>
      <select name="member">
        <option value="">All members</option>
        <?php foreach ($allMembers as $m): ?>
          <option value="<?= $m['id'] ?>" <?= $filterMember==$m['id']?'selected':'' ?>><?= e($m['full_name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <button class="btn-primary" style="grid-column:1/-1;">Apply Filter</button>
  </form>
</div>

<?php if ($byType): ?>
<div class="card">
  <h3>📊 Totals by Type</h3>
  <div class="table-wrap"><table>
    <tr><th>Type</th><th>Entries</th><th style="text-align:right;">Total</th></tr>
    <?php foreach ($byType as $b): ?>
      <tr>
        <td><?= e($b['category']) ?></td>
        <td><?= (int)$b['entries'] ?></td>
        <td style="text-align:right;"><strong><?= money($b['total']) ?></strong></td>
      </tr>
    <?php endforeach; ?>
    <tr style="background:#f7fafc;font-weight:700;">
      <td>Grand Total</td>
      <td><?= array_sum(array_column($byType, 'entries')) ?></td>
      <td style="text-align:right;"><?= money(array_sum(array_column($byType, 'total'))) ?></td>
    </tr>
  </table></div>
</div>
<?php endif; ?>

<div class="card">
  <h3>📋 Contribution Records</h3>
  <?php if (!$rows): ?>
    <div class="empty">No records match.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <tr><th>Date</th><th>Contributor</th><th>Type</th><th>Beneficiary</th>
          <th style="text-align:right;">Amount</th><th></th></tr>
      <?php foreach ($rows as $r):
        $mr = $r['member_name'] ? ['full_name'=>$r['member_name'],'photo'=>$r['member_photo']] : null;
      ?>
        <tr>
          <td><?= e($r['date']) ?></td>
          <td>
            <?php if ($mr): ?>
              <div style="display:flex;align-items:center;gap:8px;">
                <?= memberAvatar($mr, 30) ?>
                <div>
                  <strong style="font-size:13px;"><?= e($mr['full_name']) ?></strong>
                  <?php if ($r['member_phone']): ?>
                    <div style="font-size:11px;color:#718096;"><?= e($r['member_phone']) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            <?php else: ?><span style="color:#a0aec0;">—</span><?php endif; ?>
          </td>
          <td><span class="badge"><?= e($r['category']) ?></span></td>
          <td>
            <?php if (!empty($r['beneficiary_name'])): ?>
              <div style="font-weight:600;color:#9b2c2c;font-size:12px;">
                🕊 <?= e($r['beneficiary_name']) ?>
              </div>
              <?php if ($r['relationship']): ?>
                <div style="font-size:11px;color:#718096;"><?= e($r['relationship']) ?></div>
              <?php endif; ?>
            <?php else: ?><span style="color:#a0aec0;">—</span><?php endif; ?>
          </td>
          <td style="text-align:right;"><strong><?= money($r['amount']) ?></strong></td>
          <td>
            <a class="btn-sm btn-ghost" style="padding:6px 10px;font-size:12px;"
               href="../receipt.php?id=<?= $r['id'] ?>" title="Receipt">🧾</a>
            <a class="btn-sm btn-danger" style="padding:6px 10px;font-size:12px;"
               data-confirm="Delete?" href="?delete=<?= $r['id'] ?>">🗑</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </table></div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>