<?php
require_once __DIR__ . '/_layout.php';

/* ============================================================
   CASHBOOK — Central Financial Ledger (READ-ONLY)
   ============================================================ */

$from         = get('from') ?: 'date'('Y-m-01');
$to           = get('to')   ?: 'date'('Y-m-t');
$filterType   = get('type');
$filterCat    = get('category');
$filterSource = get('source');

$where  = "f.date BETWEEN ? AND ?";
$params = [$from, $to];

if (in_array($filterType, ['income','expense'], true)) {
    $where .= " AND f.type = ?";
    $params[] = $filterType;
}

if ($filterSource === 'first_collection') {
    $where .= " AND f.category = 'First Collection'";
} elseif ($filterSource === 'dayborn') {
    $where .= " AND f.category = 'Day Born Collection'";
} elseif ($filterSource === 'contributions') {
    $placeholders = implode(',', array_fill(0, count(CONTRIBUTION_TYPES), '?'));
    $where .= " AND f.category IN ($placeholders)";
    foreach (CONTRIBUTION_TYPES as $c) $params[] = $c;
} elseif ($filterSource === 'expenses') {
    $where .= " AND f.type = 'expense'";
}

if ($filterCat !== '') {
    $where .= " AND f.category = ?";
    $params[] = $filterCat;
}

$stmt = $pdo->prepare("SELECT f.*,
                              m.full_name AS member_name,
                              m.photo     AS member_photo
                       FROM finances f
                       LEFT JOIN members m ON m.id = f.member_id
                       WHERE $where
                       ORDER BY f.date ASC, f.id ASC");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$openingStmt = $pdo->prepare("SELECT
    COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END),0) AS inc,
    COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) AS exp
    FROM finances WHERE date < ?");
$openingStmt->execute([$from]);
$opening = $openingStmt->fetch();
$openingBalance = (float)$opening['inc'] - (float)$opening['exp'];

$totalIncome  = 0;
$totalExpense = 0;
foreach ($rows as $r) {
    if ($r['type'] === 'income') $totalIncome  += (float)$r['amount'];
    else                         $totalExpense += (float)$r['amount'];
}
$closingBalance = $openingBalance + $totalIncome - $totalExpense;

$bySource = [
    'First Collection'    => ['income' => 0, 'expense' => 0, 'count' => 0],
    'Day Born Collection' => ['income' => 0, 'expense' => 0, 'count' => 0],
    'Contributions'       => ['income' => 0, 'expense' => 0, 'count' => 0],
    'Expenses'            => ['income' => 0, 'expense' => 0, 'count' => 0],
    'Other'               => ['income' => 0, 'expense' => 0, 'count' => 0],
];

foreach ($rows as $r) {
    $cat = $r['category'];
    $amt = (float)$r['amount'];
    if ($cat === 'First Collection')                 $key = 'First Collection';
    elseif ($cat === 'Day Born Collection')          $key = 'Day Born Collection';
    elseif (in_array($cat, CONTRIBUTION_TYPES, true)) $key = 'Contributions';
    elseif ($r['type'] === 'expense')                $key = 'Expenses';
    else                                             $key = 'Other';

    if ($r['type'] === 'income') $bySource[$key]['income']  += $amt;
    else                         $bySource[$key]['expense'] += $amt;
    $bySource[$key]['count']++;
}

$allCats = $pdo->query("SELECT DISTINCT category FROM finances ORDER BY category")->fetchAll();

/* Note: NO include 'includes/header.php' here — _layout already printed the header */
?>

<div class="stat-grid">
  <div class="stat" style="background: linear-gradient(135deg,#4a5568,#2d3748);">
    <div class="num"><?= money($openingBalance) ?></div>
    <div class="lbl">Opening Balance</div>
  </div>
  <div class="stat" style="background: linear-gradient(135deg,#38a169,#276749);">
    <div class="num"><?= money($totalIncome) ?></div>
    <div class="lbl">Total Income</div>
  </div>
  <div class="stat" style="background: linear-gradient(135deg,#e53e3e,#9b2c2c);">
    <div class="num"><?= money($totalExpense) ?></div>
    <div class="lbl">Total Expenses</div>
  </div>
  <div class="stat" style="background: linear-gradient(135deg,#3182ce,#2c5282);">
    <div class="num"><?= money($closingBalance) ?></div>
    <div class="lbl">Closing Balance</div>
  </div>
</div>

<div class="card no-print" style="margin-top:16px;">
  <h3>🔎 Filter Cashbook</h3>
  <form method="get">
    <div class="grid grid-2">
      <div><label>From</label><input name="from" type="date" value="<?= e($from) ?>"></div>
      <div><label>To</label><input name="to" type="date" value="<?= e($to) ?>"></div>
      <div>
        <label>Source</label>
        <select name="source">
          <option value="">All sources</option>
          <option value="first_collection" <?= $filterSource==='first_collection'?'selected':'' ?>>First Collection</option>
          <option value="dayborn"          <?= $filterSource==='dayborn'?'selected':'' ?>>Day Born Collection</option>
          <option value="contributions"    <?= $filterSource==='contributions'?'selected':'' ?>>Contributions</option>
          <option value="expenses"         <?= $filterSource==='expenses'?'selected':'' ?>>Expenses</option>
        </select>
      </div>
      <div>
        <label>Type</label>
        <select name="type">
          <option value="">All types</option>
          <option value="income"  <?= $filterType==='income'?'selected':'' ?>>Income only</option>
          <option value="expense" <?= $filterType==='expense'?'selected':'' ?>>Expenses only</option>
        </select>
      </div>
      <div style="grid-column:1/-1;">
        <label>Category</label>
        <select name="category">
          <option value="">All categories</option>
          <?php foreach ($allCats as $c): ?>
            <option <?= $filterCat===$c['category']?'selected':'' ?>>
              <?= e($c['category']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="grid grid-2" style="margin-top:12px;">
      <button class="btn-primary" style="margin-top:0;">🔎 Apply Filter</button>
      <a class="btn-sm btn-ghost" style="text-align:center;padding:12px;"
         href="cashbook.php">↺ Reset</a>
    </div>
  </form>
</div>

<div class="card">
  <h3>📊 Breakdown by Source</h3>
  <div class="table-wrap"><table>
    <tr>
      <th>Source</th><th>Entries</th>
      <th style="text-align:right;">Income</th>
      <th style="text-align:right;">Expense</th>
      <th style="text-align:right;">Net</th>
    </tr>
    <?php foreach ($bySource as $src => $d):
      if ($d['count'] === 0) continue;
      $net = $d['income'] - $d['expense'];
    ?>
      <tr>
        <td><strong><?= e($src) ?></strong></td>
        <td><?= $d['count'] ?></td>
        <td style="text-align:right;color:#22543d;"><?= money($d['income']) ?></td>
        <td style="text-align:right;color:#742a2a;"><?= money($d['expense']) ?></td>
        <td style="text-align:right;"><strong><?= money($net) ?></strong></td>
      </tr>
    <?php endforeach; ?>
    <tr style="background:#f7fafc;font-weight:700;">
      <td>Grand Total</td><td><?= count($rows) ?></td>
      <td style="text-align:right;"><?= money($totalIncome) ?></td>
      <td style="text-align:right;"><?= money($totalExpense) ?></td>
      <td style="text-align:right;"><?= money($totalIncome - $totalExpense) ?></td>
    </tr>
  </table></div>
</div>

<div class="card">
  <h3>
    📖 Cashbook Ledger
    <span class="badge" style="margin-left:8px;"><?= count($rows) ?> entries</span>
  </h3>

  <?php if (!$rows): ?>
    <div class="empty">No transactions in this period.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Source / Category</th>
          <th>Member</th>
          <th>Description</th>
          <th style="text-align:right;">Income</th>
          <th style="text-align:right;">Expense</th>
          <th style="text-align:right;">Balance</th>
        </tr>
      </thead>
      <tbody>
        <tr style="background:#edf2f7;font-style:italic;">
          <td><?= 'date'('d/m/y', 'strtotime'($from)) ?></td>
          <td colspan="3"><strong>Opening Balance (brought forward)</strong></td>
          <td style="text-align:right;">—</td>
          <td style="text-align:right;">—</td>
          <td style="text-align:right;"><strong><?= money($openingBalance) ?></strong></td>
        </tr>

        <?php
        $running = $openingBalance;
        foreach ($rows as $r):
          $amt = (float)$r['amount'];
          if ($r['type'] === 'income') {
            $running += $amt;
            $incCol = '<span style="color:#22543d;font-weight:600;">' . money($amt) . '</span>';
            $expCol = '—';
          } else {
            $running -= $amt;
            $incCol = '—';
            $expCol = '<span style="color:#742a2a;font-weight:600;">' . money($amt) . '</span>';
          }

          $cat = $r['category'];
          $sourceLabel = 'Other';
          if ($cat === 'First Collection')                  $sourceLabel = 'First Collection';
          elseif ($cat === 'Day Born Collection')           $sourceLabel = 'Day Born';
          elseif (in_array($cat, CONTRIBUTION_TYPES, true)) $sourceLabel = 'Contribution';
          elseif ($r['type'] === 'expense')                 $sourceLabel = 'Expense';

          $note = preg_replace('/^\[(Recorded|Deposited|Pending)\]\s*/', '', $r['note'] ?? '');

          $memberRow = $r['member_name']
              ? ['full_name' => $r['member_name'], 'photo' => $r['member_photo']]
              : null;
        ?>
          <tr>
            <td><?= 'date'('d/m/y', 'strtotime'($r['date'])) ?></td>
            <td>
              <span class="badge"><?= e($sourceLabel) ?></span>
              <div style="font-size:11px;color:#718096;margin-top:2px;">
                <?= e($cat) ?>
              </div>
            </td>
            <td>
              <?php if ($memberRow): ?>
                <div style="display:flex;align-items:center;gap:8px;">
                  <?= memberAvatar($memberRow, 32) ?>
                  <span style="font-size:13px;"><?= e($memberRow['full_name']) ?></span>
                </div>
              <?php else: ?>
                <span style="color:#a0aec0;">—</span>
              <?php endif; ?>
            </td>
            <td style="font-size:12px;">
              <?php if (!empty($r['beneficiary_name'])): ?>
                <div style="font-weight:600;color:#9b2c2c;">
                  🕊 For: <?= e($r['beneficiary_name']) ?>
                  <?= $r['relationship'] ? ' (' . e($r['relationship']) . ')' : '' ?>
                </div>
              <?php endif; ?>
              <?= e($note) ?>
            </td>
            <td style="text-align:right;"><?= $incCol ?></td>
            <td style="text-align:right;"><?= $expCol ?></td>
            <td style="text-align:right;"><strong><?= money($running) ?></strong></td>
          </tr>
        <?php endforeach; ?>

        <tr style="background:#2c5282;color:#fff;font-weight:700;">
          <td><?= 'date'('d/m/y', 'strtotime'($to)) ?></td>
          <td colspan="3"><strong>Closing Balance (carried forward)</strong></td>
          <td style="text-align:right;"><?= money($totalIncome) ?></td>
          <td style="text-align:right;"><?= money($totalExpense) ?></td>
          <td style="text-align:right;"><?= money($closingBalance) ?></td>
        </tr>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="card no-print" style="text-align:center;">
  <button class="btn-sm btn-primary" style="max-width:220px;margin:0 auto;"
          onclick="window.print()">🖨 Print / Save as PDF</button>
</div>

<style>
@media print {
  header, nav.tabs, footer, .no-print, .flash { display: none !important; }
  body { background: #fff !important; }
  main { padding: 0 !important; max-width: 100% !important; }
  .card { box-shadow: none !important; border: 1px solid #cbd5e0; page-break-inside: avoid; }
  .stat { background: #fff !important; color: #000 !important; border: 1px solid #cbd5e0; }
  .stat .num { font-size: 16px; }
  table { font-size: 11px; }
  img { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>