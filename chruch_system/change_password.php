<?php
require_once 'includes/auth.php';
requireLogin();

$u = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current'] ?? '';
    $new     = $_POST['new']     ?? '';
    $confirm = $_POST['confirm'] ?? '';

    // Fetch user record
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$u['id']]);
    $user = $stmt->fetch();

    if (!password_verify($current, $user['password'])) {
        flash('❌ Current password is incorrect');
    } elseif (strlen($new) < 6) {
        flash('❌ New password must be at least 6 characters');
    } elseif ($new !== $confirm) {
        flash('❌ New passwords do not match');
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
            ->execute([$hash, $u['id']]);
        flash('✅ Password changed successfully');
    }
    redirect('change_password.php');
}

include 'includes/header.php';
?>

<div class="card" style="max-width:480px;margin:0 auto;">
  <h3>🔒 Change My Password</h3>

  <form method="post">
    <label>Current Password</label>
    <input name="current" type="password" required autocomplete="current-password">

    <label>New Password (min 6 characters)</label>
    <input name="new" type="password" required minlength="6" autocomplete="new-password">

    <label>Confirm New Password</label>
    <input name="confirm" type="password" required minlength="6" autocomplete="new-password">

    <button class="btn-primary" type="submit">Change Password</button>
  </form>

  <div style="margin-top:14px;font-size:12px;color:#718096;text-align:center;">
    Logged in as <strong><?= e($u['username']) ?></strong>
    (<?= e($u['role']) ?>)
  </div>
</div>

<?php include 'includes/footer.php'; ?>