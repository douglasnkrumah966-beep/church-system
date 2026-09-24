<?php
/* ============================================================
   ROLES MANAGEMENT
   ------------------------------------------------------------
   All POST/GET handling and redirects MUST happen BEFORE
   including _layout.php (which prints HTML).
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
   BUILT-IN ROLES (cannot be deleted)
   ============================================================ */
$BUILT_IN = ['sysadmin','admin','secretary','finance','catechist','member'];

/* ============================================================
   ADD ROLE
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $name = strtolower(trim(post('name')));
    $name = preg_replace('/[^a-z0-9_]/', '', $name);

    if ($name === '') {
        flash('❌ Please enter a valid role name (lowercase letters, numbers, underscore)');
        redirect('roles.php');
    }

    if (in_array($name, $BUILT_IN, true)) {
        flash('❌ "' . $name . '" is a built-in role and already exists');
        redirect('roles.php');
    }

    try {
        $pdo->prepare("INSERT INTO roles (name, description) VALUES (?, ?)")
            ->execute([$name, post('description')]);
        flash('✅ Role added: ' . $name);
        logAction($pdo, 'add_role', $name);
    } catch (PDOException $e) {
        flash('❌ Role already exists');
    }
    redirect('roles.php');
}

/* ============================================================
   DELETE ROLE
   ============================================================ */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    try {
        $stmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            flash('❌ Role not found');
        } elseif (in_array($row['name'], $BUILT_IN, true)) {
            flash('❌ Built-in roles cannot be deleted');
        } else {
            /* Check if any users have this role */
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
            $stmt->execute([$row['name']]);
            $count = (int)$stmt->fetchColumn();

            if ($count > 0) {
                flash('❌ Cannot delete — ' . $count . ' user(s) still use this role');
            } else {
                $pdo->prepare("DELETE FROM roles WHERE id = ?")->execute([$id]);
                flash('🗑 Role deleted: ' . $row['name']);
                logAction($pdo, 'delete_role', $row['name']);
            }
        }
    } catch (PDOException $e) {
        flash('❌ Could not delete role');
    }
    redirect('roles.php');
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
    $rows = $pdo->query("
        SELECT r.*,
          (SELECT COUNT(*) FROM users u WHERE u.role = r.name) AS user_count,
          (SELECT COUNT(*) FROM permissions p WHERE p.role_id = r.id) AS perm_count
        FROM roles r
        ORDER BY r.id
    ")->fetchAll();
    $roles = is_array($rows) ? $rows : [];
} catch (PDOException $e) {
    $roles = [];
}

/* ---------- Role reference guide ---------- */
$roleGuide = [
    'sysadmin'  => ['🔧 System Administrator',  '#1a365d',
                    'Technical only — users, backups, logs, security, maintenance'],
    'admin'     => ['✝️ Station Administrator', '#805ad5',
                    'Full station operations — members, sacraments, finances, reports'],
    'secretary' => ['✍️ Station Secretary',     '#3182ce',
                    'Members, sacraments, day born, attendance, WhatsApp'],
    'finance'   => ['💰 Finance Officer',        '#38a169',
                    'Collections, contributions, expenses, cashbook, reports'],
    'catechist' => ['📖 Catechist',              '#dd6b20',
                    'Sacraments and attendance'],
    'member'    => ['🙏 Member',                 '#718096',
                    'Personal dashboard, profile, sacraments, giving'],
];
?>

<style>
.rl-hero {
  background: linear-gradient(135deg,#1a365d 0%,#2c5282 60%,#4299e1 100%);
  color: #fff; border-radius: 16px; padding: 24px;
  margin-bottom: 18px; box-shadow: 0 8px 30px rgba(26,54,93,0.25);
}
.rl-hero h1 { font-size: 22px; margin: 0 0 4px; font-weight: 800; }
.rl-hero p  { font-size: 13px; opacity: 0.9; margin: 0; }

.rl-section {
  background: #fff; border-radius: 14px; padding: 20px; margin-bottom: 16px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #edf2f7;
}
.rl-section h3 {
  font-size: 15px; color: #1a365d; margin: 0 0 4px; font-weight: 700;
  display: flex; align-items: center; gap: 8px;
}
.rl-section .subtitle {
  font-size: 12px; color: #a0aec0; margin: 0 0 16px; line-height: 1.5;
}

.rl-item {
  display: flex; align-items: center; gap: 12px;
  padding: 14px; background: #f7fafc; border-radius: 12px;
  margin-bottom: 8px; border: 1px solid #edf2f7;
  border-left: 4px solid #cbd5e0;
  transition: all 0.15s ease;
}
.rl-item:hover { background: #fff; border-color: #cbd5e0; }
.rl-item.builtin { background: #ebf8ff; border-left-color: #3182ce; }

.rl-icon {
  width: 42px; height: 42px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; flex-shrink: 0; color: #fff;
}
.rl-info { flex: 1; min-width: 0; }
.rl-name {
  font-size: 14px; font-weight: 700; color: #2d3748;
}
.rl-meta { font-size: 12px; color: #718096; margin-top: 2px; }
.rl-actions { display: flex; gap: 4px; flex-shrink: 0; }

.rl-badge {
  display: inline-block; padding: 3px 10px; border-radius: 999px;
  font-size: 11px; font-weight: 600;
  background: #e2e8f0; color: #4a5568;
}
.rl-badge.builtin { background: #bee3f8; color: #1a365d; }
.rl-badge.count   { background: #c6f6d5; color: #22543d; }
</style>

<!-- ============================================================
     HERO
     ============================================================ -->
<div class="rl-hero">
  <h1>🎭 Roles</h1>
  <p>Manage the roles that control what each user can access in the system.</p>
</div>

<!-- ============================================================
     ADD ROLE
     ============================================================ -->
<div class="rl-section">
  <h3>➕ Add Custom Role</h3>
  <p class="subtitle">
    Create additional roles (e.g. "usher", "choir_leader").
    Built-in roles cannot be deleted.
  </p>

  <form method="post">
    <div class="grid grid-2">
      <div>
        <label>Role Name *</label>
        <input name="name" required placeholder="e.g. usher">
        <small style="color:#718096;font-size:11px;">
          Lowercase letters, numbers, and underscore only.
        </small>
      </div>
      <div>
        <label>Description</label>
        <input name="description" placeholder="e.g. Station ushers">
      </div>
    </div>

    <div style="margin-top:16px;text-align:right;">
      <button class="btn-sm btn-primary" name="save" value="1"
              style="padding:12px 24px;font-size:14px;">
        ➕ Add Role
      </button>
    </div>
  </form>
</div>

<!-- ============================================================
     ALL ROLES
     ============================================================ -->
<div class="rl-section">
  <h3>
    🎭 All Roles
    <span class="badge" style="margin-left:6px;"><?= count($roles) ?></span>
  </h3>
  <p class="subtitle">
    Each role below shows how many users and permissions it has.
  </p>

  <?php if (!$roles): ?>
    <div class="empty">No roles found. Run the database migration to create the built-in roles.</div>
  <?php else: foreach ($roles as $r):
    $isBuiltIn = in_array($r['name'], $BUILT_IN, true);
    $guide = $roleGuide[$r['name']] ?? null;
    $color = $guide[1] ?? '#718096';
    $icon  = $guide ? mb_substr($guide[0], 0, 1) : '🎭';
  ?>
    <div class="rl-item <?= $isBuiltIn ? 'builtin' : '' ?>"
         style="border-left-color: <?= $color ?>;">
      <div class="rl-icon" style="background: <?= $color ?>;">
        <?= e($icon) ?>
      </div>
      <div class="rl-info">
        <div class="rl-name">
          <?= e($r['name']) ?>
          <?php if ($isBuiltIn): ?>
            <span class="rl-badge builtin" style="margin-left:6px;">built-in</span>
          <?php endif; ?>
        </div>
        <div class="rl-meta">
          <?= e($r['description'] ?: 'No description') ?>
        </div>
        <div class="rl-meta" style="margin-top:4px;">
          <span class="rl-badge count">
            👥 <?= (int)$r['user_count'] ?> user<?= $r['user_count']==1?'':'s' ?>
          </span>
          <span class="rl-badge count" style="margin-left:4px;">
            🔑 <?= (int)$r['perm_count'] ?> permission<?= $r['perm_count']==1?'':'s' ?>
          </span>
        </div>
      </div>
      <div class="rl-actions">
        <a class="btn-sm btn-ghost" style="padding:8px 12px;font-size:12px;"
           href="permissions.php?role_id=<?= (int)$r['id'] ?>"
           title="Edit permissions">🔑</a>
        <?php if (!$isBuiltIn): ?>
          <a class="btn-sm btn-danger" style="padding:8px 12px;font-size:12px;"
             data-confirm="Delete role &quot;<?= e($r['name']) ?>&quot;?"
             href="?delete=<?= (int)$r['id'] ?>"
             title="Delete">🗑</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<!-- ============================================================
     BUILT-IN ROLES REFERENCE
     ============================================================ -->
<div class="rl-section" style="background:linear-gradient(135deg,#ebf8ff,#fff);
                                border-left:4px solid #3182ce;">
  <h3 style="color:#2c5282;">ℹ️ Built-in Roles</h3>
  <p class="subtitle" style="color:#2c5282;">
    These roles are protected and cannot be removed. Each has a specific purpose in the system.
  </p>

  <div style="display:grid;gap:10px;">
    <?php foreach ($roleGuide as $key => $info): ?>
      <div style="display:flex;gap:12px;align-items:center;padding:12px;
                  background:#fff;border-radius:10px;border-left:4px solid <?= $info[1] ?>;">
        <div style="flex:1;">
          <div style="font-weight:700;font-size:14px;color:<?= $info[1] ?>;">
            <?= e($info[0]) ?>
          </div>
          <div style="font-size:12px;color:#718096;margin-top:2px;">
            <?= e($info[2]) ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ============================================================
     INFO NOTE
     ============================================================ -->
<div class="rl-section" style="background:#fffaf0;border-left:4px solid #dd6b20;">
  <h3 style="color:#7b341e;">⚠️ About Custom Roles</h3>
  <p style="font-size:13px;color:#7b341e;line-height:1.7;">
    Custom roles you create here are stored in the database, but they do
    <strong>not automatically grant access</strong> to any page.
    To give a custom role access to specific pages, you'll need to update
    the <code>requireRole([...])</code> checks in those page files,
    or use the <a href="permissions.php" style="color:#9c4221;font-weight:600;">Permissions</a>
    page to assign fine-grained permissions.
  </p>
  <p style="font-size:12px;color:#7b341e;margin-top:8px;">
    For most stations, the six built-in roles are enough.
  </p>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>