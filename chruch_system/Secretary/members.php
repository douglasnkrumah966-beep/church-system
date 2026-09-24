<?php
require_once __DIR__ . '/_layout.php';

$canEdit = in_array(currentUser()['role'], ['admin','secretary']);

$UPLOAD_DIR = __DIR__ . '/../uploads/members/';
$UPLOAD_URL = '../uploads/members/';

if ($canEdit && !is_dir($UPLOAD_DIR)) {
    @mkdir($UPLOAD_DIR, 0775, true);
}

/* ============================================================
   PHOTO UPLOAD HELPER
   ============================================================ */
function uploadMemberPhoto(array $file, string $dir): ?string {
    if (!isset($file['tmp_name']) || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) {
        flash('❌ Photo upload error (code ' . $file['error'] . ')');
        return null;
    }
    if ($file['size'] > 3 * 1024 * 1024) {
        flash('❌ Photo too large (max 3 MB)');
        return null;
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png',
                'image/webp' => 'webp', 'image/gif'  => 'gif'];
    $mime = mime_content_type($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        flash('❌ Only JPG, PNG, WEBP, or GIF images allowed');
        return null;
    }
    $ext  = $allowed[$mime];
    $name = 'm_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = rtrim($dir, '/') . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        flash('❌ Could not save photo');
        return null;
    }
    return $name;
}

/* ============================================================
   AUTO-CREATE MEMBER LOGIN
   ============================================================ */
function createMemberLogin(PDO $pdo, int $memberId, string $fullName, string $phone): array {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if ($phone === '') return ['created' => false, 'reason' => 'no_phone'];

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$phone]);
    if ($stmt->fetch()) return ['created' => false, 'reason' => 'username_taken', 'username' => $phone];

    $hash = password_hash('password@123', PASSWORD_DEFAULT);
    try {
        $stmt = $pdo->prepare("INSERT INTO users
            (username, password, full_name, role, member_id)
            VALUES (?,?,?, 'member', ?)");
        $stmt->execute([$phone, $hash, $fullName, $memberId]);
        return ['created' => true, 'username' => $phone, 'password' => 'password@123'];
    } catch (PDOException $e) {
        return ['created' => false, 'reason' => 'db_error', 'message' => $e->getMessage()];
    }
}

/* ============================================================
   SAVE NEW MEMBER
   ============================================================ */
if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {

    $dobKnown  = post('dob_known') ?: 'full';
    $dob       = null;
    $birthYear = null;
    $approxAge = null;
    $dayBorn   = null;

    if ($dobKnown === 'full') {
        $dob = post('dob') ?: null;
        if ($dob) $dayBorn = dayOfWeek($dob);
    } elseif ($dobKnown === 'year') {
        $birthYear = (int)post('birth_year') ?: null;
        $dayBorn   = post('day_born_manual') ?: null;
    } elseif ($dobKnown === 'age') {
        $approxAge = (int)post('approx_age') ?: null;
        if ($approxAge) $birthYear = (int)date('Y') - $approxAge;
        $dayBorn = post('day_born_manual') ?: null;
    } else {
        $dayBorn = post('day_born_manual') ?: null;
    }

    $photoName = null;
    if (!empty($_FILES['photo']['name'])) {
        $photoName = uploadMemberPhoto($_FILES['photo'], $UPLOAD_DIR);
    }

    $phone = post('phone');

    try {
        $stmt = $pdo->prepare("INSERT INTO members
            (full_name, photo, gender, dob, birth_year, approx_age, dob_known,
             place_of_birth, phone, marital_status, baptism, day_born)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            post('full_name'), $photoName, post('gender') ?: null,
            $dob, $birthYear, $approxAge, $dobKnown,
            post('place_of_birth'), $phone,
            post('marital_status') ?: null, post('baptism') ?: null, $dayBorn,
        ]);
        $memberId = (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        $stmt = $pdo->prepare("INSERT INTO members
            (full_name, photo, gender, dob, place_of_birth, phone,
             marital_status, baptism, day_born)
            VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            post('full_name'), $photoName, post('gender') ?: null,
            $dob, post('place_of_birth'), $phone,
            post('marital_status') ?: null, post('baptism') ?: null, $dayBorn,
        ]);
        $memberId = (int)$pdo->lastInsertId();
    }

    // Auto-create login
    $loginResult = createMemberLogin($pdo, $memberId, post('full_name'), $phone);

    if ($loginResult['created']) {
        flash('✅ Member registered. Login created → username: '
            . $loginResult['username'] . ' · password: password@123');
    } elseif ($loginResult['reason'] === 'no_phone') {
        flash('✅ Member registered. ⚠️ No phone number — login NOT created.');
    } elseif ($loginResult['reason'] === 'username_taken') {
        flash('✅ Member registered. ⚠️ Login NOT created — username "'
            . $loginResult['username'] . '" already exists.');
    } else {
        flash('✅ Member registered. ⚠️ Login could not be created.');
    }

    redirect('members.php');
}

/* ============================================================
   DELETE MEMBER
   ============================================================ */
if ($canEdit && isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    $pdo->prepare("DELETE FROM users WHERE member_id = ? AND role = 'member'")
        ->execute([$id]);

    $stmt = $pdo->prepare("SELECT photo FROM members WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row && !empty($row['photo'])) {
        $path = $UPLOAD_DIR . $row['photo'];
        if (is_file($path)) @unlink($path);
    }

    $pdo->prepare("DELETE FROM members WHERE id = ?")->execute([$id]);
    flash('🗑 Member deleted (and linked login removed)');
    redirect('members.php');
}

/* ============================================================
   SEARCH
   ============================================================ */
$search = get('q');
if ($search) {
    $stmt = $pdo->prepare("SELECT * FROM members
        WHERE full_name LIKE ? OR phone LIKE ? ORDER BY full_name");
    $stmt->execute(["%$search%", "%$search%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM members ORDER BY full_name");
}
$members = $stmt->fetchAll();

/* Fetch linked logins */
$memberIds = array_column($members, 'id');
$logins = [];
if ($memberIds) {
    $in = implode(',', array_fill(0, count($memberIds), '?'));
    $stmt = $pdo->prepare("SELECT member_id, username FROM users
                           WHERE member_id IN ($in) AND role='member'");
    $stmt->execute($memberIds);
    foreach ($stmt->fetchAll() as $row) {
        $logins[$row['member_id']] = $row['username'];
    }
}
?>

<!-- ============================================================
     REGISTRATION FORM
     ============================================================ -->
<?php if ($canEdit): ?>
<div class="card">
  <h3>➕ Register New Member</h3>

  <div style="background:#ebf8ff;border-left:4px solid #3182ce;
              padding:10px 12px;border-radius:8px;font-size:13px;
              color:#2c4538;margin-bottom:14px;">
    💡 <strong>Auto-login:</strong> When you save, a login is created
    automatically with the <strong>phone number</strong> as username and
    <strong>password@123</strong> as default password.
  </div>

  <form method="post" enctype="multipart/form-data" autocomplete="off">
    <div class="grid grid-2">

      <div style="grid-column:1/-1;text-align:center;">
        <div class="photo-preview-wrap">
          <img id="photoPreview" class="photo-preview"
               src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='48' fill='%23e2e8f0'/><text x='50' y='62' font-size='40' text-anchor='middle' fill='%23718096' font-family='sans-serif'>👤</text></svg>"
               alt="Preview">
        </div>
        <label class="photo-label" for="photoInput">📷 Choose Photo (optional)</label>
        <input type="file" id="photoInput" name="photo"
               accept="image/*" capture="environment" style="display:none;">
        <div style="font-size:12px;color:#718096;margin-top:4px;">
          Max 3 MB · JPG / PNG / WEBP
        </div>
      </div>

      <div><label>Full Name *</label>
        <input name="full_name" required placeholder="e.g. John Mensah"></div>

      <div>
        <label>Phone Number * <span style="color:#e53e3e;">(used as login username)</span></label>
        <input name="phone" type="tel" required placeholder="e.g. 0546007968">
      </div>

      <div><label>Gender</label>
        <select name="gender"><option value="">Select</option>
          <option>Male</option><option>Female</option></select></div>
      <div><label>Place of Birth</label>
        <input name="place_of_birth"></div>
      <div><label>Marital Status</label>
        <select name="marital_status"><option value="">Select</option>
          <option>Single</option><option>Married</option>
          <option>Widowed</option><option>Divorced</option></select></div>
      <div><label>Baptism</label>
        <select name="baptism"><option value="">Select</option>
          <option>Baptized Catholic</option><option>Not Baptized</option>
          <option>Other Denomination</option></select></div>

      <!-- FLEXIBLE DOB -->
      <div style="grid-column:1/-1;background:#f0f4f8;padding:14px;border-radius:10px;margin-top:8px;">
        <div style="font-weight:700;color:#2c5282;margin-bottom:10px;">
          🎂 Date of Birth
        </div>

        <label>How much of the birth date is known?</label>
        <select name="dob_known" id="dobKnown" onchange="toggleDobFields()">
          <option value="full">Full date (e.g. 15 March 1962)</option>
          <option value="year">Year only (e.g. born in 1955)</option>
          <option value="age">Approximate age (e.g. about 65 years)</option>
          <option value="unknown">Not known at all</option>
        </select>

        <div id="dobFullRow" class="grid grid-2" style="margin-top:12px;">
          <div><label>Date of Birth</label>
            <input name="dob" id="dob" type="date"></div>
          <div><label>Day Born (auto)</label>
            <input id="dayBornPreview" readonly placeholder="Select DOB first"></div>
        </div>

        <div id="dobYearRow" class="grid grid-2" style="margin-top:12px;display:none;">
          <div><label>Year of Birth</label>
            <input name="birth_year" id="birthYear" type="number"
                   min="1900" max="<?= date('Y') ?>" placeholder="e.g. 1955"></div>
          <div><label>Day Born (if known)</label>
            <select name="day_born_manual">
              <option value="">— Not known —</option>
              <option>Sunday</option><option>Monday</option><option>Tuesday</option>
              <option>Wednesday</option><option>Thursday</option><option>Friday</option>
              <option>Saturday</option>
            </select></div>
        </div>

        <div id="dobAgeRow" class="grid grid-2" style="margin-top:12px;display:none;">
          <div><label>Approximate Age (years)</label>
            <input name="approx_age" id="approxAge" type="number"
                   min="0" max="130" placeholder="e.g. 65"></div>
          <div><label>Day Born (if known)</label>
            <select name="day_born_manual">
              <option value="">— Not known —</option>
              <option>Sunday</option><option>Monday</option><option>Tuesday</option>
              <option>Wednesday</option><option>Thursday</option><option>Friday</option>
              <option>Saturday</option>
            </select></div>
        </div>

        <div id="dobUnknownRow" style="margin-top:12px;display:none;">
          <div style="background:#fffaf0;border-left:4px solid #dd6b20;padding:12px;border-radius:8px;">
            <div style="font-weight:600;color:#7b341e;margin-bottom:6px;">
              Day Born (if known)
            </div>
            <select name="day_born_manual">
              <option value="">— Not known —</option>
              <option>Sunday</option><option>Monday</option><option>Tuesday</option>
              <option>Wednesday</option><option>Thursday</option><option>Friday</option>
              <option>Saturday</option>
            </select>
          </div>
        </div>
      </div>

    </div>
    <button class="btn-primary" name="save" value="1">Save Member & Create Login</button>
  </form>
</div>

<script>
function toggleDobFields() {
  const v = document.getElementById('dobKnown').value;
  document.getElementById('dobFullRow').style.display    = v === 'full'    ? 'grid' : 'none';
  document.getElementById('dobYearRow').style.display    = v === 'year'    ? 'grid' : 'none';
  document.getElementById('dobAgeRow').style.display     = v === 'age'     ? 'grid' : 'none';
  document.getElementById('dobUnknownRow').style.display = v === 'unknown' ? 'block' : 'none';

  if (v !== 'full') document.getElementById('dob').value = '';
  if (v !== 'year') document.getElementById('birthYear').value = '';
  if (v !== 'age')  document.getElementById('approxAge').value = '';
}
document.addEventListener('DOMContentLoaded', toggleDobFields);

document.getElementById('photoInput').addEventListener('change', e => {
  const file = e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = ev => document.getElementById('photoPreview').src = ev.target.result;
  reader.readAsDataURL(file);
});
</script>
<?php endif; ?>

<!-- ============================================================
     MEMBER LIST
     ============================================================ -->
<div class="card">
  <h3>📋 Members (<?= count($members) ?>)</h3>
  <form method="get" class="search-box">
    <input name="q" value="<?= e($search) ?>" placeholder="🔍 Search name or phone...">
  </form>

  <?php if (!$members): ?>
    <div class="empty">No members found.</div>
  <?php else: ?>
    <div class="member-grid">
      <?php foreach ($members as $m): ?>
        <div class="member-tile">
          <div class="member-tile-photo"><?= memberAvatar($m, 64) ?></div>
          <div class="member-tile-body">
            <div class="member-tile-name"><?= e($m['full_name']) ?></div>
            <div class="member-tile-meta">
              <?= e($m['gender'] ?: '—') ?>
              <?= $m['day_born'] ? ' · ' . e($m['day_born']) . ' Born' : '' ?>
            </div>
            <div class="member-tile-meta">🎂 <?= e(displayDob($m)) ?></div>
            <div class="member-tile-meta">📞 <?= e($m['phone'] ?: 'no phone') ?></div>

            <div class="member-tile-meta">
              <?php if (isset($logins[$m['id']])): ?>
                <span class="badge green">🔑 Login: <?= e($logins[$m['id']]) ?></span>
              <?php else: ?>
                <span class="badge orange">No login</span>
              <?php endif; ?>
            </div>
          </div>

          <div class="member-tile-actions">
            <a class="btn-sm btn-ghost" href="member_card.php?id=<?= (int)$m['id'] ?>" title="Card">🪪</a>
            <?php if ($canEdit): ?>
              <a class="btn-sm btn-ghost" href="member_edit.php?id=<?= (int)$m['id'] ?>" title="Edit">✏️</a>
              <a class="btn-sm btn-danger" data-confirm="Delete this member AND their login?"
                 href="?delete=<?= (int)$m['id'] ?>" title="Delete">🗑</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<style>
.photo-preview-wrap { display: inline-block; margin-bottom: 8px; }
.photo-preview {
  width: 120px; height: 120px; border-radius: 50%; object-fit: cover;
  border: 4px solid #e2e8f0; background: #f7fafc; display: block;
}
.photo-label {
  display: inline-block; margin-top: 10px; padding: 8px 16px;
  background: #6b46c1; color: #fff; border-radius: 999px;
  font-size: 13px; font-weight: 600; cursor: pointer;
}
.photo-label:hover { background: #553c9a; }
.member-grid { display: grid; grid-template-columns: 1fr; gap: 12px; }
@media (min-width: 600px) { .member-grid { grid-template-columns: repeat(2, 1fr); } }
@media (min-width: 900px) { .member-grid { grid-template-columns: repeat(3, 1fr); } }
.member-tile {
  position: relative; display: flex; gap: 12px; padding: 12px;
  background: #f7fafc; border-radius: 14px; border: 1px solid #edf2f7;
  transition: box-shadow 0.15s ease;
}
.member-tile:hover { box-shadow: 0 4px 14px rgba(0,0,0,0.06); }
.member-tile-photo { flex-shrink: 0; }
.member-tile-body { flex: 1; min-width: 0; }
.member-tile-name { font-weight: 700; font-size: 15px; margin-bottom: 4px; word-break: break-word; }
.member-tile-meta { font-size: 12px; color: #718096; line-height: 1.5; }
.member-tile-actions {
  position: absolute; top: 8px; right: 8px;
  display: flex; gap: 4px; flex-wrap: wrap;
}
.member-tile-actions .btn-sm { padding: 4px 8px; font-size: 12px; }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>