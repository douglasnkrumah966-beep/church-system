<?php
require_once __DIR__ . '/_layout.php';
/* ============================================================
   FINANCIAL REPORTS
   ------------------------------------------------------------
   Filter by date range, member, type, category.
   Shows breakdown + full transaction list.
   ============================================================ */

$from         = get('from')       ?: 'date'('Y-m-01');
$to           = get('to')         ?: 'date'('Y-m-t');
$filterMember = (int)get('member');
$filterType   = get('type');
$filterCat    = get('category');

$selectedMember = $filterMember ? getMember($pdo, $filterMember) : null;

/* ---------- Build WHERE ---------- */
$where  = "f.date BETWEEN ? AND ?";
$params = [$from, $to];

if ($filterMember) {
    $where .= " AND f.member_id = ?";
    $params[] = $filterMember;
}
if (in_array($filterType, ['income','expense'], true)) {
    $where .= " AND f.type = ?";
    $params[] = $filterType;
}
if ($filterCat !== '' && in_array($filterCat, array_merge(
        CONTRIBUTION_TYPES,
        ['First Collection', 'Day Born Collection', 'Other Income'],
        EXPENSE_TYPES
    ), true)) {
    $where .= " AND f.category = ?";
    $params[] = $filterCat;
}

/* ---------- Fetch rows ---------- */
$stmt = $pdo->prepare("SELECT f.*,
                              m.full_name AS member_name,
                              m.phone     AS member_phone,
                              m.photo     AS member_photo
                       FROM finances f
                       LEFT JOIN members m ON m.id = f.member_id
                       WHERE $where
                       ORDER BY f.date ASC, f.id ASC");
$stmt->execute($params);
$rows = $stmt->fetchAll();

/* ---------- Totals & breakdown ---------- */
$income  = 0;
$expense = 0;
$byCat = ['income' => [], 'expense' => []];

foreach ($rows as $r) {
    if ($r['type'] === 'income')  $income  += (float)$r['amount'];
    else                          $expense += (float)$r['amount'];

    $byCat[$r['type']][$r['category']] =
        ($byCat[$r['type']][$r['category']] ?? 0) + (float)$r['amount'];
}

/* ---------- Sort breakdown by display order ---------- */
$displayOrder = [
  'income' => array_merge(
      ['First Collection', 'Day Born Collection'],
      CONTRIBUTION_TYPES,
      ['Other Income']
  ),
  'expense' => EXPENSE_TYPES,
];

foreach (['income','expense'] as $t) {
    if (empty($byCat[$t])) continue;
    $order = $displayOrder[$t];
    uksort($byCat[$t], function($a, $b) use ($order) {
        $ia = array_search($a, $order, true);
        $ib = array_search($b, $order, true);
        if ($ia === false) $ia = PHP_INT_MAX;
        if ($ib === false) $ib = PHP_INT_MAX;
        return $ia <=> $ib;
    });
}

/* ---------- Filter dropdown data ---------- */
$allCats = $pdo->query("SELECT DISTINCT category FROM finances ORDER BY category")->fetchAll();

$filteredMembers = $pdo->query("
    SELECT DISTINCT m.id, m.full_name, m.photo
    FROM finances f
    JOIN members m ON m.id = f.member_id
    ORDER BY m.full_name
")->fetchAll();

/* _layout already printed the header — no include 'includes/header.php' here */
?>

<!-- ============================================================
     FILTERS
     ============================================================ -->
<div class="card no-print">
  <h3>📅 Report Filters</h3>
  <form method="get">
    <div class="grid grid-2">
      <div><label>From</label>
        <input name="from" type="date" value="<?= e($from) ?>"></div>
      <div><label>To</label>
        <input name="to" type="date" value="<?= e($to) ?>"></div>
      <div>
        <label>Type</label>
        <select name="type">
          <option value="">All types</option>
          <option value="income"  <?= $filterType==='income'  ? 'selected':'' ?>>Income only</option>
          <option value="expense" <?= $filterType==='expense' ? 'selected':'' ?>>Expenses only</option>
        </select>
      </div>
      <div>
        <label>Category</label>
        <select name="category">
          <option value="">All categories</option>
          <?php foreach ($allCats as $c): ?>
            <option <?= $filterCat === $c['category'] ? 'selected' : '' ?>>
              <?= e($c['category']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="grid-column:1/-1;">
        <label>Member (optional)</label>
        <select name="member">
          <option value="">All members</option>
          <?php foreach ($filteredMembers as $m): ?>
            <option value="<?= (int)$m['id'] ?>" <?= $filterMember==$m['id'] ? 'selected':'' ?>>
              <?= e($m['full_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="grid grid-2" style="margin-top:12px;">
      <button class="btn-primary" style="margin-top:0;">🔎 Generate Report</button>
      <a class="btn-sm btn-ghost" style="text-align:center;padding:12px;"
         href="reports.php">↺ Reset Filters</a>
    </div>
  </form>
</div>

<!-- ============================================================
     SUMMARY TILES
     ============================================================ -->
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
    <div class="num"><?= money($income - $expense) ?></div>
    <div class="lbl">Net Balance</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#6b46c1,#553c9a);">
    <div class="num"><?= count($rows) ?></div>
    <div class="lbl">Transactions</div>
  </div>
</div>

<!-- ============================================================
     PRINT-ONLY HEADER
     ============================================================ -->
<div class="card print-only" style="display:none;">
  <h3 style="text-align:center;">
    <?= e($settings['church_name'] ?? 'Church') ?><br>
    <small style="font-weight:400;color:#718096;">
      Financial Report · <?= 'date'('jS F Y', 'strtotime'($from)) ?>
      — <?= 'date'('jS F Y', 'strtotime'($to)) ?>
      <?= $selectedMember ? ' · Member: ' . e($selectedMember['full_name']) : '' ?>
    </small>
  </h3>
</div>

<!-- ============================================================
     BREAKDOWNS
     ============================================================ -->
<?php foreach (['income','expense'] as $type): ?>
  <?php if (!empty($byCat[$type])): ?>
    <div class="card">
      <h3>
        <?= $type === 'income' ? '💚 Income Breakdown' : '❤️ Expense Breakdown' ?>
        <span class="badge <?= $type==='income'?'green':'orange' ?>" style="margin-left:8px;">
          <?= money(array_sum($byCat[$type])) ?>
        </span>
      </h3>
      <div class="table-wrap"><table>
        <tr><th>Category</th><th style="text-align:right;">Amount</th></tr>
        <?php foreach ($byCat[$type] as $cat => $amt): ?>
          <tr>
            <td><?= e($cat) ?></td>
            <td style="text-align:right;"><strong><?= money($amt) ?></strong></td>
          </tr>
        <?php endforeach; ?>
        <tr style="background:#f7fafc;font-weight:700;">
          <td>Total</td>
          <td style="text-align:right;"><?= money(array_sum($byCat[$type])) ?></td>
        </tr>
      </table></div>
    </div>
  <?php endif; ?>
<?php endforeach; ?>

<!-- ============================================================
     ALL TRANSACTIONS
     ============================================================ -->
<div class="card">
  <h3>📋 All Transactions in Period (<?= count($rows) ?>)</h3>

  <?php if (!$rows): ?>
    <div class="empty">No transactions match these filters.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Member</th>
          <th>Type</th>
          <th>Category</th>
          <th>Beneficiary</th>
          <th style="text-align:right;">Amount</th>
          <th>Note</th>
          <th>By</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r):
          $memberRow = $r['member_name']
              ? ['full_name' => $r['member_name'], 'photo' => $r['member_photo']]
              : null;
        ?>
          <tr>
            <td><?= e($r['date']) ?></td>
            <td>
              <?php if ($memberRow): ?>
                <div style="display:flex;align-items:center;gap:8px;">
                  <?= memberAvatar($memberRow, 32) ?>
                  <div>
                    <strong><?= e($memberRow['full_name']) ?></strong>
                    <?php if (!empty($r['member_phone'])): ?>
                      <div style="font-size:11px;color:#718096;"><?= e($r['member_phone']) ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php else: ?>
                <span style="color:#a0aec0;">—</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge <?= $r['type']==='income'?'green':'orange' ?>">
                <?= e($r['type']) ?>
              </span>
            </td>
            <td><?= e($r['category']) ?></td>
            <td>
              <?php if (!empty($r['beneficiary_name'])): ?>
                <div style="font-weight:600;color:#9b2c2c;">
                  🕊 <?= e($r['beneficiary_name']) ?>
                </div>
                <?php if (!empty($r['relationship'])): ?>
                  <div style="font-size:11px;color:#718096;"><?= e($r['relationship']) ?></div>
                <?php endif; ?>
              <?php else: ?>
                <span style="color:#a0aec0;">—</span>
              <?php endif; ?>
            </td>
            <td style="text-align:right;"><strong><?= money($r['amount']) ?></strong></td>
            <td><?= e($r['note']) ?></td>
            <td><?= e($r['recorded_by']) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr style="background:#edf2f7;font-weight:700;">
          <td colspan="5" style="text-align:right;">Totals</td>
          <td style="text-align:right;"><?= money($income - $expense) ?></td>
          <td colspan="2"></td>
        </tr>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<!-- ============================================================
     PRINT BUTTON
     ============================================================ -->
<div class="card no-print" style="text-align:center;">
  <button class="btn-sm btn-primary" style="max-width:220px;margin:0 auto;"
          onclick="window.print()">🖨 Print / Save as PDF</button>
</div>

<style>
@media print {
  header, nav.tabs, footer, .no-print, .flash { display: none !important; }
  body { background: #fff !important; }
  main { padding: 0 !important; max-width: 100% !important; }
  .card { box-shadow: none !important; border: 1px solid #cbd5e0;
          page-break-inside: avoid; margin-bottom: 10px; }
  .stat-grid { display: flex; gap: 8px; }
  .stat { background: #fff !important; color: #000 !important;
          border: 1px solid #cbd5e0; flex: 1; }
  .stat .num { font-size: 16px; }
  table { font-size: 12px; }
  img { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .print-only { display: block !important; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>