<?php
/* ============================================================
   PERMISSIONS MANAGER
   ------------------------------------------------------------
   All POST handling and redirects happen BEFORE _layout.php.
   ============================================================ */

require_once __DIR__ . '/../includes/auth.php';
requireSysadmin();

$u = currentUser();

/* ============================================================
   ENSURE activity_logs TABLE EXISTS
   ============================================================ */
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        username VARCHAR(50) DEFAULT NULL,
        action VARCHAR(80) NOT NULL,
        details VARCHAR(500) DEFAULT NULL,
        ip VARCHAR(45) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {}

/* ---------- Helper ---------- */
function logAction(PDO $pdo, string $action, string $details): void {
    try {
        $u = currentUser();
        $pdo->prepare("INSERT INTO activity_logs (user_id, username, action, details, ip)
                       VALUES (?,?,?,?,?)")
            ->execute([
                $u['id'] ?? null,
                $u['username'] ?? null,
                $action,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
    } catch (PDOException $e) {}
}

/* ============================================================
   AVAILABLE PERMISSIONS
   ============================================================ */
$ALL_PERMISSIONS = [
    'members.view'    => ['View members',       '👁'],
    'members.edit'    => ['Add / edit members', '✏️'],
    'members.delete'  => ['Delete members',     '🗑'],
    'sacraments.view' => ['View sacraments',    '👁'],
    'sacraments.edit' => ['Add / edit sacraments','✏️'],
    'dayborn.view'    => ['View Day Born',      '🎂'],
    'finance.view'    => ['View finances',      '👁'],
    'finance.edit'    => ['Edit finances',      '✏️'],
    'cashbook.view'   => ['View cashbook',      '📖'],
    'reports.view'    => ['View reports',       '📊'],
    'attendance.edit' => ['Manage attendance',  '📋'],
    'messages.send'   => ['Send bulk messages', '✉️'],
    'users.manage'    => ['Manage users',       '👥'],
    'system.settings' => ['System settings',    '⚙️'],
];

/* ============================================================
   SAVE PERMISSIONS
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $roleId = (int)post('role_id');

    if ($roleId <= 0) {
        flash('❌ Invalid role');
        redirect('permissions.php');
    }

    try {
        /* Clear existing */
        $pdo->prepare("DELETE FROM permissions WHERE role_id = ?")->execute([$roleId]);

        /* Insert selected */
        $selected = $_POST['perms'] ?? [];
        if (is_array($selected) && count($selected) > 0) {
            $ins = $pdo->prepare("INSERT INTO permissions (role_id, permission) VALUES (?,?)");
            foreach ($selected as $perm) {
                if (isset($ALL_PERMISSIONS[$perm])) {
                    $ins->execute([$roleId, $perm]);
                }
            }
        }

        flash('✅ Permissions saved');
        logAction($pdo, 'permissions_update', 'Role #' . $roleId);
    } catch (PDOException $e) {
        flash('❌ Could not save permissions');
    }
    redirect('permissions.php?role_id=' . $roleId);
}

/* ============================================================
   NOW include layout (HTML output starts here)
   ============================================================ */
require_once __DIR__ . '/_layout.php';

/* ============================================================
   LOAD ROLES
   ============================================================ */
$roles = [];
try {
    $rows = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll();
    $roles = is_array($rows) ? $rows : [];
} catch (PDOException $e) { $roles = []; }

/* Selected role — default to first role or ?role_id=X */
$selectedRoleId = (int)get('role_id');
if (!$selectedRoleId && !empty($roles)) {
    $selectedRoleId = (int)$roles[0]['id'];
}

/* Load permissions for selected role */
$currentPerms = [];
$selectedRole = null;
if ($selectedRoleId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT permission FROM permissions WHERE role_id = ?");
        $stmt->execute([$selectedRoleId]);
        foreach ($stmt->fetchAll() as $p) {
            $currentPerms[$p['permission']] = true;
        }

        $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$selectedRoleId]);
        $selectedRole = $stmt->fetch();
    } catch (PDOException $e) {}
}

/* Count per role */
$roleCounts = [];
try {
    foreach ($pdo->query("SELECT role_id, COUNT(*) c FROM permissions GROUP BY role_id") as $r) {
        $roleCounts[(int)$r['role_id']] = (int)$r['c'];
    }
} catch (PDOException $e) {}
?>

<style>
.pm-hero {
  background: linear-gradient(135deg,#1a365d 0%,#2c5282 60%,#4299e1 100%);
  color:#fff; border-radius:16px; padding:24px;
  margin-bottom:18px; box-shadow:0 8px 30px rgba(26,54,93,0.25);
}
.pm-hero h1 { font-size:22px; margin:0 0 4px; font-weight:800; }
.pm-hero p  { font-size:13px; opacity:0.9; margin:0; }

.pm-section {
  background:#fff; border-radius:14px; padding:20px; margin-bottom:16px;
  box-shadow:0 2px 8px rgba(0,0,0,0.05); border:1px solid #edf2f7;
}
.pm-section h3 {
  font-size:15px; color:#1a365d; margin:0 0 4px; font-weight:700;
  display:flex; align-items:center; gap:8px;
}
.pm-section .subtitle {
  font-size:12px; color:#a0aec0; margin:0 0 16px; line-height:1.5;
}

.role-tabs {
  display:flex; flex-wrap:wrap; gap:6px; margin-bottom:14px;
}
.role-tab {
  padding:10px 16px; border-radius:10px; font-size:13px; font-weight:600;
  text-decoration:none; background:#f7fafc; color:#4a5568;
  border:2px solid transparent; transition:all 0.15s ease;
}
.role-tab:hover { background:#edf2f7; }
.role-tab.active {
  background:#1a365d; color:#fff; border-color:#1a365d;
}
.role-tab .count {
  display:inline-block; margin-left:6px; padding:1px 8px;
  background:rgba(0,0,0,0.1); border-radius:999px;
  font-size:11px; font-weight:700;
}
.role-tab.active .count {
  background:rgba(255,255,255,0.25);
}

.perm-grid {
  display:grid; grid-template-columns:1fr; gap:8px;
}
@media (min-width:700px) { .perm-grid { grid-template-columns:1fr 1fr; } }

.perm-item {
  display:flex; align-items:center; gap:12px;
  padding:14px 16px; background:#f7fafc; border-radius:10px;
  cursor:pointer; border:2px solid transparent;
  transition:all 0.15s ease;
}
.perm-item:hover { background:#fff; border-color:#cbd5e0; }
.perm-item.checked { background:#ebf8ff; border-color:#90cdf4; }
.perm-item input[type="checkbox"] { display:none; }
.perm-check {
  width:24px; height:24px; border-radius:6px;
  border:2px solid #cbd5e0; background:#fff; flex-shrink:0;
  display:flex; align-items:center; justify-content:center;
  font-size:14px; color:#fff; transition:all 0.15s ease;
}
.perm-item.checked .perm-check {
  background:#3182ce; border-color:#3182ce;
}
.perm-item.checked .perm-check::after { content:'✓'; font-weight:700; }
.perm-body { flex:1; min-width:0; }
.perm-name {
  font-size:14px; font-weight:600; color:#2d3748;
  display:flex; align-items:center; gap:6px;
}
.perm-key {
  font-family:monospace; font-size:11px; color:#718096;
  margin-top:2px;
}

.bulk-actions {
  display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px;
}
.bulk-btn {
  padding:8px 14px; font-size:12px; font-weight:600;
  border-radius:8px; background:#edf2f7; color:#4a5568;
  cursor:pointer; border:none; text-decoration:none;
}
.bulk-btn:hover { background:#cbd5e0; }

.save-bar {
  position:sticky; bottom:12px; background:#fff;
  padding:14px; border-radius:14px;
  box-shadow:0 -6px 20px rgba(0,0,0,0.08);
  display:flex; justify-content:flex-end; gap:10px;
  margin-top:20px; z-index:10;
}
@media (max-width:500px) {
  .save-bar { flex-direction:column; }
  .save-bar button { width:100%; }
}
</style>

<!-- ============================================================
     HERO
     ============================================================ -->
<div class="pm-hero">
  <h1>🔑 Permissions</h1>
  <p>Assign fine-grained permissions to each role. These control what users can see and do.</p>
</div>

<!-- ============================================================
     ROLE SELECTOR
     ============================================================ -->
<div class="pm-section">
  <h3>🎭 Select a Role</h3>
  <p class="subtitle">
    Choose a role to edit its permissions. Tap any permission to toggle it.
  </p>

  <?php if (!$roles): ?>
    <div class="empty">
      No roles found.
      <a href="roles.php" style="color:#3182ce;">Add roles →</a>
    </div>
  <?php else: ?>
    <div class="role-tabs">
      <?php foreach ($roles as $r):
        $count = $roleCounts[(int)$r['id']] ?? 0;
        $isActive = ($r['id'] == $selectedRoleId);
      ?>
        <a class="role-tab <?= $isActive ? 'active' : '' ?>"
           href="?role_id=<?= (int)$r['id'] ?>">
          <?= e(roleLabel($r['name'])) ?>
          <span class="count"><?= $count ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ============================================================
     PERMISSIONS GRID
     ============================================================ -->
<?php if ($selectedRole): ?>
<form method="post">
  <input type="hidden" name="role_id" value="<?= (int)$selectedRoleId ?>">

  <div class="pm-section">
    <h3>
      ✔️ Permissions for <?= e(roleLabel($selectedRole['name'])) ?>
      <span class="badge" style="margin-left:6px;">
        <?= count($currentPerms) ?> / <?= count($ALL_PERMISSIONS) ?>
      </span>
    </h3>
    <p class="subtitle">
      Tap any item to grant or revoke permission. Changes take effect on next page load.
    </p>

    <!-- Bulk actions -->
    <div class="bulk-actions">
      <button type="button" class="bulk-btn" onclick="toggleAll(true)">✓ Select All</button>
      <button type="button" class="bulk-btn" onclick="toggleAll(false)">✕ Clear All</button>
    </div>

    <div class="perm-grid">
      <?php foreach ($ALL_PERMISSIONS as $key => $info):
        [$label, $icon] = $info;
        $checked = isset($currentPerms[$key]);
      ?>
        <label class="perm-item <?= $checked ? 'checked' : '' ?>">
          <input type="checkbox" name="perms[]" value="<?= e($key) ?>"
                 <?= $checked ? 'checked' : '' ?>
                 onchange="this.parentElement.classList.toggle('checked', this.checked)">
          <div class="perm-check"></div>
          <div class="perm-body">
            <div class="perm-name">
              <span><?= e($icon) ?></span>
              <span><?= e($label) ?></span>
            </div>
            <div class="perm-key"><?= e($key) ?></div>
          </div>
        </label>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="save-bar">
    <button class="btn-sm btn-primary" name="save" value="1"
            style="padding:14px 28px;font-size:14px;">
      💾 Save Permissions
    </button>
  </div>
</form>
<?php else: ?>
  <div class="pm-section">
    <div class="empty">Select a role above to manage its permissions.</div>
  </div>
<?php endif; ?>

<!-- ============================================================
     INFO NOTE
     ============================================================ -->
<div class="pm-section" style="background:#ebf8ff;border-left:4px solid #3182ce;">
  <h3 style="color:#2c5282;">ℹ️ How Permissions Work</h3>
  <p style="font-size:13px;color:#2c5282;line-height:1.7;">
    These permissions are stored in the <code>permissions</code> table.
    They are <strong>informational</strong> until page code checks them — the actual
    access control still comes from each page's <code>requireRole([...])</code> call.
  </p>
  <p style="font-size:12px;color:#2c5282;margin-top:8px;">
    For most stations, the <strong>built-in roles</strong> already have the
    correct access. Use this page when you create custom roles.
  </p>
</div>

<!-- ============================================================
     JAVASCRIPT
     ============================================================ -->
<script>
function toggleAll(state) {
  document.querySelectorAll('input[name="perms[]"]').forEach(cb => {
    cb.checked = state;
    cb.parentElement.classList.toggle('checked', state);
  });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>