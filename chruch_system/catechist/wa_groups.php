<?php
require_once __DIR__ . '/_layout.php';

/* ============================================================
   WHATSAPP GROUPS MANAGER — Catechist
   ============================================================ */

/* ---------- Auto-create tables if missing ---------- */
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS wa_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        description VARCHAR(255) DEFAULT NULL,
        invite_link VARCHAR(255) DEFAULT NULL,
        audience VARCHAR(60) DEFAULT 'all',
        created_by VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS wa_group_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        member_id INT NOT NULL,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES wa_groups(id) ON DELETE CASCADE,
        FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
        UNIQUE KEY uniq_group_member (group_id, member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) { /* already exist */ }

$GROUP_TYPES = [
  'all'         => 'All members with phone',
  'today'       => 'Birthdays today',
  'sunday'      => 'Sunday Born',
  'monday'      => 'Monday Born',
  'tuesday'     => 'Tuesday Born',
  'wednesday'   => 'Wednesday Born',
  'thursday'    => 'Thursday Born',
  'friday'      => 'Friday Born',
  'saturday'    => 'Saturday Born',
  'custom'      => 'Custom (I will pick)',
];

/* ---------- Helper: audience → members ---------- */
function getAudienceMembers(PDO $pdo, $audience) {
    $where  = "";
    $params = [];

    switch ((string)$audience) {
        case 'today':
            $where    = "AND dob IS NOT NULL AND DATE_FORMAT(dob,'%m-%d') = ?";
            $params[] = 'date'('m-d');
            break;
        case 'sunday':    $where = "AND day_born = 'Sunday'";    break;
        case 'monday':    $where = "AND day_born = 'Monday'";    break;
        case 'tuesday':   $where = "AND day_born = 'Tuesday'";   break;
        case 'wednesday': $where = "AND day_born = 'Wednesday'"; break;
        case 'thursday':  $where = "AND day_born = 'Thursday'";  break;
        case 'friday':    $where = "AND day_born = 'Friday'";    break;
        case 'saturday':  $where = "AND day_born = 'Saturday'";  break;
        case 'all':
        case 'custom':
        default:
            break;
    }

    $sql = "SELECT id, full_name, phone, photo, day_born
            FROM members
            WHERE phone IS NOT NULL AND phone <> '' $where
            ORDER BY full_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : [];
}

/* ---------- Normalize phone for WhatsApp ---------- */
function ghPhone($p) {
    $p = preg_replace('/[^0-9]/', '', (string)($p ?? ''));
    if ($p === '') return '';
    if (strpos($p, '0') === 0)       return '233' . substr($p, 1);
    if (strpos($p, '233') !== 0)     return '233' . $p;
    return $p;
}

/* ============================================================
   ACTIONS
   ============================================================ */

/* ---------- Save new group ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $stmt = $pdo->prepare("INSERT INTO wa_groups
        (name, description, invite_link, audience, created_by)
        VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        post('name'),
        post('description'),
        post('invite_link'),
        post('audience') ?: 'all',
        currentUser()['name']
    ]);
    flash('✅ Group created');
    redirect('wa_groups.php?id=' . (int)$pdo->lastInsertId());
}

/* ---------- Delete group ---------- */
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM wa_groups WHERE id = ?")
        ->execute([(int)$_GET['delete']]);
    flash('🗑 Group deleted');
    redirect('wa_groups.php');
}

/* ---------- Update invite link ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_link'])) {
    $pdo->prepare("UPDATE wa_groups SET invite_link = ? WHERE id = ?")
        ->execute([post('invite_link'), (int)post('group_id')]);
    flash('✅ Invite link updated');
    redirect('wa_groups.php?id=' . (int)post('group_id'));
}

/* ---------- Add member to group ---------- */
if (isset($_GET['add_member']) && isset($_GET['gid'])) {
    try {
        $pdo->prepare("INSERT IGNORE INTO wa_group_members (group_id, member_id)
                       VALUES (?, ?)")
            ->execute([(int)$_GET['gid'], (int)$_GET['add_member']]);
        flash('✅ Member added');
    } catch (PDOException $e) {
        flash('❌ Could not add member');
    }
    redirect('wa_groups.php?id=' . (int)$_GET['gid']);
}

/* ---------- Remove member from group ---------- */
if (isset($_GET['remove_member']) && isset($_GET['gid'])) {
    $pdo->prepare("DELETE FROM wa_group_members
                   WHERE group_id = ? AND member_id = ?")
        ->execute([(int)$_GET['gid'], (int)$_GET['remove_member']]);
    flash('🗑 Member removed');
    redirect('wa_groups.php?id=' . (int)$_GET['gid']);
}

/* ============================================================
   LOAD DATA
   ============================================================ */

/* ---------- All groups ---------- */
$groups = [];
try {
    $rows = $pdo->query("
        SELECT g.*,
          (SELECT COUNT(*) FROM wa_group_members gm WHERE gm.group_id = g.id) AS group_count
        FROM wa_groups g
        ORDER BY g.id DESC
    ")->fetchAll();
    $groups = is_array($rows) ? $rows : [];
} catch (PDOException $e) {
    $groups = [];
}

/* ---------- Current group ---------- */
$currentId = (int)get('id');
$current   = null;
$inGroup   = [];
$audienceMembers = [];

if ($currentId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM wa_groups WHERE id = ?");
        $stmt->execute([$currentId]);
        $row = $stmt->fetch();

        if (is_array($row) && !empty($row['id']) && !empty($row['name'])) {
            $current = $row;
        }
    } catch (PDOException $e) {
        $current = null;
    }
}

if (is_array($current)) {
    try {
        $stmt = $pdo->prepare("SELECT member_id FROM wa_group_members WHERE group_id = ?");
        $stmt->execute([(int)$current['id']]);
        foreach ($stmt->fetchAll() as $r) {
            if (is_array($r)) $inGroup[(int)$r['member_id']] = true;
        }
    } catch (PDOException $e) {
        $inGroup = [];
    }

    $audienceMembers = getAudienceMembers($pdo, $current['audience'] ?? 'all');
    if (!is_array($audienceMembers)) $audienceMembers = [];
}
?>

<style>
.cat-wa-hero {
  background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
  color: #fff;
  border-radius: 16px;
  padding: 24px;
  margin-bottom: 18px;
  box-shadow: 0 8px 30px rgba(18,140,126,0.3);
}
.cat-wa-hero h1 { font-size: 22px; margin: 0 0 4px; font-weight: 800; }
.cat-wa-hero p  { font-size: 13px; opacity: 0.9; margin: 0; }

.cat-wa-section {
  background: #fff;
  border-radius: 14px;
  padding: 20px;
  margin-bottom: 16px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  border: 1px solid #edf2f7;
}
.cat-wa-section h3 {
  font-size: 15px;
  color: #128C7E;
  margin: 0 0 4px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 8px;
}
.cat-wa-section .subtitle {
  font-size: 12px;
  color: #a0aec0;
  margin: 0 0 16px;
  line-height: 1.5;
}

.cat-wa-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px;
  background: #f7fafc;
  border-radius: 12px;
  margin-bottom: 8px;
  border: 1px solid #edf2f7;
  transition: all 0.15s ease;
}
.cat-wa-item:hover { background: #fff; border-color: #cbd5e0; }
.cat-wa-icon {
  width: 42px; height: 42px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; flex-shrink: 0;
  background: linear-gradient(135deg, #25D366, #128C7E);
  color: #fff;
}
.cat-wa-info { flex: 1; min-width: 0; }
.cat-wa-name {
  font-size: 14px;
  font-weight: 700;
  color: #2d3748;
}
.cat-wa-meta {
  font-size: 12px;
  color: #718096;
  margin-top: 2px;
  word-break: break-all;
}
.cat-wa-actions { display: flex; gap: 4px; flex-shrink: 0; }

.cat-wa-current {
  background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
  color: #fff;
  border-radius: 14px;
  padding: 20px;
  margin-bottom: 16px;
}
.cat-wa-current h3 {
  color: #fff;
  font-size: 20px;
  margin: 0 0 6px;
  font-weight: 800;
}
.cat-wa-current .desc {
  font-size: 13px;
  opacity: 0.95;
  margin-bottom: 10px;
}
.cat-wa-current .invite {
  background: rgba(255,255,255,0.15);
  padding: 12px;
  border-radius: 10px;
  margin-top: 12px;
}
.cat-wa-current .invite .label {
  font-size: 11px;
  opacity: 0.85;
  margin-bottom: 4px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.cat-wa-current .invite .url {
  font-family: monospace;
  font-size: 13px;
  word-break: break-all;
  font-weight: 600;
}
</style>

<!-- ============================================================
     HERO
     ============================================================ -->
<div class="cat-wa-hero">
  <h1>💬 WhatsApp Groups</h1>
  <p>Create group links and invite members. Perfect for catechism classes and Sunday school.</p>
</div>

<!-- ============================================================
     CREATE NEW GROUP
     ============================================================ -->
<div class="cat-wa-section">
  <h3>➕ Create New Group</h3>
  <p class="subtitle">
    First create the group in your WhatsApp app, then paste the invite link here.
  </p>

  <form method="post">
    <div class="grid grid-2">
      <div style="grid-column:1/-1;">
        <label>Group Name *</label>
        <input name="name" required
               placeholder="e.g. Catechism Class 2026 / First Communion Group">
      </div>

      <div style="grid-column:1/-1;">
        <label>WhatsApp Invite Link</label>
        <input name="invite_link"
               placeholder="https://chat.whatsapp.com/...">
        <small style="color:#718096;font-size:11px;">
          Open WhatsApp → Group Info → Invite via link → Copy
        </small>
      </div>

      <div style="grid-column:1/-1;">
        <label>Description (optional)</label>
        <input name="description"
               placeholder="e.g. Weekly class reminders">
      </div>

      <div style="grid-column:1/-1;">
        <label>Audience</label>
        <select name="audience">
          <?php foreach ($GROUP_TYPES as $key => $label): ?>
            <option value="<?= e($key) ?>"><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <small style="color:#718096;font-size:11px;">
          Used to suggest members who should join.
        </small>
      </div>
    </div>

    <button class="btn-primary" name="save" value="1"
            style="background:#25D366;">
      💬 Create Group
    </button>
  </form>
</div>

<!-- ============================================================
     ALL GROUPS
     ============================================================ -->
<div class="cat-wa-section">
  <h3>📱 All WhatsApp Groups (<?= count($groups) ?>)</h3>

  <?php if (!$groups): ?>
    <div class="empty">No groups yet. Create one above.</div>
  <?php else: foreach ($groups as $g):
    if (!is_array($g)) continue;
  ?>
    <div class="cat-wa-item">
      <div class="cat-wa-icon">💬</div>
      <div class="cat-wa-info">
        <div class="cat-wa-name"><?= e($g['name'] ?? '') ?></div>
        <div class="cat-wa-meta">
          <?= e($GROUP_TYPES[$g['audience']] ?? ($g['audience'] ?? '')) ?>
          · <?= (int)($g['group_count'] ?? 0) ?> member<?= ($g['group_count'] ?? 0) == 1 ? '' : 's' ?> tracked
        </div>
        <?php if (!empty($g['invite_link'])): ?>
          <div class="cat-wa-meta" style="color:#38a169;">
            🔗 <?= e($g['invite_link']) ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="cat-wa-actions">
        <a class="btn-sm" style="padding:8px 14px;font-size:12px;background:#25D366;color:#fff;"
           href="?id=<?= (int)$g['id'] ?>">Open</a>
        <a class="btn-sm btn-danger" style="padding:8px 12px;font-size:12px;"
           data-confirm="Delete this group?"
           href="?delete=<?= (int)$g['id'] ?>">🗑</a>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<!-- ============================================================
     CURRENT GROUP DETAILS
     ============================================================ -->
<?php if (is_array($current)): ?>

  <div class="cat-wa-current">
    <h3>💬 <?= e($current['name']) ?></h3>

    <?php if (!empty($current['description'])): ?>
      <p class="desc"><?= e($current['description']) ?></p>
    <?php endif; ?>

    <div style="font-size:13px;opacity:0.95;">
      Audience:
      <strong><?= e($GROUP_TYPES[$current['audience']] ?? $current['audience']) ?></strong>
    </div>

    <?php if (!empty($current['invite_link'])): ?>
      <div class="invite">
        <div class="label">Invite Link</div>
        <div class="url"><?= e($current['invite_link']) ?></div>

        <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
          <a class="btn-sm" style="background:#fff;color:#128C7E;padding:8px 12px;font-size:13px;"
             href="<?= e($current['invite_link']) ?>" target="_blank" rel="noopener">
            🔗 Open Invite
          </a>
          <button class="btn-sm" style="background:#fff;color:#128C7E;padding:8px 12px;font-size:13px;"
                  onclick="copyText('<?= e($current['invite_link']) ?>')">
            📋 Copy Link
          </button>
          <a class="btn-sm" style="background:#fff;color:#128C7E;padding:8px 12px;font-size:13px;"
             target="_blank" rel="noopener"
             href="https://wa.me/?text=<?= rawurlencode('Join our WhatsApp group "' . $current['name'] . '": ' . $current['invite_link']) ?>">
            💬 Share on WhatsApp
          </a>
        </div>
      </div>
    <?php endif; ?>

    <form method="post" style="margin-top:12px;">
      <input type="hidden" name="group_id" value="<?= (int)$current['id'] ?>">
      <label style="color:#fff;font-size:12px;">Update Invite Link</label>
      <div style="display:flex;gap:6px;">
        <input name="invite_link" value="<?= e($current['invite_link'] ?? '') ?>"
               placeholder="https://chat.whatsapp.com/..."
               style="flex:1;padding:8px;font-size:13px;">
        <button class="btn-sm" style="background:#fff;color:#128C7E;padding:8px 14px;font-size:13px;"
                name="update_link" value="1">Save</button>
      </div>
    </form>
  </div>

  <!-- ============================================================
       SUGGESTED MEMBERS
       ============================================================ -->
  <div class="cat-wa-section">
    <h3>👥 Suggested Members (<?= count($audienceMembers) ?>)</h3>
    <p class="subtitle">
      Tap 💬 to invite via WhatsApp, or + Track to add to your group list.
    </p>

    <?php if (!$audienceMembers): ?>
      <div class="empty">No members match this audience yet.</div>
    <?php else: ?>
      <?php foreach ($audienceMembers as $am):
        if (!is_array($am)) continue;
        $isIn    = isset($inGroup[(int)($am['id'] ?? 0)]);
        $phone   = ghPhone($am['phone'] ?? '');
        $joinMsg = rawurlencode(
            "Hello " . ($am['full_name'] ?? '') . ",\n\n" .
            "You are invited to join our WhatsApp group \"" . $current['name'] . "\":\n" .
            (!empty($current['invite_link']) ? $current['invite_link'] : '(link coming soon)') . "\n\n" .
            "— " . ($settings['church_name'] ?? 'Station')
        );
      ?>
        <div class="cat-wa-item">
          <?= memberAvatar($am, 40) ?>
          <div class="cat-wa-info">
            <div class="cat-wa-name"><?= e($am['full_name'] ?? '') ?></div>
            <div class="cat-wa-meta">
              <?= e($am['phone'] ?? '') ?>
              <?= !empty($am['day_born']) ? ' · ' . e($am['day_born']) . ' Born' : '' ?>
            </div>
          </div>

          <?php if ($phone !== '' && !empty($current['invite_link'])): ?>
            <a class="btn-sm" style="padding:6px 12px;font-size:12px;background:#25D366;color:#fff;"
               target="_blank" rel="noopener"
               href="https://wa.me/<?= e($phone) ?>?text=<?= $joinMsg ?>">
              💬 Invite
            </a>
          <?php endif; ?>

          <?php if ($isIn): ?>
            <a class="btn-sm btn-ghost" style="padding:6px 10px;font-size:12px;"
               data-confirm="Remove from tracking?"
               href="?id=<?= (int)$current['id'] ?>&remove_member=<?= (int)$am['id'] ?>">
              ✓ Tracked
            </a>
          <?php else: ?>
            <a class="btn-sm btn-ghost" style="padding:6px 10px;font-size:12px;"
               href="?id=<?= (int)$current['id'] ?>&add_member=<?= (int)$am['id'] ?>">
              + Track
            </a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- ============================================================
       BULK INVITE LIST
       ============================================================ -->
  <?php if (!empty($current['invite_link']) && $audienceMembers):
    $phones = [];
    foreach ($audienceMembers as $am2) {
        if (!is_array($am2)) continue;
        $p = ghPhone($am2['phone'] ?? '');
        if ($p !== '') $phones[] = $p;
    }
  ?>
    <div class="cat-wa-section">
      <h3>📤 Bulk Invite List</h3>
      <p class="subtitle">
        Copy all phone numbers below into WhatsApp Broadcast.
      </p>

      <textarea readonly rows="4" id="bulkPhones"
        style="width:100%;font-family:monospace;font-size:13px;padding:10px;
               border:2px solid #e2e8f0;border-radius:8px;"><?= e(implode(', ', $phones)) ?></textarea>

      <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;">
        <button class="btn-sm" style="padding:10px 16px;background:#25D366;color:#fff;"
                onclick="copyText(document.getElementById('bulkPhones').value)">
          📋 Copy All Numbers
        </button>
        <a class="btn-sm" style="padding:10px 16px;background:#25D366;color:#fff;"
           target="_blank" rel="noopener"
           href="https://wa.me/?text=<?= rawurlencode('Join our WhatsApp group "' . $current['name'] . '": ' . $current['invite_link']) ?>">
          💬 Share Link on WhatsApp
        </a>
      </div>
    </div>
  <?php endif; ?>

<?php endif; ?>

<!-- ============================================================
     INFO NOTE
     ============================================================ -->
<div class="cat-wa-section" style="background:#f0fff4;border-left:4px solid #25D366;">
  <h3 style="color:#22543d;">💡 Tips for Catechists</h3>
  <ul style="font-size:13px;color:#22543d;line-height:1.9;padding-left:20px;margin-top:8px;">
    <li><strong>Catechism class groups</strong> — one group per class level</li>
    <li><strong>Sunday school reminders</strong> — send weekly reminders</li>
    <li><strong>Parent groups</strong> — keep parents informed of activities</li>
    <li><strong>Sacrament preparation</strong> — coordinate with students preparing for First Communion, Confirmation, etc.</li>
    <li><strong>Birthdays</strong> — celebrate children's birthdays with the class</li>
  </ul>
</div>

<!-- ============================================================
     JAVASCRIPT
     ============================================================ -->
<script>
function copyText(text) {
  const ta = document.createElement('textarea');
  ta.value = text;
  ta.style.position = 'fixed';
  ta.style.opacity = '0';
  document.body.appendChild(ta);
  ta.select();
  try {
    document.execCommand('copy');
    alert('✅ Copied!');
  } catch (e) {
    alert('Please copy manually.');
  }
  document.body.removeChild(ta);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>