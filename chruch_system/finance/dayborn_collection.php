<?php
require_once __DIR__ . '/_layout.php';

$CATEGORY = 'Day Born Collection';
$GROUP_ORDER = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $amount   = cleanAmount(post('amount'));
    $dayGroup = post('day_group');
    $status   = post('status') ?: 'Recorded';

    if ($amount <= 0) { flash('❌ Please enter a valid amount'); redirect('dayborn_collection.php'); }
    if (!$dayGroup)   { flash('❌ Please select a Day Born Group'); redirect('dayborn_collection.php'); }

    $allowedStatuses = ['Recorded','Deposited','Pending'];
    if (!in_array($status, $allowedStatuses, true)) $status = 'Recorded';

    $note = trim(post('note'));
    $groupLabel = $dayGroup . ' Born';
    $finalNote = $groupLabel . ($note ? ' — ' . $note : '');

    $stmt = $pdo->prepare("INSERT INTO finances
        (type, category, amount, date, note, recorded_by)
        VALUES ('income', ?, ?, ?, ?, ?)");
    $stmt->execute([$CATEGORY, $amount, post('date') ?: 'date'('Y-m-d'),
                    $finalNote, currentUser()['name']]);
    $id = $pdo->lastInsertId();
    $pdo->prepare("UPDATE finances SET note = ? WHERE id = ?")
        ->execute(["[{$status}] " . $finalNote, $id]);

    flash('✅ ' . $groupLabel . ' collection saved: ' . money($amount));
    redirect('dayborn_collection.php');
}

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM finances WHERE id = ? AND category = ?")
        ->execute([(int)$_GET['delete'], $CATEGORY]);
    flash('🗑 Record deleted');
    redirect('dayborn_collection.php');
}

if (isset($_GET['mark']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $mark = $_GET['mark'];
    if (in_array($mark, ['Recorded','Deposited','Pending'], true)) {
        $stmt = $pdo->prepare("SELECT * FROM finances WHERE id=? AND category=?");
        $stmt->execute([$id, $CATEGORY]);
        $row = $stmt->fetch();
        if ($row) {
            $clean = preg_replace('/^\[(Recorded|Deposited|Pending)\]\s*/', '', $row['note']);
            $pdo->prepare("UPDATE finances SET note=? WHERE id=?")
                ->execute(["[{$mark}] " . $clean, $id]);
            flash("✅ Status updated");
        }
    }
    redirect('dayborn_collection.php');
}
/* ---------- Helper functions ---------- */
function dbExtractStatus(?string $note): string {
    if (preg_match('/^\[(Recorded|Deposited|Pending)\]/', $note ?? '', $m)) return $m[1];
    return 'Recorded';
}

function dbExtractGroup(?string $note): string {
    $clean = preg_replace('/^\[(Recorded|Deposited|Pending)\]\s*/', '', $note ?? '');
    if (preg_match('/^(Sunday|Monday|Tuesday|Wednesday|Thursday|Friday|Saturday)\s+Born/', $clean, $m)) {
        return $m[1];
    }
    return 'Unknown';
}

function dbCleanNote(?string $note): string {
    $clean = preg_replace('/^\[(Recorded|Deposited|Pending)\]\s*/', '', $note ?? '');
    return trim(preg_replace('/^(Sunday|Monday|Tuesday|Wednesday|Thursday|Friday|Saturday)\s+Born\s*[—-]?\s*/',
                             '', $clean));
}

$allRows = $pdo->query("SELECT * FROM finances WHERE category = '$CATEGORY'
                        ORDER BY date DESC, id DESC")->fetchAll();

$today = 'date'('Y-m-d');
$monthStart = 'date'('Y-m-01');
$yearStart  = 'date'('Y-01-01');
$todayTotal = $monthTotal = $yearTotal = 0;
foreach ($allRows as $r) {
    $amt = (float)$r['amount'];
    if ($r['date'] === $today)                             $todayTotal += $amt;
    if ($r['date'] >= $monthStart && $r['date'] <= $today) $monthTotal += $amt;
    if ($r['date'] >= $yearStart  && $r['date'] <= $today) $yearTotal  += $amt;
}

$byGroup = array_fill_keys($GROUP_ORDER, 0);
$byGroupCount = array_fill_keys($GROUP_ORDER, 0);
foreach ($allRows as $r) {
    $g = dbExtractGroup($r['note']);
    if (isset($byGroup[$g])) {
        $byGroup[$g] += (float)$r['amount'];
        $byGroupCount[$g]++;
    }
}

$filterGroup = get('group');
$rows = $allRows;
if ($filterGroup && in_array($filterGroup, $GROUP_ORDER, true)) {
    $rows = array_values(array_filter($allRows,
        fn($r) => dbExtractGroup($r['note']) === $filterGroup));
}
?>

<div class="stat-grid">
  <div class="stat"><div class="num"><?= money($todayTotal) ?></div><div class="lbl">Today</div></div>
  <div class="stat"><div class="num"><?= money($monthTotal) ?></div><div class="lbl">This Month</div></div>
  <div class="stat"><div class="num"><?= money($yearTotal) ?></div><div class="lbl">This Year</div></div>
  <div class="stat"><div class="num"><?= count($allRows) ?></div><div class="lbl">Records</div></div>
</div>

<div class="card" style="margin-top:16px;">
  <h3>➕ Record Day Born Collection</h3>
  <form method="post">
    <div class="grid grid-2">
      <div><label>Day Born Group</label>
        <select name="day_group" required>
          <option value="">Select group</option>
          <?php foreach ($GROUP_ORDER as $g): ?>
            <option <?= $filterGroup === $g ? 'selected' : '' ?>><?= $g ?> Born</option>
          <?php endforeach; ?>
        </select></div>
      <div><label>Amount (GH₵)</label>
        <input name="amount" type="number" step="0.01" min="0.01" required></div>
      <div><label>Date</label>
        <input name="date" type="date" value="<?= 'date'('Y-m-d') ?>" required></div>
      <div><label>Status</label>
        <select name="status"><option>Recorded</option><option>Deposited</option><option>Pending</option></select></div>
      <div style="grid-column:1/-1;"><label>Note (optional)</label>
        <input name="note" placeholder="e.g. Weekly collection"></div>
    </div>
    <button class="btn-primary" name="save" value="1">Save Record</button>
  </form>
</div>

<div class="card">
  <h3>📊 Totals by Group</h3>
  <div class="table-wrap"><table>
    <tr><th>Group</th><th>Entries</th><th style="text-align:right;">Total</th><th></th></tr>
    <?php foreach ($GROUP_ORDER as $g): ?>
      <tr>
        <td><strong><?= $g ?> Born</strong></td>
        <td><?= $byGroupCount[$g] ?></td>
        <td style="text-align:right;"><?= money($byGroup[$g]) ?></td>
        <td><a class="btn-sm btn-ghost" style="padding:6px 12px;font-size:12px;"
               href="?group=<?= urlencode($g) ?>">View</a></td>
      </tr>
    <?php endforeach; ?>
    <tr style="background:#f7fafc;font-weight:700;">
      <td>Grand Total</td>
      <td><?= array_sum($byGroupCount) ?></td>
      <td style="text-align:right;"><?= money(array_sum($byGroup)) ?></td>
      <td></td>
    </tr>
  </table></div>
</div>

<div class="card">
  <h3>📋 Day Born Collection Records <?= $filterGroup ? '(' . e($filterGroup) . ')' : '' ?></h3>
  <?php if ($filterGroup): ?>
    <a class="btn-sm btn-ghost" href="dayborn_collection.php"
       style="display:inline-block;margin-bottom:10px;">↺ Show All</a>
  <?php endif; ?>
  <?php if (!$rows): ?>
    <div class="empty">No records.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <tr><th>Date</th><th>Group</th><th style="text-align:right;">Amount</th>
          <th>Status</th><th>Note</th><th></th></tr>
      <?php foreach ($rows as $r):
        $grp = dbExtractGroup($r['note']);
        $stat = dbExtractStatus($r['note']);
        $note = dbCleanNote($r['note']);
        $statClass = $stat === 'Deposited' ? 'green' : ($stat === 'Pending' ? 'orange' : '');
      ?>
        <tr>
          <td><?= 'date'('d/m/y', 'strtotime'($r['date'])) ?></td>
          <td><span class="badge"><?= e($grp) ?> Born</span></td>
          <td style="text-align:right;"><strong><?= money($r['amount']) ?></strong></td>
          <td><span class="badge <?= $statClass ?>"><?= e($stat) ?></span></td>
          <td><?= e($note) ?></td>
          <td>
            <?php if ($stat !== 'Deposited'): ?>
              <a class="btn-sm btn-success" style="padding:6px 10px;font-size:12px;"
                 data-confirm="Mark as Deposited?"
                 href="?mark=Deposited&id=<?= $r['id'] ?><?= $filterGroup ? '&group='.urlencode($filterGroup):'' ?>">✓</a>
            <?php endif; ?>
            <a class="btn-sm btn-danger" style="padding:6px 10px;font-size:12px;"
               data-confirm="Delete?" href="?delete=<?= $r['id'] ?>">🗑</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </table></div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>