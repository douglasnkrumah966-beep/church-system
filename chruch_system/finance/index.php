<?php
require_once __DIR__ . '/_layout.php';

/* ---------- Totals ---------- */
$income  = $pdo->query("SELECT COALESCE(SUM(amount),0) s FROM finances WHERE type='income'")->fetch()['s'];
$expense = $pdo->query("SELECT COALESCE(SUM(amount),0) s FROM finances WHERE type='expense'")->fetch()['s'];
$balance = $income - $expense;

$monthStart = 'date'('Y-m-01');
$monthEnd   = 'date'('Y-m-t');
$stmt = $pdo->prepare("SELECT
    COALESCE(SUM(CASE WHEN type='income'  THEN amount ELSE 0 END),0) AS inc,
    COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) AS exp
    FROM finances WHERE date BETWEEN ? AND ?");
$stmt->execute([$monthStart, $monthEnd]);
$month = $stmt->fetch();

$yearStart = 'date'('Y-01-01');
$yearEnd   = 'date'('Y-12-31');
$stmt = $pdo->prepare("SELECT
    COALESCE(SUM(CASE WHEN type='income'  THEN amount ELSE 0 END),0) AS inc,
    COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) AS exp
    FROM finances WHERE date BETWEEN ? AND ?");
$stmt->execute([$yearStart, $yearEnd]);
$year = $stmt->fetch();

/* ---------- 6-month trend ---------- */
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $start = 'date'('Y-m-01', 'strtotime'("-$i month"));
    $end   = 'date'('Y-m-t',  'strtotime'("-$i month"));
    $stmt = $pdo->prepare("SELECT
        COALESCE(SUM(CASE WHEN type='income'  THEN amount ELSE 0 END),0) AS inc,
        COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) AS exp
        FROM finances WHERE date BETWEEN ? AND ?");
    $stmt->execute([$start, $end]);
    $r = $stmt->fetch();
    $months[] = [
        'label'   => 'date'('M Y', 'strtotime'($start)),
        'income'  => (float)$r['inc'],
        'expense' => (float)$r['exp'],
    ];
}
$maxVal = max(1, max(array_merge(
    array_column($months, 'income'),
    array_column($months, 'expense')
)));

/* ---------- Category totals ---------- */
$byCat = $pdo->query("SELECT type, category, SUM(amount) total, COUNT(*) entries
                      FROM finances GROUP BY type, category
                      ORDER BY type, category")->fetchAll();

$orderIncome = array_merge(['First Collection','Day Born Collection'], CONTRIBUTION_TYPES, ['Other Income']);
$orderExpense = EXPENSE_TYPES;

$incomeCats = []; $expenseCats = [];
foreach ($byCat as $c) {
    if ($c['type'] === 'income')  $incomeCats[]  = $c;
    else                          $expenseCats[] = $c;
}
usort($incomeCats, function($a, $b) use ($orderIncome) {
    $ia = array_search($a['category'], $orderIncome, true); if ($ia === false) $ia = PHP_INT_MAX;
    $ib = array_search($b['category'], $orderIncome, true); if ($ib === false) $ib = PHP_INT_MAX;
    return $ia <=> $ib;
});
usort($expenseCats, function($a, $b) use ($orderExpense) {
    $ia = array_search($a['category'], $orderExpense, true); if ($ia === false) $ia = PHP_INT_MAX;
    $ib = array_search($b['category'], $orderExpense, true); if ($ib === false) $ib = PHP_INT_MAX;
    return $ia <=> $ib;
});

/* ---------- Recent 10 ---------- */
$recent = $pdo->query("SELECT f.*, m.full_name AS member_name, m.photo AS member_photo
                       FROM finances f
                       LEFT JOIN members m ON m.id = f.member_id
                       ORDER BY f.date DESC, f.id DESC LIMIT 10")->fetchAll();
?>

<div class="stat-grid">
  <div class="stat" style="background:linear-gradient(135deg,#38a169,#276749);">
    <div class="num"><?= money($income) ?></div>
    <div class="lbl">Total Income</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#e53e3e,#9b2c2c);">
    <div class="num"><?= money($expense) ?></div>
    <div class="lbl">Total Expenses</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#3182ce,#2c5282);">
    <div class="num"><?= money($balance) ?></div>
    <div class="lbl">Balance</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#6b46c1,#553c9a);">
    <div class="num"><?= money($month['inc'] - $month['exp']) ?></div>
    <div class="lbl">This Month Net</div>
  </div>
</div>

<div class="card" style="margin-top:16px;">
  <h3>📅 <?= 'date'('F Y') ?> Snapshot</h3>
  <div class="table-wrap"><table>
    <tr><th>Period</th><th style="text-align:right;">Income</th>
        <th style="text-align:right;">Expenses</th><th style="text-align:right;">Net</th></tr>
    <tr>
      <td><strong>This Month</strong></td>
      <td style="text-align:right;color:#22543d;"><?= money($month['inc']) ?></td>
      <td style="text-align:right;color:#742a2a;"><?= money($month['exp']) ?></td>
      <td style="text-align:right;"><strong><?= money($month['inc'] - $month['exp']) ?></strong></td>
    </tr>
    <tr>
      <td><strong>This Year (<?= 'date'('Y') ?>)</strong></td>
      <td style="text-align:right;color:#22543d;"><?= money($year['inc']) ?></td>
      <td style="text-align:right;color:#742a2a;"><?= money($year['exp']) ?></td>
      <td style="text-align:right;"><strong><?= money($year['inc'] - $year['exp']) ?></strong></td>
    </tr>
  </table></div>
</div>

<div class="card">
  <h3>⚡ Quick Actions</h3>
  <div class="grid grid-2">
    <a class="btn-sm btn-success" style="text-align:center;padding:12px;" href="first_collection.php">➕ First Collection</a>
    <a class="btn-sm btn-success" style="text-align:center;padding:12px;" href="dayborn_collection.php">➕ Day Born Collection</a>
    <a class="btn-sm btn-success" style="text-align:center;padding:12px;" href="contributions.php">➕ Contribution</a>
    <a class="btn-sm btn-danger"  style="text-align:center;padding:12px;" href="expenses.php">➖ Expense</a>
    <a class="btn-sm btn-primary" style="text-align:center;padding:12px;grid-column:1/-1;" href="cashbook.php">📖 Open Cashbook</a>
    <a class="btn-sm btn-ghost"   style="text-align:center;padding:12px;grid-column:1/-1;" href="funeral_report.php">🕊 Funeral Report</a>
  </div>
</div>

<div class="card">
  <h3>📊 Last 6 Months — Income vs Expenses</h3>
  <div style="display:flex;gap:8px;align-items:flex-end;height:220px;padding:10px 0;">
    <?php foreach ($months as $m):
      $incH = $maxVal ? ($m['income']  / $maxVal * 100) : 0;
      $expH = $maxVal ? ($m['expense'] / $maxVal * 100) : 0;
    ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;">
        <div style="display:flex;gap:2px;align-items:flex-end;height:160px;width:100%;">
          <div title="Income: <?= money($m['income']) ?>"
               style="flex:1;background:linear-gradient(180deg,#48bb78,#276749);
                      height:<?= $incH ?>%;border-radius:6px 6px 0 0;min-height:4px;"></div>
          <div title="Expenses: <?= money($m['expense']) ?>"
               style="flex:1;background:linear-gradient(180deg,#fc8181,#9b2c2c);
                      height:<?= $expH ?>%;border-radius:6px 6px 0 0;min-height:4px;"></div>
        </div>
        <div style="font-size:11px;color:#718096;text-align:center;font-weight:600;">
          <?= e($m['label']) ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <div style="display:flex;gap:16px;justify-content:center;font-size:12px;margin-top:8px;">
    <span><span style="display:inline-block;width:10px;height:10px;
                       background:#48bb78;border-radius:2px;margin-right:4px;"></span>Income</span>
    <span><span style="display:inline-block;width:10px;height:10px;
                       background:#fc8181;border-radius:2px;margin-right:4px;"></span>Expenses</span>
  </div>
</div>

<div class="card">
  <h3>💚 Income by Category</h3>
  <?php if (!$incomeCats): ?>
    <div class="empty">No income recorded yet.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <tr><th>Category</th><th>Entries</th><th style="text-align:right;">Total</th></tr>
      <?php foreach ($incomeCats as $c): ?>
        <tr>
          <td><?= e($c['category']) ?></td>
          <td><?= (int)$c['entries'] ?></td>
          <td style="text-align:right;"><strong><?= money($c['total']) ?></strong></td>
        </tr>
      <?php endforeach; ?>
      <tr style="background:#f0fff4;font-weight:700;">
        <td colspan="2">Total Income</td>
        <td style="text-align:right;"><?= money($income) ?></td>
      </tr>
    </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <h3>❤️ Expenses by Category</h3>
  <?php if (!$expenseCats): ?>
    <div class="empty">No expenses recorded yet.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <tr><th>Category</th><th>Entries</th><th style="text-align:right;">Total</th></tr>
      <?php foreach ($expenseCats as $c): ?>
        <tr>
          <td><?= e($c['category']) ?></td>
          <td><?= (int)$c['entries'] ?></td>
          <td style="text-align:right;"><strong><?= money($c['total']) ?></strong></td>
        </tr>
      <?php endforeach; ?>
      <tr style="background:#fff5f5;font-weight:700;">
        <td colspan="2">Total Expenses</td>
        <td style="text-align:right;"><?= money($expense) ?></td>
      </tr>
    </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <h3>🕒 Recent Transactions</h3>
  <?php if (!$recent): ?>
    <div class="empty">No transactions yet.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <tr><th>Date</th><th>Type</th><th>Category</th><th>Member</th>
          <th style="text-align:right;">Amount</th></tr>
      <?php foreach ($recent as $t):
        $memberRow = $t['member_name']
            ? ['full_name' => $t['member_name'], 'photo' => $t['member_photo']]
            : null;
      ?>
        <tr>
          <td><?= 'date'('d/m/y', 'strtotime'($t['date'])) ?></td>
          <td><span class="badge <?= $t['type']==='income'?'green':'orange' ?>"><?= e($t['type']) ?></span></td>
          <td><?= e($t['category']) ?></td>
          <td>
            <?php if ($memberRow): ?>
              <div style="display:flex;align-items:center;gap:6px;">
                <?= memberAvatar($memberRow, 26) ?>
                <span style="font-size:12px;"><?= e($memberRow['full_name']) ?></span>
              </div>
            <?php else: ?>
              <span style="color:#a0aec0;">—</span>
            <?php endif; ?>
          </td>
          <td style="text-align:right;"><strong><?= money($t['amount']) ?></strong></td>
        </tr>
      <?php endforeach; ?>
    </table></div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>