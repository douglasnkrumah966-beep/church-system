<?php
require_once __DIR__ . '/_layout.php';

$CATEGORY = 'First Collection';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $amount = cleanAmount(post('amount'));
    $type   = trim(post('collection_type'));
    $status = post('status') ?: 'Recorded';

    if ($amount <= 0) { flash('❌ Please enter a valid amount'); redirect('first_collection.php'); }
    if ($type === '') { flash('❌ Please type the collection name'); redirect('first_collection.php'); }

    $allowedStatuses = ['Recorded','Deposited','Pending'];
    if (!in_array($status, $allowedStatuses, true)) $status = 'Recorded';

    $freeNote = trim(post('note'));
    $finalNote = $type . ($freeNote ? ' — ' . $freeNote : '');

    $stmt = $pdo->prepare("INSERT INTO finances
        (type, category, amount, date, note, recorded_by)
        VALUES ('income', ?, ?, ?, ?, ?)");
    $stmt->execute([$CATEGORY, $amount, post('date') ?: 'date'('Y-m-d'),
                    $finalNote, currentUser()['name']]);

    $id = $pdo->lastInsertId();
    $pdo->prepare("UPDATE finances SET note = ? WHERE id = ?")
        ->execute(["[{$status}] " . $finalNote, $id]);

    flash('✅ ' . $type . ' saved: ' . money($amount));
    redirect('first_collection.php');
}

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM finances WHERE id = ? AND category = ?")
        ->execute([(int)$_GET['delete'], $CATEGORY]);
    flash('🗑 Record deleted');
    redirect('first_collection.php');
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
            flash("✅ Status updated to $mark");
        }
    }
    redirect('first_collection.php');
}

function fcStatus(?string $note): string {
    if (preg_match('/^\[(Recorded|Deposited|Pending)\]/', $note ?? '', $m)) return $m[1];
    return 'Recorded';
}

function fcCleanNote(?string $note): string {
    return trim(preg_replace('/^\[(Recorded|Deposited|Pending)\]\s*/', '', $note ?? ''));
}

$rows = $pdo->query("SELECT * FROM finances WHERE category = '$CATEGORY'
                     ORDER BY date DESC, id DESC")->fetchAll();

$today = 'date'('Y-m-d');
$monthStart = 'date'('Y-m-01');
$yearStart  = 'date'('Y-01-01');
$todayTotal = 0; $monthTotal = 0; $yearTotal = 0;
foreach ($rows as $r) {
    $amt = (float)$r['amount'];
    if ($r['date'] === $today)                             $todayTotal += $amt;
    if ($r['date'] >= $monthStart && $r['date'] <= $today) $monthTotal += $amt;
    if ($r['date'] >= $yearStart  && $r['date'] <= $today) $yearTotal  += $amt;
}

$byType = [];
foreach ($rows as $r) {
    $clean = fcCleanNote($r['note']);
    $typeName = trim(explode('—', $clean, 2)[0]) ?: 'Unnamed';
    if (!isset($byType[$typeName])) $byType[$typeName] = ['total' => 0, 'count' => 0];
    $byType[$typeName]['total'] += (float)$r['amount'];
    $byType[$typeName]['count']++;
}
?>

<div class="stat-grid">
  <div class="stat"><div class="num"><?= money($todayTotal) ?></div><div class="lbl">Today</div></div>
  <div class="stat"><div class="num"><?= money($monthTotal) ?></div><div class="lbl">This Month</div></div>
  <div class="stat"><div class="num"><?= money($yearTotal) ?></div><div class="lbl">This Year</div></div>
  <div class="stat"><div class="num"><?= count($rows) ?></div><div class="lbl">Records</div></div>
</div>

<div class="card" style="margin-top:16px;">
  <h3>➕ Record First Collection</h3>
  <form method="post">
    <div class="grid grid-2">
      <div style="grid-column:1/-1;">
        <label>Collection Type *</label>
        <input name="collection_type" required placeholder="e.g. First Collection — 8am Mass"
               list="collectionSuggestions">
        <datalist id="collectionSuggestions">
          <option value="First Collection">
          <option value="Second Collection">
          <option value="First Collection — 6:30am Mass">
          <option value="First Collection — 8am Mass">
          <option value="First Collection — 10am Mass">
          <option value="Special Collection">
          <option value="Sunday Collection">
        </datalist>
      </div>
      <div><label>Amount (GH₵)</label>
        <input name="amount" type="number" step="0.01" min="0.01" required></div>
      <div><label>Date</label>
        <input name="date" type="date" value="<?= 'date'('Y-m-d') ?>" required></div>
      <div><label>Status</label>
        <select name="status">
          <option>Recorded</option><option>Deposited</option><option>Pending</option>
        </select></div>
      <div><label>Note (optional)</label>
        <input name="note" placeholder="e.g. Counted by J. Mensah"></div>
    </div>
    <button class="btn-primary" name="save" value="1">Save Record</button>
  </form>
</div>

<?php if ($byType): ?>
<div class="card">
  <h3>📊 Totals by Collection Type</h3>
  <div class="table-wrap"><table>
    <tr><th>Type</th><th>Entries</th><th style="text-align:right;">Total</th></tr>
    <?php uasort($byType, fn($a,$b) => $b['total'] <=> $a['total']);
    foreach ($byType as $name => $data): ?>
      <tr>
        <td><strong><?= e($name) ?></strong></td>
        <td><?= $data['count'] ?></td>
        <td style="text-align:right;"><?= money($data['total']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php endif; ?>

<div class="card">
  <h3>📋 First Collection Records</h3>
  <?php if (!$rows): ?>
    <div class="empty">No records yet.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <tr><th>Date</th><th>Type</th><th style="text-align:right;">Amount</th>
          <th>Status</th><th>Note</th><th></th></tr>
      <?php foreach ($rows as $r):
        $stat = fcStatus($r['note']);
        $clean = fcCleanNote($r['note']);
        $parts = explode('—', $clean, 2);
        $typeName = trim($parts[0]);
        $freeNote = isset($parts[1]) ? trim($parts[1]) : '';
        $statClass = $stat === 'Deposited' ? 'green' : ($stat === 'Pending' ? 'orange' : '');
      ?>
        <tr>
          <td><?= 'date'('d/m/y', 'strtotime'($r['date'])) ?></td>
          <td><span class="badge"><?= e($typeName) ?></span></td>
          <td style="text-align:right;"><strong><?= money($r['amount']) ?></strong></td>
          <td><span class="badge <?= $statClass ?>"><?= e($stat) ?></span></td>
          <td><?= e($freeNote) ?></td>
          <td>
            <?php if ($stat !== 'Deposited'): ?>
              <a class="btn-sm btn-success" style="padding:6px 10px;font-size:12px;"
                 data-confirm="Mark as Deposited?" href="?mark=Deposited&id=<?= $r['id'] ?>">✓</a>
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