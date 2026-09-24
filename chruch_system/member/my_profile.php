<?php
/* ============================================================
   MY PROFILE — Member self-service
   ------------------------------------------------------------
   Redirects must happen BEFORE _layout.php prints HTML.
   ============================================================ */

require_once __DIR__ . '/../includes/auth.php';
requireRole(['member']);

$u = currentUser();
$member = myMember($pdo);

/* Redirect before any output */
if (!$member) {
    flash('❌ No member record linked to your account');
    redirect('index.php');
}

/* Ensure upload folder exists (create it if missing) */
$UPLOAD_DIR = __DIR__ . '/../uploads/members/';
if (!is_dir($UPLOAD_DIR)) {
    @mkdir($UPLOAD_DIR, 0775, true);
}

/* ============================================================
   HANDLE SAVE — BEFORE layout
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $photoName = $member['photo'];

    /* Replace photo */
    if (!empty($_FILES['photo']['name'])
        && isset($_FILES['photo']['error'])
        && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {

        $file = $_FILES['photo'];

        if ($file['size'] > 3 * 1024 * 1024) {
            flash('❌ Photo too large (max 3 MB)');
            redirect('my_profile.php');
        }

        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
        $mime = function_exists('mime_content_type')
              ? mime_content_type($file['tmp_name'])
              : ($file['type'] ?? '');

        if (!isset($allowed[$mime])) {
            flash('❌ Only JPG, PNG, WEBP, or GIF images allowed');
            redirect('my_profile.php');
        }

        /* Make sure the folder exists */
        if (!is_dir($UPLOAD_DIR)) {
            @mkdir($UPLOAD_DIR, 0775, true);
        }

        if (!is_writable($UPLOAD_DIR)) {
            flash('❌ Upload folder is not writable. Contact the administrator.');
            redirect('my_profile.php');
        }

        $ext = $allowed[$mime];
        $newName = 'm_' . 'date'('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = rtrim($UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $newName;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            // Delete old photo
            if (!empty($member['photo'])) {
                $oldPath = $UPLOAD_DIR . $member['photo'];
                if (is_file($oldPath)) @unlink($oldPath);
            }
            $photoName = $newName;
        } else {
            flash('❌ Could not save photo. Check folder permissions.');
            redirect('my_profile.php');
        }
    }

    /* Update DB */
    try {
        $stmt = $pdo->prepare("UPDATE members SET
            photo = ?, phone = ?, place_of_birth = ?, marital_status = ?
            WHERE id = ?");
        $stmt->execute([
            $photoName,
            post('phone'),
            post('place_of_birth'),
            post('marital_status') ?: null,
            (int)$member['id']
        ]);
        flash('✅ Profile updated');
    } catch (PDOException $e) {
        flash('❌ Could not update profile');
    }

    redirect('my_profile.php');
}

/* ============================================================
   NOW include the layout (HTML output starts here)
   ============================================================ */
require_once __DIR__ . '/_layout.php';
?>

<div class="card">
  <h3>👤 My Profile</h3>
  <form method="post" enctype="multipart/form-data">

    <div style="text-align:center;margin-bottom:16px;">
      <?= memberAvatar($member, 120) ?>
      <div style="margin-top:10px;">
        <label class="photo-label" for="photoInput"
               style="display:inline-block;padding:10px 18px;background:#805ad5;
                      color:#fff;border-radius:999px;font-size:13px;font-weight:600;
                      cursor:pointer;">
          📷 Change Photo
        </label>
        <input type="file" id="photoInput" name="photo" accept="image/*"
               style="display:none;">
      </div>
      <div style="font-size:12px;color:#718096;margin-top:6px;">
        Max 3 MB · JPG / PNG / WEBP
      </div>
    </div>

    <div style="background:#faf5ff;padding:14px;border-radius:10px;margin-bottom:14px;">
      <div style="font-size:11px;color:#718096;text-transform:uppercase;font-weight:600;">
        Name
      </div>
      <div style="font-weight:700;font-size:16px;"><?= e($member['full_name']) ?></div>
      <div style="font-size:12px;color:#718096;margin-top:8px;">
        🎂 <?= e(displayDob($member)) ?>
        · <?= e($member['day_born'] ? $member['day_born'] . ' Born' : '—') ?>
      </div>
    </div>

    <div class="grid grid-2">
      <div><label>Phone</label>
        <input name="phone" type="tel" value="<?= e($member['phone'] ?? '') ?>"></div>
      <div><label>Place of Birth</label>
        <input name="place_of_birth" value="<?= e($member['place_of_birth'] ?? '') ?>"></div>
      <div><label>Marital Status</label>
        <select name="marital_status">
          <option value="">Select</option>
          <?php foreach (['Single','Married','Widowed','Divorced'] as $s): ?>
            <option <?= ($member['marital_status'] ?? '') === $s ? 'selected' : '' ?>>
              <?= $s ?>
            </option>
          <?php endforeach; ?>
        </select></div>
    </div>

    <button class="btn-primary" name="update" value="1">Save Changes</button>
  </form>
</div>

<p style="text-align:center;color:#718096;font-size:13px;">
  To change your name, date of birth, or baptism status,<br>
  please contact the Station Office.
</p>

<script>
document.getElementById('photoInput').addEventListener('change', e => {
  const file = e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = ev => {
    document.querySelectorAll('img[src*="uploads/members/"]').forEach(img => {
      img.src = ev.target.result;
    });
    const av = document.querySelector('.avatar');
    if (av) av.style.display = 'none';
  };
  reader.readAsDataURL(file);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>