<?php
require_once __DIR__ . '/_layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $amount   = cleanAmount(post('amount'));
    $category = post('category');

    if ($amount <= 0) { flash('❌ Enter valid amount'); redirect('expenses.php'); }
    if (!in_array($category, EXPENSE_TYPES, true)) { flash('❌ Invalid type'); redirect('expenses.php'); }

    $stmt = $pdo->prepare("INSERT INTO finances
        (type, category, amount, date, note, recorded_by)
        VALUES ('expense', ?, ?, ?, ?, ?)");
    $stmt->execute([$category, $amount, post('date') ?: 'date'('Y-m-d'),
                    post('note'), currentUser()['name']]);
    flash('✅ Expense saved');
    redirect('expenses.php');
}

if (isset($_GET['delete'])) {
    $ph = implode(',', array_fill(0, count(EXPENSE_TYPES), '?'));
    $stmt = $pdo->prepare("DELETE FROM finances WHERE id=? AND type='expense' AND category IN ($ph)");
    $stmt->execute(array_merge([(int)$_GET['delete']], EXPENSE_TYPES));
    flash('🗑 Deleted');
    redirect('expenses.php');
}

$filter = get('type');
$where = "type='expense' AND category IN (" . implode(',', array_fill(0, count(EXPENSE_TYPES), '?')) . ")";
$params = EXPENSE_TYPES;
if ($filter && in_array($filter, EXPENSE_TYPES, true)) {
    $where .= " AND category = ?"; $params[] = $filter;
}

$stmt = $pdo->prepare("SELECT * FROM finances WHERE $where ORDER BY date DESC, id DESC");
$stmt->execute($params);
$rows = $stmt->fetchAll();
$total = array_sum(array_column($rows, 'amount'));

$stmt = $pdo->prepare("SELECT category, SUM(amount) total, COUNT(*) entries FROM finances
    WHERE type='expense' AND category IN (" . implode(',', array_fill(0, count(EXPENSE_TYPES), '?')) . ")
    GROUP BY category");
$stmt->execute(EXPENSE_TYPES);
$byType = $stmt->fetchAll();
usort($byType, fn($a,$b) => array_search($a['category'], EXPENSE_TYPES) <=> array_search($b['category'], EXPENSE_TYPES));
?>

<div class="stat-grid">
  <div class="stat"><div class="num"><?= money($total) ?></div>
    <div class="lbl"><?= $filter ? 'Total ('.e($filter).')' : 'Total Expenses' ?></div></div>
  <div class="stat"><div class="num"><?= count($rows) ?></div><div class="lbl">Entries</div></div>
</div>

<div class="card" style="margin-top:16px;">
  <h3>➕ Record Expense</h3>
  <form method="post">
    <div class="grid grid-2">
      <div><label>Expense Type</label>
        <select name="category" required>
          <option value="">Select type</option>
          <?php foreach (EXPENSE_TYPES as $c): ?>
            <option <?= $filter===$c?'selected':'' ?>><?= e($c) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label>Amount (GH₵)</label>
        <input name="amount" type="number" step="0.01" min="0.01" required></div>
      <div><label>Date</label>
        <input name="date" type="date" value="<?= 'date'('Y-m-d') ?>" required></div>
      <div><label>Note (optional)</label>
        <input name="note" placeholder="e.g. ECG bill for November"></div>
    </div>
    <button class="btn-primary" name="save" value="1">Save Expense</button>
  </form>
</div>

<div class="card">
  <h3>🔎 Filter</h3>
  <div class="grid" style="grid-template-columns:1fr;">
    <a class="btn-sm <?= !$filter ? 'btn-primary' : 'btn-ghost' ?>"
       style="text-align:center;<?= !$filter ? 'margin-top:0' : '' ?>"
       href="expenses.php">All Types</a>
    <?php foreach (EXPENSE_TYPES as $c): ?>
      <a class="btn-sm <?= $filter === $c ? 'btn-primary' : 'btn-ghost' ?>"
         style="text-align:center;<?= $filter === $c ? 'margin-top:0' : '' ?>"
         href="?type=<?= urlencode($c) ?>"><?= e($c) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($byType): ?>
<div class="card">
  <h3>📊 Totals by Type</h3>
  <div class="table-wrap"><table>
    <tr><th>Type</th><th>Entries</th><th style="text-align:right;">Total</th></tr>
    <?php foreach ($byType as $b): ?>
      <tr><td><?= e($b['category']) ?></td>
          <td><?= (int)$b['entries'] ?></td>
          <td style="text-align:right;"><strong><?= money($b['total']) ?></strong></td></tr>
    <?php endforeach; ?>
    <tr style="background:#f7fafc;font-weight:700;">
      <td>Grand Total</td><td><?= array_sum(array_column($byType, 'entries')) ?></td>
      <td style="text-align:right;"><?= money(array_sum(array_column($byType, 'total'))) ?></td>
    </tr>
  </table></div>
</div>
<?php endif; ?>

<div class="card">
  <h3>📋 Expense Records <?= $filter ? '(' . e($filter) . ')' : '' ?></h3>
  <?php if (!$rows): ?><div class="empty">No expenses yet.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <tr><th>Date</th><th>Type</th><th style="text-align:right;">Amount</th>
          <th>Note</th><th>By</th><th></th></tr>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['date']) ?></td>
          <td><span class="badge orange"><?= e($r['category']) ?></span></td>
          <td style="text-align:right;"><strong><?= money($r['amount']) ?></strong></td>
          <td><?= e($r['note']) ?></td>
          <td><?= e($r['recorded_by']) ?></td>
          <td><a class="btn-sm btn-danger" style="padding:6px 10px;font-size:12px;"
                 data-confirm="Delete?" href="?delete=<?= $r['id'] ?>">🗑</a></td>
        </tr>
      <?php endforeach; ?>
    </table></div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>