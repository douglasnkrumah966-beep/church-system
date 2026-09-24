<?php
/* ============================================================
   MAINTENANCE MODE
   ------------------------------------------------------------
   All POST handling and redirects happen BEFORE _layout.php.
   ============================================================ */

require_once __DIR__ . '/../includes/auth.php';
requireSysadmin();

$u = currentUser();

/* ============================================================
   ENSURE TABLES EXIST
   ============================================================ */
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS maintenance_mode (
        id INT PRIMARY KEY DEFAULT 1,
        enabled TINYINT DEFAULT 0,
        message VARCHAR(500) DEFAULT 'System is under maintenance. Please check back shortly.',
        allowed_roles VARCHAR(200) DEFAULT 'admin',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* Ensure a row exists */
    $exists = (int)$pdo->query("SELECT COUNT(*) FROM maintenance_mode")->fetchColumn();
    if ($exists === 0) {
        $pdo->exec("INSERT INTO maintenance_mode (id) VALUES (1)");
    }
} catch (PDOException $e) {}

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
   HANDLE SAVE
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {

    $enabled      = isset($_POST['enabled']) ? 1 : 0;
    $message      = trim(post('message'));
    $allowedRoles = trim(post('allowed_roles'));

    if ($message === '') {
        $message = 'System is under maintenance. Please check back shortly.';
    }

    /* Validate allowed roles against whitelist */
    $validRoles = ['sysadmin','admin','secretary','finance','catechist','member'];
    $rolesArray = array_filter(array_map('trim', explode(',', $allowedRoles)));
    $rolesArray = array_intersect($rolesArray, $validRoles);

    /* Always allow sysadmin to access (locked) */
    if (!in_array('sysadmin', $rolesArray, true)) {
        $rolesArray[] = 'sysadmin';
    }

    $allowedRolesClean = implode(',', $rolesArray);

    try {
        $stmt = $pdo->prepare("UPDATE maintenance_mode SET
            enabled = ?,
            message = ?,
            allowed_roles = ?
            WHERE id = 1");
        $stmt->execute([$enabled, $message, $allowedRolesClean]);

        flash($enabled
            ? '⚠️ Maintenance mode is now ENABLED'
            : '✅ Maintenance mode is now DISABLED');

        logAction($pdo, 'maintenance_update',
                  $enabled ? 'Maintenance enabled' : 'Maintenance disabled');
    } catch (PDOException $e) {
        flash('❌ Could not save maintenance settings');
    }
    redirect('maintenance.php');
}

/* ============================================================
   NOW include layout (HTML output starts here)
   ============================================================ */
require_once __DIR__ . '/_layout.php';

/* ============================================================
   LOAD CURRENT STATE
   ============================================================ */
$m = [];
try {
    $m = $pdo->query("SELECT * FROM maintenance_mode WHERE id = 1")->fetch();
} catch (PDOException $e) { $m = []; }
if (!is_array($m) || empty($m)) {
    $m = [
        'enabled' => 0,
        'message' => 'System is under maintenance. Please check back shortly.',
        'allowed_roles' => 'sysadmin',
    ];
}

$isEnabled = !empty($m['enabled']);
$currentRoles = array_filter(array_map('trim', explode(',', $m['allowed_roles'] ?? 'sysadmin')));

/* ---------- Role guide ---------- */
$roleOptions = [
    'sysadmin'  => ['🔧 System Administrator', '#1a365d'],
    'admin'     => ['✝️ Station Administrator', '#805ad5'],
    'secretary' => ['✍️ Station Secretary',     '#3182ce'],
    'finance'   => ['💰 Finance Officer',        '#38a169'],
    'catechist' => ['📖 Catechist',              '#dd6b20'],
    'member'    => ['🙏 Member',                 '#718096'],
];
?>

<style>
.mt-hero {
  background: linear-gradient(135deg,#1a365d 0%,#2c5282 60%,#4299e1 100%);
  color:#fff; border-radius:16px; padding:24px;
  margin-bottom:18px; box-shadow:0 8px 30px rgba(26,54,93,0.25);
}
.mt-hero h1 { font-size:22px; margin:0 0 4px; font-weight:800; }
.mt-hero p  { font-size:13px; opacity:0.9; margin:0; }

.mt-section {
  background:#fff; border-radius:14px; padding:20px; margin-bottom:16px;
  box-shadow:0 2px 8px rgba(0,0,0,0.05); border:1px solid #edf2f7;
}
.mt-section h3 {
  font-size:15px; color:#1a365d; margin:0 0 4px; font-weight:700;
  display:flex; align-items:center; gap:8px;
}
.mt-section .subtitle {
  font-size:12px; color:#a0aec0; margin:0 0 16px; line-height:1.5;
}

/* Status banner */
.status-banner {
  display: flex; align-items: center; gap: 16px;
  padding: 20px 24px; border-radius: 14px; margin-bottom: 18px;
  border: 2px solid;
}
.status-banner.on {
  background: linear-gradient(135deg,#fff5f5,#fed7d7);
  border-color: #e53e3e;
}
.status-banner.off {
  background: linear-gradient(135deg,#f0fff4,#c6f6d5);
  border-color: #48bb78;
}
.status-banner .icon {
  font-size: 42px; flex-shrink: 0;
}
.status-banner .info { flex: 1; min-width: 0; }
.status-banner .title {
  font-size: 18px; font-weight: 800; margin-bottom: 4px;
}
.status-banner.on .title { color: #742a2a; }
.status-banner.off .title { color: #22543d; }
.status-banner .desc {
  font-size: 13px; line-height: 1.5;
}
.status-banner.on .desc { color: #742a2a; }
.status-banner.off .desc { color: #22543d; }

/* Toggle row */
.toggle-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 18px; background: #f7fafc; border-radius: 12px;
  margin-bottom: 14px; border: 2px solid #edf2f7;
  cursor: pointer; transition: all 0.15s ease;
}
.toggle-row:hover { border-color: #cbd5e0; background: #fff; }
.toggle-row.active {
  background: #fff5f5; border-color: #fc8181;
}
.toggle-row .label {
  flex: 1; min-width: 0;
}
.toggle-row .title {
  font-size: 15px; font-weight: 700; color: #2d3748; margin-bottom: 2px;
}
.toggle-row .hint {
  font-size: 12px; color: #718096;
}
.toggle-row input[type="checkbox"] { display: none; }
.switch {
  position: relative; width: 56px; height: 30px;
  background: #cbd5e0; border-radius: 999px;
  flex-shrink: 0; transition: background 0.2s ease;
}
.switch::after {
  content: ''; position: absolute; top: 3px; left: 3px;
  width: 24px; height: 24px; background: #fff; border-radius: 50%;
  transition: transform 0.2s ease;
  box-shadow: 0 2px 4px rgba(0,0,0,0.15);
}
input[type="checkbox"]:checked + .switch { background: #e53e3e; }
input[type="checkbox"]:checked + .switch::after { transform: translateX(26px); }

/* Role checkboxes */
.role-grid {
  display: grid; grid-template-columns: 1fr; gap: 8px;
}
@media (min-width: 600px) { .role-grid { grid-template-columns: 1fr 1fr; } }

.role-check {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 14px; background: #f7fafc; border-radius: 10px;
  cursor: pointer; border: 2px solid transparent;
  transition: all 0.15s ease;
}
.role-check:hover { background: #fff; border-color: #cbd5e0; }
.role-check input { width: auto !important; }
.role-check.checked { background: #ebf8ff; border-color: #90cdf4; }
.role-check.locked {
  background: #edf2f7; cursor: not-allowed;
  opacity: 0.85; border-color: #cbd5e0;
}

/* Live preview */
.preview-box {
  background: #2d3748; color: #fff; border-radius: 12px;
  padding: 20px; text-align: center; margin-top: 12px;
}
.preview-box .emoji { font-size: 40px; }
.preview-box .title {
  font-size: 20px; font-weight: 800; margin: 8px 0 4px;
}
.preview-box .msg {
  font-size: 14px; opacity: 0.9; line-height: 1.6;
  max-width: 400px; margin: 0 auto;
}

@media (max-width: 600px) {
  .status-banner { flex-direction: column; text-align: center; }
}
</style>

<!-- ============================================================
     HERO
     ============================================================ -->
<div class="mt-hero">
  <h1>🛠 Maintenance Mode</h1>
  <p>Temporarily close the system for non-essential users during updates, migrations, or emergencies.</p>
</div>

<!-- ============================================================
     STATUS BANNER
     ============================================================ -->
<div class="status-banner <?= $isEnabled ? 'on' : 'off' ?>">
  <div class="icon"><?= $isEnabled ? '⚠️' : '✅' ?></div>
  <div class="info">
    <div class="title">
      <?= $isEnabled ? 'Maintenance Mode is ON' : 'Maintenance Mode is OFF' ?>
    </div>
    <div class="desc">
      <?php if ($isEnabled): ?>
        Non-authorized users are blocked from the system.
        Allowed roles: <strong><?= e(implode(', ', $currentRoles)) ?></strong>
      <?php else: ?>
        Everyone can access the system normally.
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ============================================================
     SETTINGS FORM
     ============================================================ -->
<form method="post">

  <!-- Toggle -->
  <div class="mt-section">
    <h3>⚙️ Enable / Disable</h3>
    <p class="subtitle">
      When ON, only users in the allowed roles can log in.
      Everyone else sees the maintenance message.
    </p>

    <label class="toggle-row <?= $isEnabled ? 'active' : '' ?>">
      <div class="label">
        <div class="title">Enable Maintenance Mode</div>
        <div class="hint">
          Currently: <strong><?= $isEnabled ? 'ON' : 'OFF' ?></strong>
        </div>
      </div>
      <input type="checkbox" name="enabled" value="1"
             <?= $isEnabled ? 'checked' : '' ?>>
      <span class="switch"></span>
    </label>
  </div>

  <!-- Message -->
  <div class="mt-section">
    <h3>💬 Message Shown to Users</h3>
    <p class="subtitle">
      This message appears on the maintenance page for blocked users.
    </p>

    <label>Maintenance Message</label>
    <textarea name="message" id="msgInput" rows="3"
              style="width:100%;padding:12px;border:2px solid #e2e8f0;
                     border-radius:10px;font-family:inherit;font-size:15px;"
              oninput="updatePreview()"><?= e($m['message'] ?? '') ?></textarea>

    <small style="color:#718096;font-size:11px;">
      Keep it short and friendly. Users will see this on the maintenance page.
    </small>

    <!-- Live preview -->
    <div style="margin-top:16px;">
      <label style="font-size:11px;color:#718096;text-transform:uppercase;">
        Live Preview
      </label>
      <div class="preview-box">
        <div class="emoji">🛠</div>
        <div class="title"><?= e($settings['church_name'] ?? 'Station') ?></div>
        <div class="msg" id="msgPreview"><?= e($m['message'] ?? '') ?></div>
      </div>
    </div>
  </div>

  <!-- Allowed Roles -->
  <div class="mt-section">
    <h3>👥 Roles Allowed During Maintenance</h3>
    <p class="subtitle">
      Users with these roles can still access the system while maintenance is ON.
      <strong>System Administrator is always allowed</strong> (locked).
    </p>

    <div class="role-grid">
      <?php foreach ($roleOptions as $role => $info):
        $isChecked = in_array($role, $currentRoles, true);
        $isLocked  = ($role === 'sysadmin');
      ?>
        <label class="role-check <?= $isChecked ? 'checked' : '' ?> <?= $isLocked ? 'locked' : '' ?>">
          <input type="checkbox" name="roles[]" value="<?= e($role) ?>"
                 <?= $isChecked ? 'checked' : '' ?>
                 <?= $isLocked ? 'disabled' : '' ?>
                 onchange="this.parentElement.classList.toggle('checked', this.checked)">
          <div style="flex:1;">
            <div style="font-weight:700;color:<?= $info[1] ?>;font-size:14px;">
              <?= e($info[0]) ?>
            </div>
            <?php if ($isLocked): ?>
              <div style="font-size:11px;color:#718096;">Always allowed</div>
            <?php endif; ?>
          </div>
        </label>
      <?php endforeach; ?>
    </div>

    <!-- Hidden input to preserve sysadmin even though checkbox is disabled -->
    <input type="hidden" name="roles[]" value="sysadmin">

    <!-- Because we use roles[] in the form, we need to convert it to a comma string -->
    <input type="hidden" name="allowed_roles" id="allowedRolesInput"
           value="<?= e(implode(',', $currentRoles)) ?>">
  </div>

  <!-- Save -->
  <div class="mt-section" style="text-align:center;">
    <button class="btn-primary" name="save" value="1"
            style="max-width:280px;margin:0 auto;padding:14px 24px;font-size:15px;">
      💾 Save Maintenance Settings
    </button>
  </div>
</form>

<!-- ============================================================
     INFO NOTE
     ============================================================ -->
<div class="mt-section" style="background:#ebf8ff;border-left:4px solid #3182ce;">
  <h3 style="color:#2c5282;">ℹ️ When to Use Maintenance Mode</h3>
  <ul style="font-size:13px;color:#2c5282;line-height:1.9;padding-left:20px;margin-top:8px;">
    <li><strong>Database migrations</strong> — when updating the schema</li>
    <li><strong>Backup & restore</strong> — to prevent data changes during a full backup</li>
    <li><strong>Bug fixes</strong> — when deploying critical patches</li>
    <li><strong>Emergencies</strong> — if the system is corrupted</li>
    <li><strong>Training</strong> — while rehearsing new features with the admin team</li>
  </ul>
  <p style="font-size:12px;color:#2c5282;margin-top:10px;">
    <strong>Remember to turn it OFF</strong> once your work is done.
  </p>
</div>

<!-- ============================================================
     JAVASCRIPT
     ============================================================ -->
<script>
/* Update live preview when the message changes */
function updatePreview() {
  const txt = document.getElementById('msgInput').value;
  document.getElementById('msgPreview').textContent = txt || 'System is under maintenance.';
}

/* Sync role checkboxes to the hidden field before form submit */
document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('form');
  const hidden = document.getElementById('allowedRolesInput');

  if (form && hidden) {
    form.addEventListener('submit', () => {
      const checked = Array.from(document.querySelectorAll('input[name="roles[]"]:checked'))
        .map(cb => cb.value);
      // Always include sysadmin
      if (!checked.includes('sysadmin')) checked.push('sysadmin');
      hidden.value = checked.join(',');
    });
  }

  /* Handle checkbox class toggles for the enabled toggle */
  const toggle = document.querySelector('input[name="enabled"]');
  if (toggle) {
    toggle.addEventListener('change', function () {
      const row = this.closest('.toggle-row');
      if (this.checked) row.classList.add('active');
      else row.classList.remove('active');
    });
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>