<?php
require_once __DIR__ . '/_layout.php';

$canEdit = in_array(currentUser()['role'], ['admin','secretary','catechist']);
$SACRAMENTS = ['Baptism','First Holy Communion','Confirmation','Holy Matrimony',
               'Holy Orders','Anointing of the Sick','Reconciliation'];

/* ============================================================
   SAVE NEW SACRAMENT
   ============================================================ */
if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $stmt = $pdo->prepare("INSERT INTO sacraments
      (member_id,type,date_received,minister,place,certificate_no)
      VALUES (?,?,?,?,?,?)");
    $stmt->execute([
      (int)post('member_id'),
      post('type'),
      post('date_received') ?: null,
      post('minister'),
      post('place'),
      post('certificate_no')
    ]);
    flash('✅ Sacrament recorded');
    redirect('sacraments.php');
}

/* ============================================================
   DELETE
   ============================================================ */
if ($canEdit && isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM sacraments WHERE id=?")->execute([(int)$_GET['delete']]);
    flash('🗑 Record deleted');
    redirect('sacraments.php');
}

/* ============================================================
   LOAD DATA
   ============================================================ */
$members = $pdo->query("SELECT id, full_name, photo, day_born FROM members ORDER BY full_name")->fetchAll();

$records = $pdo->query("
    SELECT s.*,
           m.full_name AS member_name,
           m.photo     AS member_photo,
           m.day_born  AS member_day_born
    FROM sacraments s
    LEFT JOIN members m ON m.id = s.member_id
    ORDER BY s.id DESC
")->fetchAll();

/* Group records by sacrament type for a nicer view */
$grouped = [];
foreach ($records as $r) {
    $grouped[$r['type']][] = $r;
}
?>

<!-- ============================================================
     SUMMARY TILES
     ============================================================ -->
<div class="stat-grid">
  <div class="stat" style="background:linear-gradient(135deg,#805ad5,#553c9a);">
    <div class="num"><?= count($records) ?></div>
    <div class="lbl">Total Records</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#3182ce,#2c5282);">
    <div class="num"><?= count($members) ?></div>
    <div class="lbl">Registered Members</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#38a169,#276749);">
    <div class="num"><?= count($grouped) ?></div>
    <div class="lbl">Sacrament Types</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#dd6b20,#9c4221);">
    <div class="num"><?= count($SACRAMENTS) ?></div>
    <div class="lbl">Types Available</div>
  </div>
</div>

<!-- ============================================================
     RECORD SACRAMENT FORM
     ============================================================ -->
<?php if ($canEdit): ?>
<div class="card" style="margin-top:16px;">
  <h3>✝️ Record Sacrament</h3>
  <form method="post">
    <div class="grid grid-2">
      <div>
        <label>Member</label>
        <select name="member_id" required>
          <option value="">Select member</option>
          <?php foreach ($members as $m): ?>
            <option value="<?= (int)$m['id'] ?>"><?= e($m['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Sacrament Type</label>
        <select name="type" required>
          <?php foreach ($SACRAMENTS as $s): ?>
            <option><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Date Received</label>
        <input name="date_received" type="date">
      </div>
      <div>
        <label>Minister / Priest</label>
        <input name="minister" placeholder="e.g. Fr. Michael">
      </div>
      <div>
        <label>Place</label>
        <input name="place" placeholder="e.g. St. Mary's Parish">
      </div>
      <div>
        <label>Certificate No. (optional)</label>
        <input name="certificate_no" placeholder="e.g. BAP-2024-001">
      </div>
    </div>
    <button class="btn-primary" name="save" value="1">Save Sacrament Record</button>
  </form>
</div>
<?php endif; ?>

<!-- ============================================================
     RECORDS TABLE (with photos)
     ============================================================ -->
<div class="card">
  <h3>📖 Sacrament Records (<?= count($records) ?>)</h3>

  <?php if (!$records): ?>
    <div class="empty">No sacrament records yet.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead>
        <tr>
          <th>Member</th>
          <th>Sacrament</th>
          <th>Date Received</th>
          <th>Minister</th>
          <th>Place</th>
          <th>Certificate</th>
          <?php if ($canEdit): ?><th></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($records as $r):
          $memberRow = $r['member_name']
              ? ['full_name' => $r['member_name'], 'photo' => $r['member_photo']]
              : null;
        ?>
          <tr>
            <td>
              <?php if ($memberRow): ?>
                <div style="display:flex;align-items:center;gap:10px;">
                  <?= memberAvatar($memberRow, 40) ?>
                  <div>
                    <strong><?= e($memberRow['full_name']) ?></strong>
                    <?php if (!empty($r['member_day_born'])): ?>
                      <div style="font-size:11px;color:#718096;">
                        <?= e($r['member_day_born']) ?> Born
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php else: ?>
                <span style="color:#a0aec0;">— unknown member —</span>
              <?php endif; ?>
            </td>
            <td><span class="badge"><?= e($r['type']) ?></span></td>
            <td>
              <?= $r['date_received']
                  ? date('jS M Y', strtotime($r['date_received']))
                  : '—' ?>
            </td>
            <td><?= e($r['minister'] ?: '—') ?></td>
            <td><?= e($r['place'] ?: '—') ?></td>
            <td>
              <?php if ($r['certificate_no']): ?>
                <span style="font-family:monospace;font-size:12px;">
                  <?= e($r['certificate_no']) ?>
                </span>
              <?php else: ?>
                <span style="color:#a0aec0;">—</span>
              <?php endif; ?>
            </td>
            <?php if ($canEdit): ?>
              <td>
                <a class="btn-sm btn-danger" data-confirm="Delete this record?"
                   href="?delete=<?= (int)$r['id'] ?>">🗑</a>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<!-- ============================================================
     GROUPED BY SACRAMENT TYPE (cards with photos)
     ============================================================ -->
<?php if ($grouped): foreach ($SACRAMENTS as $type):
  if (empty($grouped[$type])) continue;
  $list = $grouped[$type];
?>
  <div class="card">
    <h3>
      ✝️ <?= e($type) ?>
      <span class="badge" style="margin-left:8px;"><?= count($list) ?></span>
    </h3>
    <?php foreach ($list as $r):
      $memberRow = $r['member_name']
          ? ['full_name' => $r['member_name'], 'photo' => $r['member_photo']]
          : null;
    ?>
      <div class="member-card">
        <?php if ($memberRow): ?>
          <?= memberAvatar($memberRow, 44) ?>
        <?php else: ?>
          <div class="avatar">?</div>
        <?php endif; ?>
        <div class="member-info">
          <div class="name"><?= e($r['member_name'] ?? 'Unknown') ?></div>
          <div class="meta">
            <?= $r['date_received'] ? date('jS F Y', strtotime($r['date_received'])) : 'date not set' ?>
            <?= $r['minister'] ? ' · ' . e($r['minister']) : '' ?>
            <?= $r['place'] ? ' · ' . e($r['place']) : '' ?>
          </div>
        </div>
        <?php if ($r['certificate_no']): ?>
          <span class="badge"><?= e($r['certificate_no']) ?></span>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>