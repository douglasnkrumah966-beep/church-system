<?php
/* ============================================================
   SECURITY SETTINGS
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
    $pdo->exec("CREATE TABLE IF NOT EXISTS security_settings (
        id INT PRIMARY KEY DEFAULT 1,
        min_password_length INT DEFAULT 6,
        require_numbers TINYINT DEFAULT 0,
        require_symbols TINYINT DEFAULT 0,
        max_login_attempts INT DEFAULT 5,
        session_timeout_minutes INT DEFAULT 120,
        force_https TINYINT DEFAULT 0,
        two_factor_enabled TINYINT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("INSERT IGNORE INTO security_settings (id) VALUES (1)");
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

    $minLen     = max(4, min(64,   (int)post('min_password_length')));
    $maxTries   = max(3, min(20,   (int)post('max_login_attempts')));
    $timeout    = max(15, min(1440,(int)post('session_timeout_minutes')));

    try {
        $stmt = $pdo->prepare("UPDATE security_settings SET
            min_password_length = ?,
            require_numbers = ?,
            require_symbols = ?,
            max_login_attempts = ?,
            session_timeout_minutes = ?,
            force_https = ?,
            two_factor_enabled = ?
            WHERE id = 1");

        $stmt->execute([
            $minLen ?: 6,
            isset($_POST['require_numbers'])    ? 1 : 0,
            isset($_POST['require_symbols'])    ? 1 : 0,
            $maxTries ?: 5,
            $timeout ?: 120,
            isset($_POST['force_https'])        ? 1 : 0,
            isset($_POST['two_factor_enabled']) ? 1 : 0,
        ]);

        flash('✅ Security settings saved successfully');
        logAction($pdo, 'security_update', 'Security settings updated');
    } catch (PDOException $e) {
        flash('❌ Could not save security settings');
    }
    redirect('security.php');
}

/* ============================================================
   NOW include layout (HTML output starts here)
   ============================================================ */
require_once __DIR__ . '/_layout.php';

/* ============================================================
   LOAD CURRENT SETTINGS
   ============================================================ */
$s = [];
try {
    $s = $pdo->query("SELECT * FROM security_settings WHERE id=1")->fetch();
} catch (PDOException $e) { $s = []; }
if (!is_array($s) || empty($s)) {
    $s = [
        'min_password_length'     => 6,
        'require_numbers'         => 0,
        'require_symbols'         => 0,
        'max_login_attempts'      => 5,
        'session_timeout_minutes' => 120,
        'force_https'             => 0,
        'two_factor_enabled'      => 0,
    ];
}

$minLen      = (int)$s['min_password_length'];
$maxTries    = (int)$s['max_login_attempts'];
$timeout     = (int)$s['session_timeout_minutes'];
$forceHttps  = !empty($s['force_https']);
$requireNums = !empty($s['require_numbers']);
$requireSyms = !empty($s['require_symbols']);
$twoFA       = !empty($s['two_factor_enabled']);
?>

<style>
.sec-hero {
  background: linear-gradient(135deg,#1a365d 0%,#2c5282 60%,#4299e1 100%);
  color:#fff; border-radius:16px; padding:28px 24px;
  margin-bottom:18px; box-shadow:0 8px 30px rgba(26,54,93,0.25);
}
.sec-hero h1 { font-size:24px; margin:0 0 6px; font-weight:800; }
.sec-hero p  { font-size:13px; opacity:0.92; margin:0; line-height:1.6; }
.sec-hero .shield { display:inline-block; font-size:36px; margin-bottom:6px; }

.sec-section {
  background:#fff; border-radius:14px; padding:20px; margin-bottom:16px;
  box-shadow:0 2px 8px rgba(0,0,0,0.05); border:1px solid #edf2f7;
}
.sec-section h3 {
  font-size:15px; color:#1a365d; margin:0 0 4px; font-weight:700;
  display:flex; align-items:center; gap:8px;
}
.sec-section .subtitle {
  font-size:12px; color:#a0aec0; margin:0 0 16px; line-height:1.5;
}

.num-wrap { position:relative; display:flex; align-items:center; }
.num-wrap input { padding-right:60px; }
.num-wrap .unit {
  position:absolute; right:14px; font-size:12px; color:#a0aec0;
  font-weight:600; pointer-events:none;
}

.toggle-row {
  display:flex; align-items:center; justify-content:space-between;
  padding:14px 16px; background:#f7fafc; border-radius:10px;
  margin-bottom:8px; cursor:pointer;
  border:2px solid #edf2f7; transition: all 0.15s ease;
}
.toggle-row:hover { border-color:#cbd5e0; background:#fff; }
.toggle-row.active { background:#f0fff4; border-color:#68d391; }
.toggle-row.advanced.active { background:#fffaf0; border-color:#f6ad55; }
.toggle-row .toggle-label { flex:1; min-width:0; }
.toggle-row .toggle-title {
  font-size:14px; font-weight:600; color:#2d3748; margin-bottom:2px;
}
.toggle-row .toggle-hint { font-size:12px; color:#718096; }
.toggle-row input[type="checkbox"] { display:none; }
.switch {
  position:relative; width:48px; height:26px; background:#cbd5e0;
  border-radius:999px; flex-shrink:0; transition: background 0.2s ease;
}
.switch::after {
  content:''; position:absolute; top:3px; left:3px;
  width:20px; height:20px; background:#fff; border-radius:50%;
  transition: transform 0.2s ease;
  box-shadow: 0 2px 4px rgba(0,0,0,0.15);
}
input[type="checkbox"]:checked + .switch { background:#48bb78; }
input[type="checkbox"]:checked + .switch::after { transform: translateX(22px); }
.toggle-row.advanced input[type="checkbox"]:checked + .switch { background:#dd6b20; }

.status-grid { display:grid; grid-template-columns:1fr; gap:10px; }
@media (min-width:600px) { .status-grid { grid-template-columns:repeat(2,1fr); } }
.status-item {
  display:flex; align-items:center; gap:12px;
  padding:12px 14px; background:#f7fafc; border-radius:10px;
  border-left:4px solid #cbd5e0;
}
.status-item.ok   { border-left-color:#48bb78; background:#f0fff4; }
.status-item.off  { border-left-color:#cbd5e0; }
.status-item .ic { font-size:18px; flex-shrink:0; }
.status-item .info { flex:1; min-width:0; }
.status-item .lbl {
  font-size:11px; color:#718096; text-transform:uppercase;
  font-weight:600; letter-spacing:0.3px;
}
.status-item .val {
  font-size:14px; font-weight:700; color:#2d3748; margin-top:2px;
}

.save-bar {
  position:sticky; bottom:12px; background:#fff;
  padding:14px; border-radius:14px;
  box-shadow:0 -6px 20px rgba(0,0,0,0.08);
  display:flex; gap:10px; align-items:center;
  justify-content:flex-end; margin-top:20px; z-index:10;
}
.save-bar .status-note { flex:1; font-size:12px; color:#718096; }
@media (max-width:500px) {
  .save-bar { flex-direction:column; }
  .save-bar button { width:100%; }
  .save-bar .status-note { text-align:center; }
}
</style>

<!-- ============================================================
     HERO
     ============================================================ -->
<div class="sec-hero">
  <div class="shield">🛡</div>
  <h1>Security Settings</h1>
  <p>Control who can sign in and how long they stay signed in. These settings protect the station's records.</p>
</div>

<!-- ============================================================
     FORM
     ============================================================ -->
<form method="post">

  <!-- PASSWORD RULES -->
  <div class="sec-section">
    <h3>🔑 Password Rules</h3>
    <p class="subtitle">Applied whenever a password is created or changed.</p>

    <div class="grid grid-2">
      <div>
        <label>Minimum length</label>
        <div class="num-wrap">
          <input name="min_password_length" type="number" min="4" max="64"
                 value="<?= $minLen ?>">
          <span class="unit">chars</span>
        </div>
      </div>
      <div>
        <label>Lock after failed logins</label>
        <div class="num-wrap">
          <input name="max_login_attempts" type="number" min="3" max="20"
                 value="<?= $maxTries ?>">
          <span class="unit">tries</span>
        </div>
      </div>
    </div>

    <div style="margin-top:14px;">
      <label class="toggle-row <?= $requireNums ? 'active' : '' ?>">
        <div class="toggle-label">
          <div class="toggle-title">Require at least one number</div>
          <div class="toggle-hint">Example: <code>Station2026</code></div>
        </div>
        <input type="checkbox" name="require_numbers" value="1"
               <?= $requireNums ? 'checked' : '' ?>>
        <span class="switch"></span>
      </label>

      <label class="toggle-row <?= $requireSyms ? 'active' : '' ?>">
        <div class="toggle-label">
          <div class="toggle-title">Require at least one symbol</div>
          <div class="toggle-hint">Example: <code>Station@2026</code></div>
        </div>
        <input type="checkbox" name="require_symbols" value="1"
               <?= $requireSyms ? 'checked' : '' ?>>
        <span class="switch"></span>
      </label>
    </div>
  </div>

  <!-- SESSION -->
  <div class="sec-section">
    <h3>⏱ Session Timeout</h3>
    <p class="subtitle">Users are signed out automatically after this period of inactivity.</p>

    <div class="grid grid-2">
      <div>
        <label>Timeout duration</label>
        <div class="num-wrap">
          <input name="session_timeout_minutes" type="number" min="15" max="1440"
                 value="<?= $timeout ?>">
          <span class="unit">mins</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ADVANCED -->
  <div class="sec-section">
    <h3>⚙️ Advanced</h3>
    <p class="subtitle">Only change these if you know what you're doing.</p>

    <label class="toggle-row advanced <?= $forceHttps ? 'active' : '' ?>">
      <div class="toggle-label">
        <div class="toggle-title">Force HTTPS on all pages</div>
        <div class="toggle-hint">⚠️ Requires an SSL certificate. If not set up correctly, users cannot log in.</div>
      </div>
      <input type="checkbox" name="force_https" value="1"
             <?= $forceHttps ? 'checked' : '' ?>>
      <span class="switch"></span>
    </label>

    <label class="toggle-row advanced <?= $twoFA ? 'active' : '' ?>">
      <div class="toggle-label">
        <div class="toggle-title">Two-factor authentication</div>
        <div class="toggle-hint">Coming soon — extra code sent to phone for login.</div>
      </div>
      <input type="checkbox" name="two_factor_enabled" value="1"
             <?= $twoFA ? 'checked' : '' ?>>
      <span class="switch"></span>
    </label>
  </div>

  <!-- SAVE BAR -->
  <div class="save-bar">
    <div class="status-note">Changes take effect on the next login.</div>
    <button class="btn-sm btn-primary" name="save" value="1"
            style="padding:12px 24px;font-size:14px;">
      💾 Save Changes
    </button>
  </div>
</form>

<!-- ============================================================
     CURRENT STATUS
     ============================================================ -->
<div class="sec-section" style="margin-top:24px;">
  <h3>📋 Current Status</h3>
  <p class="subtitle">A live summary of the station's security configuration.</p>

  <div class="status-grid">
    <div class="status-item ok">
      <div class="ic">🔑</div>
      <div class="info">
        <div class="lbl">Password Length</div>
        <div class="val"><?= $minLen ?> characters</div>
      </div>
    </div>
    <div class="status-item <?= $requireNums ? 'ok' : 'off' ?>">
      <div class="ic"><?= $requireNums ? '✅' : '⚪' ?></div>
      <div class="info">
        <div class="lbl">Require Numbers</div>
        <div class="val"><?= $requireNums ? 'Enabled' : 'Not required' ?></div>
      </div>
    </div>
    <div class="status-item <?= $requireSyms ? 'ok' : 'off' ?>">
      <div class="ic"><?= $requireSyms ? '✅' : '⚪' ?></div>
      <div class="info">
        <div class="lbl">Require Symbols</div>
        <div class="val"><?= $requireSyms ? 'Enabled' : 'Not required' ?></div>
      </div>
    </div>
    <div class="status-item ok">
      <div class="ic">🔒</div>
      <div class="info">
        <div class="lbl">Lock After</div>
        <div class="val"><?= $maxTries ?> failed attempts</div>
      </div>
    </div>
    <div class="status-item ok">
      <div class="ic">⏱</div>
      <div class="info">
        <div class="lbl">Session Timeout</div>
        <div class="val"><?= $timeout ?> minutes</div>
      </div>
    </div>
    <div class="status-item <?= $forceHttps ? 'ok' : 'off' ?>">
      <div class="ic"><?= $forceHttps ? '🔐' : '⚪' ?></div>
      <div class="info">
        <div class="lbl">HTTPS Enforcement</div>
        <div class="val"><?= $forceHttps ? 'Enabled' : 'Disabled' ?></div>
      </div>
    </div>
    <div class="status-item <?= $twoFA ? 'ok' : 'off' ?>">
      <div class="ic"><?= $twoFA ? '📱' : '⚪' ?></div>
      <div class="info">
        <div class="lbl">Two-Factor Auth</div>
        <div class="val"><?= $twoFA ? 'Enabled (preview)' : 'Not enabled' ?></div>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================
     RECOMMENDED
     ============================================================ -->
<div class="sec-section" style="background:linear-gradient(135deg,#f0fff4,#fff);
                                 border-left:4px solid #48bb78;">
  <h3 style="color:#22543d;">💡 Recommended for a Station</h3>
  <p class="subtitle" style="color:#2f855a;">
    These values balance security with ease of use for parishioners.
  </p>

  <div class="status-grid">
    <div class="status-item ok">
      <div class="ic">🔑</div>
      <div class="info">
        <div class="lbl">Password Length</div>
        <div class="val">8 characters or more</div>
      </div>
    </div>
    <div class="status-item ok">
      <div class="ic">🔢</div>
      <div class="info">
        <div class="lbl">Require Numbers</div>
        <div class="val">Yes</div>
      </div>
    </div>
    <div class="status-item off">
      <div class="ic">✨</div>
      <div class="info">
        <div class="lbl">Require Symbols</div>
        <div class="val">Optional</div>
      </div>
    </div>
    <div class="status-item ok">
      <div class="ic">🔒</div>
      <div class="info">
        <div class="lbl">Lock After</div>
        <div class="val">5 attempts</div>
      </div>
    </div>
    <div class="status-item ok">
      <div class="ic">⏱</div>
      <div class="info">
        <div class="lbl">Session Timeout</div>
        <div class="val">120 minutes (2 hours)</div>
      </div>
    </div>
    <div class="status-item off">
      <div class="ic">🔐</div>
      <div class="info">
        <div class="lbl">Force HTTPS</div>
        <div class="val">Only with SSL certificate</div>
      </div>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.toggle-row input[type="checkbox"]').forEach(cb => {
  cb.addEventListener('change', function () {
    const row = this.closest('.toggle-row');
    if (this.checked) row.classList.add('active');
    else row.classList.remove('active');
  });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>