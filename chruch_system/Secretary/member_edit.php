<?php
require_once __DIR__ . '/_layout.php';
requireRole(['admin','secretary']);

$id = (int)get('id');
if (!$id) { flash('❌ Invalid member'); redirect('members.php'); }

$UPLOAD_DIR = __DIR__ . '/uploads/members/';

$stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
$stmt->execute([$id]);
$member = $stmt->fetch();
if (!$member) { flash('❌ Member not found'); redirect('members.php'); }

/* ============================================================
   AUTO-CREATE MEMBER LOGIN (helper)
   ============================================================ */
function ensureMemberLogin(PDO $pdo, int $memberId, string $fullName, string $phone): array {
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

    // Does a login already exist for this member?
    $stmt = $pdo->prepare("SELECT * FROM users WHERE member_id = ? AND role='member'");
    $stmt->execute([$memberId]);
    $existing = $stmt->fetch();

    if ($cleanPhone === '') {
        return ['status' => 'no_phone'];
    }

    if ($existing) {
        // Sync username/full_name if changed
        if ($existing['username'] !== $cleanPhone) {
            // Check the new username isn't already used by someone else
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id <> ?");
            $stmt->execute([$cleanPhone, $existing['id']]);
            if ($stmt->fetch()) {
                return ['status' => 'username_taken', 'username' => $cleanPhone];
            }
            $pdo->prepare("UPDATE users SET username = ?, full_name = ? WHERE id = ?")
                ->execute([$cleanPhone, $fullName, $existing['id']]);
            return ['status' => 'username_updated', 'username' => $cleanPhone];
        }
        // Just update full name if changed
        if ($existing['full_name'] !== $fullName) {
            $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?")
                ->execute([$fullName, $existing['id']]);
        }
        return ['status' => 'already_linked'];
    }

    // No login yet → create one
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$cleanPhone]);
    if ($stmt->fetch()) {
        return ['status' => 'username_taken', 'username' => $cleanPhone];
    }

    $hash = password_hash('password@123', PASSWORD_DEFAULT);
    try {
        $stmt = $pdo->prepare("INSERT INTO users
            (username, password, full_name, role, member_id)
            VALUES (?,?,?, 'member', ?)");
        $stmt->execute([$cleanPhone, $hash, $fullName, $memberId]);
        return ['status' => 'created', 'username' => $cleanPhone];
    } catch (PDOException $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

/* ---------------- Save changes ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {

    $dobKnown  = post('dob_known') ?: ($member['dob_known'] ?? 'full');
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
        if ($approxAge) $birthYear = (int)'date'('Y') - $approxAge;
        $dayBorn = post('day_born_manual') ?: null;
    } else {
        $dayBorn = post('day_born_manual') ?: null;
    }

    // Photo handling
    $photoName = $member['photo'];

    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['photo'];
        if ($file['size'] > 3 * 1024 * 1024) {
            flash('❌ Photo too large (max 3 MB)');
            redirect("member_edit.php?id=$id");
        }
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
        $mime = mime_content_type($file['tmp_name']);
        if (!isset($allowed[$mime])) {
            flash('❌ Only JPG, PNG, WEBP, or GIF images allowed');
            redirect("member_edit.php?id=$id");
        }
        $ext  = $allowed[$mime];
        $newName = 'm_' . 'date'('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $UPLOAD_DIR . $newName)) {
            if (!empty($member['photo']) && is_file($UPLOAD_DIR . $member['photo'])) {
                @unlink($UPLOAD_DIR . $member['photo']);
            }
            $photoName = $newName;
        }
    }

    if (!empty($_POST['remove_photo'])) {
        if (!empty($member['photo']) && is_file($UPLOAD_DIR . $member['photo'])) {
            @unlink($UPLOAD_DIR . $member['photo']);
        }
        $photoName = null;
    }

    // Update member
    try {
        $stmt = $pdo->prepare("UPDATE members SET
            full_name=?, photo=?, gender=?, dob=?, birth_year=?, approx_age=?, dob_known=?,
            place_of_birth=?, phone=?, marital_status=?, baptism=?, day_born=?
            WHERE id=?");
        $stmt->execute([
            post('full_name'), $photoName, post('gender') ?: null,
            $dob, $birthYear, $approxAge, $dobKnown,
            post('place_of_birth'), post('phone'),
            post('marital_status') ?: null, post('baptism') ?: null,
            $dayBorn, $id
        ]);
    } catch (PDOException $e) {
        $stmt = $pdo->prepare("UPDATE members SET
            full_name=?, photo=?, gender=?, dob=?,
            place_of_birth=?, phone=?, marital_status=?, baptism=?, day_born=?
            WHERE id=?");
        $stmt->execute([
            post('full_name'), $photoName, post('gender') ?: null,
            $dob, post('place_of_birth'), post('phone'),
            post('marital_status') ?: null, post('baptism') ?: null,
            $dayBorn, $id
        ]);
    }

    /* ---------------- Ensure login is in sync ---------------- */
    $loginResult = ensureMemberLogin(
        $pdo,
        $id,
        post('full_name'),
        post('phone')
    );

    switch ($loginResult['status']) {
        case 'created':
            flash('✅ Member updated. 🔑 Login created → username: '
                . $loginResult['username'] . ' · password: password@123');
            break;
        case 'username_updated':
            flash('✅ Member updated. 🔑 Username synced to: ' . $loginResult['username']);
            break;
        case 'username_taken':
            flash('✅ Member updated. ⚠️ Username "' . $loginResult['username']
                . '" is already used by another account.');
            break;
        case 'no_phone':
            flash('✅ Member updated. ⚠️ No phone number — login NOT created.');
            break;
        case 'already_linked':
        default:
            flash('✅ Member updated');
            break;
    }

    redirect('members.php');
}

/* ---------- Check if member already has a login (for display) ---------- */
$stmt = $pdo->prepare("SELECT username FROM users WHERE member_id = ? AND role='member'");
$stmt->execute([$id]);
$existingLogin = $stmt->fetch();

include 'includes/header.php';
?>

<a class="btn-sm btn-ghost" style="display:inline-block;margin-bottom:12px;"
   href="members.php">← Back to Members</a>

<div class="card">
  <h3>✏️ Edit Member — <?= e($member['full_name']) ?></h3>

  <!-- Login status banner -->
  <?php if ($existingLogin): ?>
    <div style="background:#f0fff4;border-left:4px solid #38a169;padding:10px 12px;
                border-radius:8px;font-size:13px;color:#22543d;margin-bottom:14px;">
      🔑 <strong>Login active:</strong> username
      <code><?= e($existingLogin['username']) ?></code>
      · default password <code>password@123</code>
    </div>
  <?php else: ?>
    <div style="background:#fffaf0;border-left:4px solid #dd6b20;padding:10px 12px;
                border-radius:8px;font-size:13px;color:#7b341e;margin-bottom:14px;">
      ⚠️ <strong>No login yet.</strong> Add a phone number below and save
      — the login will be created automatically.
    </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" autocomplete="off">
    <div class="grid grid-2">

      <div style="grid-column:1/-1;text-align:center;">
        <div class="photo-preview-wrap">
          <?php
          $photoUrl = memberPhoto($member);
          $initial  = strtoupper(substr($member['full_name'], 0, 1));
          ?>
          <?php if ($photoUrl): ?>
            <img id="photoPreview" class="photo-preview" src="<?= e($photoUrl) ?>" alt="">
          <?php else: ?>
            <div id="photoPreviewFallback" class="photo-preview photo-preview-fallback">
              <?= e($initial) ?>
            </div>
            <img id="photoPreview" class="photo-preview" src="" alt="" style="display:none;">
          <?php endif; ?>
        </div>

        <div>
          <label class="photo-label" for="photoInput">📷 Change Photo</label>
          <input type="file" id="photoInput" name="photo" accept="image/*" style="display:none;">
        </div>

        <?php if (!empty($member['photo'])): ?>
          <label style="display:inline-block;margin-top:10px;font-size:13px;color:#742a2a;cursor:pointer;">
            <input type="checkbox" name="remove_photo" value="1"
                   style="width:auto;margin-right:6px;"> Remove current photo
          </label>
        <?php endif; ?>
      </div>

      <div><label>Full Name *</label>
        <input name="full_name" value="<?= e($member['full_name']) ?>" required></div>

      <div>
        <label>Phone Number <span style="color:#e53e3e;font-size:11px;">(login username)</span></label>
        <input name="phone" type="tel" value="<?= e($member['phone']) ?>"
               placeholder="e.g. 0546007968">
      </div>

      <div><label>Gender</label>
        <select name="gender">
          <option value="">Select</option>
          <?php foreach (['Male','Female'] as $g): ?>
            <option <?= $member['gender']===$g?'selected':'' ?>><?= $g ?></option>
          <?php endforeach; ?>
        </select></div>

      <div><label>Place of Birth</label>
        <input name="place_of_birth" value="<?= e($member['place_of_birth']) ?>"></div>

      <div><label>Marital Status</label>
        <select name="marital_status">
          <option value="">Select</option>
          <?php foreach (['Single','Married','Widowed','Divorced'] as $s): ?>
            <option <?= $member['marital_status']===$s?'selected':'' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select></div>

      <div><label>Baptism</label>
        <select name="baptism">
          <option value="">Select</option>
          <?php foreach (['Baptized Catholic','Not Baptized','Other Denomination'] as $b): ?>
            <option <?= $member['baptism']===$b?'selected':'' ?>><?= $b ?></option>
          <?php endforeach; ?>
        </select></div>

      <!-- FLEXIBLE DOB -->
      <?php
      $currMode = $member['dob_known'] ?? 'full';
      if (empty($currMode)) {
          if (!empty($member['dob'])) $currMode = 'full';
          elseif (!empty($member['birth_year'])) $currMode = 'year';
          elseif (!empty($member['approx_age'])) $currMode = 'age';
          else $currMode = 'unknown';
      }
      ?>
      <div style="grid-column:1/-1;background:#f0f4f8;padding:14px;border-radius:10px;margin-top:8px;">
        <div style="font-weight:700;color:#2c5282;margin-bottom:10px;">
          🎂 Date of Birth
        </div>

        <label>How much of the birth date is known?</label>
        <select name="dob_known" id="dobKnown" onchange="toggleDobFields()">
          <option value="full"    <?= $currMode==='full'    ?'selected':'' ?>>Full date</option>
          <option value="year"    <?= $currMode==='year'    ?'selected':'' ?>>Year only</option>
          <option value="age"     <?= $currMode==='age'     ?'selected':'' ?>>Approximate age</option>
          <option value="unknown" <?= $currMode==='unknown' ?'selected':'' ?>>Not known at all</option>
        </select>

        <div id="dobFullRow" class="grid grid-2" style="margin-top:12px;">
          <div><label>Date of Birth</label>
            <input name="dob" id="dob" type="date" value="<?= e($member['dob']) ?>"></div>
          <div><label>Day Born (auto)</label>
            <input id="dayBornPreview" value="<?= e($member['day_born']) ?>" readonly></div>
        </div>

        <div id="dobYearRow" class="grid grid-2" style="margin-top:12px;">
          <div><label>Year of Birth</label>
            <input name="birth_year" id="birthYear" type="number"
                   min="1900" max="<?= 'date'('Y') ?>"
                   value="<?= e($member['birth_year'] ?? '') ?>"></div>
          <div><label>Day Born (if known)</label>
            <select name="day_born_manual">
              <option value="">— Not known —</option>
              <?php foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $d): ?>
                <option <?= $member['day_born']===$d?'selected':'' ?>><?= $d ?></option>
              <?php endforeach; ?>
            </select></div>
        </div>

        <div id="dobAgeRow" class="grid grid-2" style="margin-top:12px;">
          <div><label>Approximate Age (years)</label>
            <input name="approx_age" id="approxAge" type="number"
                   min="0" max="130" value="<?= e($member['approx_age'] ?? '') ?>"></div>
          <div><label>Day Born (if known)</label>
            <select name="day_born_manual">
              <option value="">— Not known —</option>
              <?php foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $d): ?>
                <option <?= $member['day_born']===$d?'selected':'' ?>><?= $d ?></option>
              <?php endforeach; ?>
            </select></div>
        </div>

        <div id="dobUnknownRow" style="margin-top:12px;">
          <div style="background:#fffaf0;border-left:4px solid #dd6b20;padding:12px;border-radius:8px;">
            <div style="font-weight:600;color:#7b341e;margin-bottom:6px;">
              Day Born (if known)
            </div>
            <select name="day_born_manual">
              <option value="">— Not known —</option>
              <?php foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $d): ?>
                <option <?= $member['day_born']===$d?'selected':'' ?>><?= $d ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

    </div>

    <button class="btn-primary" name="update" value="1">Save Changes</button>
  </form>
</div>

<style>
.photo-preview-wrap { display: inline-block; margin-bottom: 8px; }
.photo-preview {
  width: 120px; height: 120px; border-radius: 50%; object-fit: cover;
  border: 4px solid #e2e8f0; background: #f7fafc; display: block;
}
.photo-preview-fallback {
  background: #6b46c1; color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-size: 48px; font-weight: 700;
}
.photo-label {
  display: inline-block; margin-top: 10px; padding: 8px 16px;
  background: #6b46c1; color: #fff; border-radius: 999px;
  font-size: 13px; font-weight: 600; cursor: pointer;
}
.photo-label:hover { background: #553c9a; }
</style>

<script>
function toggleDobFields() {
  const v = document.getElementById('dobKnown').value;
  document.getElementById('dobFullRow').style.display    = v === 'full'    ? 'grid' : 'none';
  document.getElementById('dobYearRow').style.display    = v === 'year'    ? 'grid' : 'none';
  document.getElementById('dobAgeRow').style.display     = v === 'age'     ? 'grid' : 'none';
  document.getElementById('dobUnknownRow').style.display = v === 'unknown' ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', toggleDobFields);

document.getElementById('photoInput').addEventListener('change', e => {
  const file = e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = ev => {
    const img = document.getElementById('photoPreview');
    const fb  = document.getElementById('photoPreviewFallback');
    img.src = ev.target.result;
    img.style.display = 'block';
    if (fb) fb.style.display = 'none';
  };
  reader.readAsDataURL(file);
});
</script>

<?php include 'includes/footer.php'; ?>