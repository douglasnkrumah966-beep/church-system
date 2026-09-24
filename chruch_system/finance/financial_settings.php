<?php
require_once __DIR__ . '/_layout.php';

/* ============================================================
   FINANCIAL SETTINGS
   ------------------------------------------------------------
   View the categories currently in use across finance records.
   Church name / currency are managed in /admin/settings.php.
   ============================================================ */

/* Categories used in finance records */
try {
    $cats = $pdo->query("SELECT DISTINCT category, type FROM finances
                         ORDER BY type, category")->fetchAll();
} catch (PDOException $e) {
    $cats = [];
}

/* Category counts per type */
$incomeCount = 0;
$expenseCount = 0;
foreach ($cats as $c) {
    if ($c['type'] === 'income')  $incomeCount++;
    else                          $expenseCount++;
}

/* Category totals */
$stmt = $pdo->query("SELECT type, category, SUM(amount) total, COUNT(*) entries
                     FROM finances GROUP BY type, category
                     ORDER BY type, category");
$totals = $stmt->fetchAll();

/* Currency from settings */
$currency = $settings['currency'] ?? 'GH₵';
?>

<div class="stat-grid">
  <div class="stat" style="background:linear-gradient(135deg,#38a169,#276749);">
    <div class="num"><?= $incomeCount ?></div>
    <div class="lbl">Income Categories</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#e53e3e,#9b2c2c);">
    <div class="num"><?= $expenseCount ?></div>
    <div class="lbl">Expense Categories</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#3182ce,#2c5282);">
    <div class="num"><?= count($cats) ?></div>
    <div class="lbl">Total Categories</div>
  </div>
  <div class="stat" style="background:linear-gradient(135deg,#6b46c1,#553c9a);">
    <div class="num"><?= e($currency) ?></div>
    <div class="lbl">Currency</div>
  </div>
</div>

<!-- Info card -->
<div class="card" style="margin-top:16px;">
  <h3>⚙️ Station Settings</h3>
  <p style="color:#718096;font-size:13px;margin-bottom:14px;">
    The station name and currency are managed centrally by the System Administrator.
  </p>

  <div class="table-wrap"><table>
    <tr>
      <td style="color:#718096;">Station Name</td>
      <td><strong><?= e($settings['church_name'] ?? 'Church') ?></strong></td>
    </tr>
    <tr>
      <td style="color:#718096;">Currency</td>
      <td><strong><?= e($currency) ?></strong></td>
    </tr>
  </table></div>

  <?php if (in_array(currentUser()['role'], ['admin','sysadmin'], true)): ?>
    <div style="margin-top:14px;">
      <a class="btn-sm btn-ghost" href="../admin/settings.php">
        ⚙️ Open System Settings
      </a>
    </div>
  <?php endif; ?>
</div>

<!-- Categories in use -->
<div class="card">
  <h3>📋 Categories Currently in Use</h3>
  <?php if (!$cats): ?>
    <div class="empty">No categories recorded yet. They will appear here once contributions or expenses are entered.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <tr><th>Category</th><th>Type</th></tr>
      <?php foreach ($cats as $c): ?>
        <tr>
          <td><?= e($c['category']) ?></td>
          <td>
            <span class="badge <?= $c['type']==='income'?'green':'orange' ?>">
              <?= e($c['type']) ?>
            </span>
          </td>
        </tr>
      <?php endforeach; ?>
    </table></div>
  <?php endif; ?>
</div>

<!-- Category totals -->
<?php if ($totals): ?>
<div class="card">
  <h3>📊 Totals by Category</h3>
  <div class="table-wrap"><table>
    <tr>
      <th>Category</th>
      <th>Type</th>
      <th>Entries</th>
      <th style="text-align:right;">Total</th>
    </tr>
    <?php foreach ($totals as $t): ?>
      <tr>
        <td><?= e($t['category']) ?></td>
        <td>
          <span class="badge <?= $t['type']==='income'?'green':'orange' ?>">
            <?= e($t['type']) ?>
          </span>
        </td>
        <td><?= (int)$t['entries'] ?></td>
        <td style="text-align:right;"><strong><?= money($t['total']) ?></strong></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php endif; ?>

<!-- Category reference -->
<div class="card">
  <h3>📖 Category Reference</h3>
  <p style="color:#718096;font-size:13px;margin-bottom:14px;">
    These are the built-in categories the system uses. They appear in dropdowns across the finance pages.
  </p>

  <div style="background:#f0fff4;border-left:4px solid #38a169;
              padding:14px;border-radius:10px;margin-bottom:12px;">
    <div style="font-weight:700;color:#22543d;margin-bottom:8px;">
      💚 Income Categories
    </div>
    <div style="font-size:13px;color:#22543d;line-height:1.9;">
      <strong>Collections:</strong> First Collection · Day Born Collection<br>
      <strong>Contributions:</strong> <?= e(implode(' · ', CONTRIBUTION_TYPES)) ?>
    </div>
  </div>

  <div style="background:#fff5f5;border-left:4px solid #e53e3e;
              padding:14px;border-radius:10px;">
    <div style="font-weight:700;color:#742a2a;margin-bottom:8px;">
      ❤️ Expense Categories
    </div>
    <div style="font-size:13px;color:#742a2a;line-height:1.9;">
      <?= e(implode(' · ', EXPENSE_TYPES)) ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>