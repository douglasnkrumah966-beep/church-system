<?php
/* ============================================================
   USERS MANAGEMENT
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

/* ============================================================
   HELPER — log activity
   ============================================================ */
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
   CREATE USER
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $pass = $_POST['password'] ?: 'password@123';
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $role = post('role');
    $memberId = (int)post('member_id') ?: null;

    /* Only link member when role = member */
    if ($role !== 'member') $memberId = null;

    /* Validate role */
    $validRoles = ['sysadmin','admin','secretary','finance','catechist','member'];
    if (!in_array($role, $validRoles, true)) {
        flash('❌ Invalid role');
        redirect('users.php');
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO users
            (username, password, full_name, role, member_id)
            VALUES (?,?,?,?,?)");
        $stmt->execute([
            post('username'), $hash, post('full_name'), $role, $memberId
        ]);
        flash('✅ User created — default password: password@123');
        logAction($pdo, 'create_user', post('username') . ' (' . roleLabel($role) . ')');
    } catch (PDOException $e) {
        flash('❌ That username is already taken');
    }
    redirect('users.php');
}

/* ============================================================
   CHANGE ROLE
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    $targetId = (int)post('user_id');
    $newRole  = post('role');

    if ($targetId === (int)currentUser()['id'] && $newRole !== 'sysadmin') {
        flash('❌ You cannot change your own role');
        redirect('users.php');
    }

    $validRoles = ['sysadmin','admin','secretary','finance','catechist','member'];
    if (!in_array($newRole, $validRoles, true)) {
        flash('❌ Invalid role');
        redirect('users.php');
    }

    try {
        $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")
            ->execute([$newRole, $targetId]);
        flash('✅ Role updated to ' . roleLabel($newRole));
        logAction($pdo, 'change_role', 'User #' . $targetId . ' → ' . $newRole);
    } catch (PDOException $e) {
        flash('❌ Could not update role');
    }
    redirect('users.php');
}

/* ============================================================
   RESET PASSWORD
   ============================================================ */
if (isset($_GET['reset'])) {
    $id = (int)$_GET['reset'];
    $hash = password_hash('password@123', PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
        ->execute([$hash, $id]);
    flash('🔑 Password reset to password@123');
    logAction($pdo, 'reset_password', 'User #' . $id);
    redirect('users.php');
}

/* ============================================================
   LINK / UNLINK MEMBER
   ============================================================ */
if (isset($_GET['link']) && isset($_GET['member_id'])) {
    $uid = (int)$_GET['link'];
    $mid = (int)$_GET['member_id'] ?: null;
    $pdo->prepare("UPDATE users SET member_id = ? WHERE id = ?")
        ->execute([$mid, $uid]);
    flash($mid ? '✅ Member linked' : '✅ Member unlinked');
    logAction($pdo, 'link_member', 'User #' . $uid . ' → Member #' . ($mid ?? 'none'));
    redirect('users.php');
}

/* ============================================================
   DELETE USER
   ============================================================ */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id === (int)currentUser()['id']) {
        flash('❌ You cannot delete your own account');
    } else {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        flash('🗑 User deleted');
        logAction($pdo, 'delete_user', 'User #' . $id);
    }
    redirect('users.php');
}

/* ============================================================
   NOW include the layout (HTML output starts here)
   ============================================================ */
require_once __DIR__ . '/_layout.php';

/* ============================================================
   LOAD DATA
   ============================================================ */
$filterRole = get('role');
$where = ""; $params = [];

if ($filterRole && in_array($filterRole, ['sysadmin','admin','secretary','finance','catechist','member'], true)) {
    $where = "WHERE u.role = ?";
    $params[] = $filterRole;
}

$stmt = $pdo->prepare("
    SELECT u.*, m.full_name AS member_name
    FROM users u
    LEFT JOIN members m ON m.id = u.member_id
    $where
    ORDER BY
      FIELD(u.role, 'sysadmin','admin','secretary','finance','catechist','member'),
      u.username
");
$stmt->execute($params);
$users = $stmt->fetchAll();
if (!is_array($users)) $users = [];

$members = [];
try {
    $rows = $pdo->query("SELECT id, full_name FROM members ORDER BY full_name")->fetchAll();
    $members = is_array($rows) ? $rows : [];
} catch (PDOException $e) {}

/* Counts by role */
$roleCounts = ['sysadmin'=>0,'admin'=>0,'secretary'=>0,'finance'=>0,'catechist'=>0,'member'=>0];
try {
    foreach ($pdo->query("SELECT role, COUNT(*) c FROM users GROUP BY role") as $r) {
        if (isset($roleCounts[$r['role']])) $roleCounts[$r['role']] = (int)$r['c'];
    }
} catch (PDOException $e) {}
$totalUsers = array_sum($roleCounts);
?>

<style>
.us-hero {
  background: linear-gradient(135deg,#1a365d 0%,#2c5282 60%,#4299e1 100%);
  color:#fff; border-radius:16px; padding:24px;
  margin-bottom:18px; box-shadow:0 8px 30px rgba(26,54,93,0.25);
}
.us-hero h1 { font-size:22px; margin:0 0 4px; font-weight:800; }
.us-hero p  { font-size:13px; opacity:0.9; margin:0; }

.us-tiles {
  display: grid; grid-template-columns: repeat(2, 1fr); gap:10px;
  margin-bottom:18px;
}
@media (min-width:700px) { .us-tiles { grid-template-columns: repeat(3, 1fr); } }
@media (min-width:960px) { .us-tiles { grid-template-columns: repeat(6, 1fr); } }
.us-tile {
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  padding:14px 10px; background:#fff; border-radius:12px; text-decoration:none;
  box-shadow:0 2px 8px rgba(0,0,0,0.05); border:2px solid #edf2f7;
  transition: all 0.15s ease;
}
.us-tile:hover { border-color:#3182ce; transform:translateY(-2px); }
.us-tile.active { border-color:#3182ce; background:#ebf8ff; }
.us-tile .ic  { font-size:22px; margin-bottom:4px; }
.us-tile .n   { font-size:22px; font-weight:800; color:#2c5282; line-height:1; }
.us-tile .lbl { font-size:10px; color:#718096; text-transform:uppercase;
                letter-spacing:0.4px; margin-top:4px; text-align:center; }

.us-section, .form-card {
  background:#fff; border-radius:14px; padding:20px; margin-bottom:16px;
  box-shadow:0 2px 8px rgba(0,0,0,0.05); border:1px solid #edf2f7;
}
.us-section h3, .form-card h3 {
  font-size:15px; color:#1a365d; margin:0 0 4px; font-weight:700;
  display:flex; align-items:center; gap:8px;
}
.us-section .subtitle, .form-card .subtitle {
  font-size:12px; color:#a0aec0; margin:0 0 16px; line-height:1.5;
}

.us-user {
  display:flex; align-items:center; gap:12px;
  padding:14px; background:#f7fafc; border-radius:12px;
  margin-bottom:8px; border:1px solid #edf2f7;
  transition: all 0.15s ease;
}
.us-user:hover { background:#fff; border-color:#cbd5e0; }
.us-user.self { background:#ebf8ff; border-color:#90cdf4; }
.us-avatar {
  width:42px; height:42px; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  color:#fff; font-weight:700; font-size:16px; flex-shrink:0;
}
.us-info { flex:1; min-width:0; }
.us-name { font-size:14px; font-weight:700; color:#2d3748; }
.us-meta { font-size:12px; color:#718096; margin-top:2px; }
.us-meta code {
  background:#fff; padding:1px 6px; border-radius:4px;
  font-size:11px; color:#1a365d; font-weight:600;
}
.us-actions { display:flex; gap:4px; flex-shrink:0; flex-wrap:wrap; }

.role-pill {
  display:inline-flex; align-items:center; gap:4px;
  padding:2px 8px; border-radius:999px; font-size:11px; font-weight:600;
}
</style>

<!-- ============================================================
     HERO
     ============================================================ -->
<div class="us-hero">
  <h1>👥 User Accounts</h1>
  <p>Manage who can sign in, what role they hold, and their access level.</p>
</div>

<!-- ============================================================
     ROLE FILTER TILES
     ============================================================ -->
<div class="us-tiles">
  <a class="us-tile <?= $filterRole==='' ? 'active' : '' ?>" href="users.php">
    <div class="ic">👥</div>
    <div class="n"><?= $totalUsers ?></div>
    <div class="lbl">All Users</div>
  </a>
  <a class="us-tile <?= $filterRole==='sysadmin' ? 'active' : '' ?>" href="?role=sysadmin">
    <div class="ic">🔧</div>
    <div class="n"><?= $roleCounts['sysadmin'] ?></div>
    <div class="lbl">System Admins</div>
  </a>
  <a class="us-tile <?= $filterRole==='admin' ? 'active' : '' ?>" href="?role=admin">
    <div class="ic">✝️</div>
    <div class="n"><?= $roleCounts['admin'] ?></div>
    <div class="lbl">Station Admins</div>
  </a>
  <a class="us-tile <?= $filterRole==='secretary' ? 'active' : '' ?>" href="?role=secretary">
    <div class="ic">✍️</div>
    <div class="n"><?= $roleCounts['secretary'] ?></div>
    <div class="lbl">Secretaries</div>
  </a>
  <a class="us-tile <?= $filterRole==='finance' ? 'active' : '' ?>" href="?role=finance">
    <div class="ic">💰</div>
    <div class="n"><?= $roleCounts['finance'] ?></div>
    <div class="lbl">Finance</div>
  </a>
  <a class="us-tile <?= $filterRole==='member' ? 'active' : '' ?>" href="?role=member">
    <div class="ic">🙏</div>
    <div class="n"><?= $roleCounts['member'] ?></div>
    <div class="lbl">Members</div>
  </a>
</div>

<!-- ============================================================
     CREATE USER
     ============================================================ -->
<div class="form-card">
  <h3>➕ Create New User</h3>
  <p class="subtitle">
    Fill in the details. If you leave the password empty, the default
    <code>password@123</code> will be used.
  </p>

  <form method="post">
    <div class="grid grid-2">
      <div>
        <label>Full Name *</label>
        <input name="full_name" required placeholder="e.g. Mary Adjei">
      </div>
      <div>
        <label>Username *</label>
        <input name="username" required placeholder="e.g. mary.adjei">
        <small style="color:#718096;font-size:11px;">
          Lowercase, no spaces. For members, use their phone number.
        </small>
      </div>
      <div>
        <label>Password</label>
        <input name="password" placeholder="Leave blank for default">
      </div>
      <div>
        <label>Role *</label>
        <select name="role" id="roleSelect" onchange="toggleMemberPicker()">
          <option value="admin">✝️ Station Administrator</option>
          <option value="secretary">✍️ Station Secretary</option>
          <option value="finance">💰 Finance Officer</option>
          <option value="catechist">📖 Catechist</option>
          <option value="member" selected>🙏 Member</option>
          <option value="sysadmin">🔧 System Administrator</option>
        </select>
      </div>
      <div id="memberPicker" style="grid-column:1/-1;">
        <label>Link to Member Record</label>
        <select name="member_id">
          <option value="">— Choose a member (required for Member role) —</option>
          <?php foreach ($members as $m): ?>
            <option value="<?= (int)$m['id'] ?>"><?= e($m['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div style="margin-top:16px;text-align:right;">
      <button class="btn-sm btn-primary" name="save" value="1"
              style="padding:12px 24px;font-size:14px;">
        ➕ Create User
      </button>
    </div>
  </form>
</div>

<script>
function toggleMemberPicker() {
  const role = document.getElementById('roleSelect').value;
  document.getElementById('memberPicker').style.display =
    (role === 'member') ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', toggleMemberPicker);
</script>

<!-- ============================================================
     USER LIST
     ============================================================ -->
<div class="us-section">
  <h3>
    📋 <?= $filterRole ? roleLabel($filterRole) . 's' : 'All Users' ?>
    <span class="badge" style="margin-left:6px;"><?= count($users) ?></span>
  </h3>
  <p class="subtitle">
    <?php if ($filterRole): ?>
      Showing only <strong><?= e(roleLabel($filterRole)) ?></strong> accounts.
      <a href="users.php" style="color:#3182ce;">Show all →</a>
    <?php else: ?>
      Every account with access to this system.
    <?php endif; ?>
  </p>

  <?php if (!$users): ?>
    <div class="empty">No users found.</div>
  <?php else: foreach ($users as $user):
    $colors = [
      'sysadmin'  => '#1a365d',
      'admin'     => '#805ad5',
      'secretary' => '#3182ce',
      'finance'   => '#38a169',
      'catechist' => '#dd6b20',
      'member'    => '#718096',
    ];
    $initial = strtoupper(substr($user['full_name'] ?? '?', 0, 1));
    $isSelf = $user['id'] === currentUser()['id'];
  ?>
    <div class="us-user <?= $isSelf ? 'self' : '' ?>">
      <div class="us-avatar" style="background:<?= $colors[$user['role']] ?? '#718096' ?>;">
        <?= e($initial) ?>
      </div>

      <div class="us-info">
        <div class="us-name">
          <?= e($user['full_name']) ?>
          <?php if ($isSelf): ?>
            <span style="font-size:11px;color:#3182ce;font-weight:600;">(you)</span>
          <?php endif; ?>
        </div>
        <div class="us-meta">
          <code><?= e($user['username']) ?></code>
          <?= !empty($user['member_name']) ? ' · Linked to ' . e($user['member_name']) : '' ?>
        </div>
        <div style="margin-top:4px;">
          <span class="role-pill"
                style="background:<?= $colors[$user['role']] ?? '#718096' ?>;color:#fff;">
            <?= e(roleLabelLong($user['role'])) ?>
          </span>
        </div>
      </div>

      <div class="us-actions">
        <?php if ($isSelf): ?>
          <a class="btn-sm btn-ghost" style="padding:8px 12px;font-size:12px;"
             href="../change_password.php" title="Change my password">🔒</a>
        <?php else: ?>
          <form method="post" style="display:inline-flex;gap:4px;align-items:center;">
            <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
            <select name="role" style="padding:6px 8px;font-size:12px;width:auto;">
              <option value="sysadmin"  <?= $user['role']==='sysadmin'?'selected':'' ?>>🔧 Sysadmin</option>
              <option value="admin"     <?= $user['role']==='admin'?'selected':'' ?>>✝️ Station Admin</option>
              <option value="secretary" <?= $user['role']==='secretary'?'selected':'' ?>>✍️ Secretary</option>
              <option value="finance"   <?= $user['role']==='finance'?'selected':'' ?>>💰 Finance</option>
              <option value="catechist" <?= $user['role']==='catechist'?'selected':'' ?>>📖 Catechist</option>
              <option value="member"    <?= $user['role']==='member'?'selected':'' ?>>🙏 Member</option>
            </select>
            <button class="btn-sm btn-primary" name="change_role" value="1"
                    style="padding:6px 10px;font-size:12px;">Save</button>
          </form>

          <a class="btn-sm btn-ghost" style="padding:8px 12px;font-size:12px;"
             data-confirm="Reset password to password@123?"
             href="?reset=<?= (int)$user['id'] ?><?= $filterRole ? '&role='.urlencode($filterRole) : '' ?>"
             title="Reset password">🔑</a>

          <a class="btn-sm btn-danger" style="padding:8px 12px;font-size:12px;"
             data-confirm="Delete this user?"
             href="?delete=<?= (int)$user['id'] ?><?= $filterRole ? '&role='.urlencode($filterRole) : '' ?>"
             title="Delete">🗑</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<!-- ============================================================
     ROLE REFERENCE
     ============================================================ -->
<div class="us-section" style="background:linear-gradient(135deg,#ebf8ff,#fff);
                                border-left:4px solid #3182ce;">
  <h3 style="color:#2c5282;">ℹ️ What Each Role Can Do</h3>
  <p class="subtitle" style="color:#2c5282;">
    Use this as a quick guide when assigning roles.
  </p>

  <div style="display:grid;gap:10px;">
    <?php
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
    foreach ($roleGuide as $key => $info): ?>
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
        <div style="font-size:20px;font-weight:800;color:<?= $info[1] ?>;">
          <?= $roleCounts[$key] ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>