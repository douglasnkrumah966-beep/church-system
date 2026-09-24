<?php
/* ============================================================
   SYSTEM SETTINGS
   ------------------------------------------------------------
   All POST handling and redirects happen BEFORE _layout.php.
   ============================================================ */

require_once __DIR__ . '/../includes/auth.php';
requireSysadmin();

$u = currentUser();

/* ---------- Available timezones (Africa-focused + world) ---------- */
$AFRICA_TIMEZONES = [
    'Africa/Accra'      => 'Ghana — Accra (GMT)',
    'Africa/Lagos'      => 'Nigeria — Lagos (WAT)',
    'Africa/Abidjan'    => 'Côte d\'Ivoire — Abidjan (GMT)',
    'Africa/Lome'       => 'Togo — Lomé (GMT)',
    'Africa/Ouagadougou'=> 'Burkina Faso — Ouagadougou (GMT)',
    'Africa/Bamako'     => 'Mali — Bamako (GMT)',
    'Africa/Dakar'      => 'Senegal — Dakar (GMT)',
    'Africa/Nairobi'    => 'Kenya — Nairobi (EAT)',
    'Africa/Kampala'    => 'Uganda — Kampala (EAT)',
    'Africa/Dar_es_Salaam' => 'Tanzania — Dar es Salaam (EAT)',
    'Africa/Johannesburg'  => 'South Africa — Johannesburg (SAST)',
    'Africa/Cairo'      => 'Egypt — Cairo (EET)',
    'Africa/Casablanca' => 'Morocco — Casablanca (WET)',
];

$WORLD_TIMEZONES = [
    'UTC'              => 'UTC — Coordinated Universal Time',
    'Europe/London'    => 'UK — London',
    'Europe/Paris'     => 'Europe — Paris / Central Europe',
    'America/New_York' => 'USA — New York (Eastern)',
    'America/Chicago'  => 'USA — Chicago (Central)',
    'America/Los_Angeles' => 'USA — Los Angeles (Pacific)',
    'Asia/Dubai'       => 'UAE — Dubai',
    'Asia/Tokyo'       => 'Japan — Tokyo',
];

/* ---------- Save settings ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {

    /* Validate timezone */
    $tz = post('timezone');
    $allTz = array_merge(array_keys($AFRICA_TIMEZONES), array_keys($WORLD_TIMEZONES));
    if (!in_array($tz, $allTz, true)) {
        $tz = 'Africa/Accra';
    }

    try {
        $stmt = $pdo->prepare("UPDATE settings SET
            church_name = ?,
            currency = ?,
            timezone = ?
            WHERE id = 1");
        $stmt->execute([
            post('church_name'),
            post('currency') ?: 'GH₵',
            $tz
        ]);
        flash('✅ Settings saved');
        logAction($pdo, 'settings_update', 'System settings updated');
    } catch (PDOException $e) {
        flash('❌ Could not save settings');
    }
    redirect('settings.php');
}

/* ---------- Activity log helper ---------- */
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
   NOW include layout (HTML output starts here)
   ============================================================ */
require_once __DIR__ . '/_layout.php';

/* ---------- Load settings ---------- */
$s = [];
try {
    $s = $pdo->query("SELECT * FROM settings WHERE id = 1")->fetch();
} catch (PDOException $e) { $s = []; }
if (!is_array($s)) $s = [];

$currentTz = $s['timezone'] ?? 'Africa/Accra';

/* ---------- System info ---------- */
$phpVersion  = PHP_VERSION;
$serverSoft  = $_SERVER['SERVER_SOFTWARE'] ?? '—';
$dbName      = $pdo->query("SELECT DATABASE()")->fetchColumn() ?: '—';
$appTz       = date_default_timezone_get();
$dbSize      = $pdo->query("
    SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
")->fetchColumn() ?: 0;
?>

<style>
.set-hero {
  background: linear-gradient(135deg,#1a365d 0%,#2c5282 60%,#4299e1 100%);
  color:#fff; border-radius:16px; padding:24px;
  margin-bottom:18px; box-shadow:0 8px 30px rgba(26,54,93,0.25);
}
.set-hero h1 { font-size:22px; margin:0 0 4px; font-weight:800; }
.set-hero p  { font-size:13px; opacity:0.9; margin:0; }

.set-section {
  background:#fff; border-radius:14px; padding:20px; margin-bottom:16px;
  box-shadow:0 2px 8px rgba(0,0,0,0.05); border:1px solid #edf2f7;
}
.set-section h3 {
  font-size:15px; color:#1a365d; margin:0 0 4px; font-weight:700;
  display:flex; align-items:center; gap:8px;
}
.set-section .subtitle {
  font-size:12px; color:#a0aec0; margin:0 0 16px; line-height:1.5;
}

.tz-group {
  font-size:11px; color:#1a365d; font-weight:700; text-transform:uppercase;
  letter-spacing:1px; padding:8px 0 4px; border-bottom:2px solid #ebf8ff;
  margin-bottom:6px;
}

.info-row {
  display:flex; justify-content:space-between;
  padding:10px 12px; border-bottom:1px solid #edf2f7;
  font-size:13px; border-radius:6px;
}
.info-row:last-child { border-bottom:none; }
.info-row .lbl { color:#718096; }
.info-row .val { font-weight:700; color:#2d3748; text-align:right; }

@media (max-width:600px) {
  .info-row { flex-direction:column; gap:4px; align-items:flex-start; }
  .info-row .val { text-align:left; }
}
</style>

<!-- ============================================================
     HERO
     ============================================================ -->
<div class="set-hero">
  <h1>⚙️ System Settings</h1>
  <p>Station name, currency, and timezone for the whole management system.</p>
</div>

<!-- ============================================================
     SETTINGS FORM
     ============================================================ -->
<form method="post">

  <!-- Station Information -->
  <div class="set-section">
    <h3>🏛️ Station Information</h3>
    <p class="subtitle">
      These appear in headers, receipts, reports, and printed cards.
    </p>

    <div class="grid grid-2">
      <div style="grid-column:1/-1;">
        <label>Station Name *</label>
        <input name="church_name" required
               value="<?= e($s['church_name'] ?? '') ?>"
               placeholder="e.g. St. Mary's Catholic Station">
      </div>

      <div>
        <label>Currency Symbol *</label>
        <input name="currency" required
               value="<?= e($s['currency'] ?? 'GH₵') ?>"
               placeholder="e.g. GH₵">
        <small style="color:#718096;font-size:11px;">
          Ghana Cedis uses <code>GH₵</code>. You can also use ₦, $, £, €.
        </small>
      </div>

      <div>
        <label>Currency Code (optional)</label>
        <input value="<?= e($s['default_currency_code'] ?? 'GHS') ?>" readonly
               style="background:#f7fafc;color:#718096;">
        <small style="color:#718096;font-size:11px;">
          Reference only — not used in display.
        </small>
      </div>
    </div>
  </div>

  <!-- Timezone -->
  <div class="set-section">
    <h3>🕒 Timezone</h3>
    <p class="subtitle">
      Sets the time zone for all dates and times in the system.
      <strong>Recommended: Africa/Accra</strong> for Ghana.
    </p>

    <label>Select Timezone</label>
    <select name="timezone" style="max-width:500px;">
      <optgroup label="🇬🇭 Africa (recommended)">
        <?php foreach ($AFRICA_TIMEZONES as $tz => $label): ?>
          <option value="<?= e($tz) ?>" <?= $currentTz === $tz ? 'selected' : '' ?>>
            <?= e($label) ?>
          </option>
        <?php endforeach; ?>
      </optgroup>
      <optgroup label="🌍 Other Regions">
        <?php foreach ($WORLD_TIMEZONES as $tz => $label): ?>
          <option value="<?= e($tz) ?>" <?= $currentTz === $tz ? 'selected' : '' ?>>
            <?= e($label) ?>
          </option>
        <?php endforeach; ?>
      </optgroup>
    </select>

    <div style="margin-top:12px;background:#ebf8ff;padding:12px 14px;
                border-radius:10px;font-size:12px;color:#2c5282;line-height:1.6;">
      <strong>Currently set:</strong> <code><?= e($currentTz) ?></code><br>
      Server timezone (PHP default): <code><?= e($appTz) ?></code>
    </div>

    <div style="margin-top:12px;background:#f0fff4;padding:12px 14px;
                border-radius:10px;font-size:12px;color:#22543d;line-height:1.6;
                border-left:4px solid #48bb78;">
      <strong>💡 Note for Ghana stations:</strong><br>
      <code>Africa/Accra</code> is the correct timezone. It stays at GMT/UTC all year
      with no daylight saving. All timestamps (transactions, attendance,
      logs) will be recorded correctly.
    </div>
  </div>

  <!-- Save button -->
  <div class="set-section" style="text-align:center;">
    <button class="btn-primary" name="save" value="1"
            style="max-width:280px;margin:0 auto;padding:14px 24px;font-size:15px;">
      💾 Save All Settings
    </button>
  </div>
</form>

<!-- ============================================================
     SYSTEM INFO
     ============================================================ -->
<div class="set-section">
  <h3>💻 System Information</h3>
  <p class="subtitle">
    Technical details about the environment this system runs on.
  </p>

  <div class="info-row">
    <span class="lbl">Application</span>
    <span class="val">Station Management System</span>
  </div>
  <div class="info-row">
    <span class="lbl">PHP Version</span>
    <span class="val"><?= e($phpVersion) ?></span>
  </div>
  <div class="info-row">
    <span class="lbl">Database</span>
    <span class="val"><?= e($dbName) ?></span>
  </div>
  <div class="info-row">
    <span class="lbl">Database Size</span>
    <span class="val"><?= number_format((float)$dbSize, 2) ?> MB</span>
  </div>
  <div class="info-row">
    <span class="lbl">Server Software</span>
    <span class="val"><?= e($serverSoft) ?></span>
  </div>
  <div class="info-row">
    <span class="lbl">Timezone (active)</span>
    <span class="val"><?= e($currentTz) ?></span>
  </div>
  <div class="info-row">
    <span class="lbl">Server time now</span>
    <span class="val"><?= date('jS F Y, H:i:s') ?></span>
  </div>
</div>

<!-- ============================================================
     SCOPE NOTE
     ============================================================ -->
<div class="set-section" style="background:#ebf8ff;border-left:4px solid #3182ce;">
  <h3 style="color:#2c5282;">ℹ️ About These Settings</h3>
  <p style="font-size:13px;color:#2c5282;line-height:1.7;">
    These are <strong>system-wide</strong> settings — they affect the whole
    application. For station operations like financial categories,
    use the <a href="../finance/financial_settings.php"
                style="color:#1a365d;font-weight:600;">Finance Settings</a> page instead.
  </p>
  <p style="font-size:12px;color:#2c5282;margin-top:8px;">
    Only the <strong>System Administrator</strong> can change these values.
  </p>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>