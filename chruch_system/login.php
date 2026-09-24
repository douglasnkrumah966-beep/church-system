<?php
require_once 'config/database.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = post('username');
    $p = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$u]);
    $user = $stmt->fetch();

    if ($user && password_verify($p, $user['password'])) {
        $_SESSION['user'] = [
            'id'        => $user['id'],
            'username'  => $user['username'],
            'name'      => $user['full_name'],
            'role'      => $user['role'],
            'member_id' => $user['member_id'] ?? null,
        ];

        $landing = match($user['role']) {
            'sysadmin'  => 'admin/index.php',
            'secretary' => 'secretary/index.php',
            'finance'   => 'finance/index.php',
            'catechist' => 'catechist/index.php',
            'admin'     => 'index.php',
            'member'    => 'member/index.php',
            default     => 'index.php',
        };
        redirect($landing);
    }

    $error = 'Invalid username or password';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In · <?= e($settings['church_name']) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }

html, body { height: 100%; }

/* ============================================================
   BACKGROUND — IMAGE FULLY VISIBLE
   ============================================================ */
body {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  padding: 20px;
  position: relative;
  overflow: hidden;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  -webkit-font-smoothing: antialiased;
  color: #e2e8f0;

  /* === THE IMAGE === */
  background-color: #1e293b;
  background-image: url('assets/images/our-lady.jpg');
  background-size: cover;
  background-position: center 30%;
  background-repeat: no-repeat;
  background-attachment: fixed;
}

/* ============================================================
   VERY LIGHT VIGNETTE — keeps focus on the card
   ============================================================ */
body::before {
  content: '';
  position: fixed;
  inset: 0;
  background: radial-gradient(circle at center,
              rgba(10, 22, 40, 0.15) 0%,
              rgba(10, 22, 40, 0.35) 70%,
              rgba(10, 22, 40, 0.55) 100%);
  pointer-events: none;
  z-index: 1;
}

/* ============================================================
   LOGIN CARD — glassy so image shows through
   ============================================================ */
.login-container {
  position: relative;
  z-index: 3;
  width: 100%;
  max-width: 420px;
}

.login-card {
  background: rgba(15, 23, 42, 0.68);
  backdrop-filter: blur(20px) saturate(140%);
  -webkit-backdrop-filter: blur(20px) saturate(140%);

  border: 1px solid rgba(212, 175, 55, 0.30);
  border-radius: 20px;

  padding: 44px 40px 36px;

  box-shadow:
    0 30px 80px -12px rgba(0, 0, 0, 0.5),
    0 0 0 1px rgba(255, 255, 255, 0.06) inset;

  animation: cardEnter 0.9s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes cardEnter {
  0%   { opacity: 0; transform: translateY(40px) scale(0.94); filter: blur(10px); }
  100% { opacity: 1; transform: translateY(0)    scale(1);    filter: blur(0); }
}

.login-card.shake { animation: cardShake 0.5s ease; }

@keyframes cardShake {
  0%, 100% { transform: translateX(0); }
  15%      { transform: translateX(-10px); }
  30%      { transform: translateX(10px); }
  45%      { transform: translateX(-7px); }
  60%      { transform: translateX(7px); }
  75%      { transform: translateX(-3px); }
}

/* ============================================================
   LOGO
   ============================================================ */
.logo {
  width: 72px;
  height: 72px;
  margin: 0 auto 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36px;

  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  border-radius: 20px;

  box-shadow:
    0 10px 30px -8px rgba(139, 92, 246, 0.55),
    0 0 0 1px rgba(255, 255, 255, 0.15) inset;

  animation: logoPop 0.7s cubic-bezier(0.34, 1.56, 0.64, 1) 0.3s both;
}

@keyframes logoPop {
  0%   { opacity: 0; transform: scale(0.4) rotate(-15deg); }
  100% { opacity: 1; transform: scale(1)   rotate(0deg); }
}

/* ============================================================
   TITLE
   ============================================================ */
.title {
  text-align: center;
  font-size: 26px;
  font-weight: 700;
  color: #f8fafc;
  letter-spacing: -0.5px;
  margin-bottom: 8px;
  animation: fadeUp 0.6s ease-out 0.5s both;
  text-shadow: 0 2px 12px rgba(0, 0, 0, 0.4);
}

.subtitle {
  text-align: center;
  font-size: 14px;
  color: #cbd5e1;
  margin-bottom: 36px;
  animation: fadeUp 0.6s ease-out 0.6s both;
  text-shadow: 0 1px 6px rgba(0, 0, 0, 0.3);
}

@keyframes fadeUp {
  0%   { opacity: 0; transform: translateY(14px); }
  100% { opacity: 1; transform: translateY(0); }
}

/* ============================================================
   ERROR BANNER
   ============================================================ */
.error-banner {
  background: rgba(220, 38, 38, 0.20);
  color: #fecaca;
  border: 1px solid rgba(220, 38, 38, 0.5);
  border-left: 4px solid #dc2626;
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 13px;
  text-align: center;
  margin-bottom: 20px;
  animation: fadeUp 0.3s ease-out;
}

/* ============================================================
   FORM FIELDS
   ============================================================ */
.field {
  position: relative;
  margin-bottom: 22px;
  animation: fieldSlide 0.55s ease-out both;
}

.field:nth-of-type(1) { animation-delay: 0.7s; }
.field:nth-of-type(2) { animation-delay: 0.85s; }

@keyframes fieldSlide {
  0%   { opacity: 0; transform: translateX(-22px); }
  100% { opacity: 1; transform: translateX(0); }
}

.field input {
  width: 100%;
  padding: 16px 18px 16px 52px;
  font-size: 15px;
  font-family: inherit;
  color: #f1f5f9;

  background: rgba(255, 255, 255, 0.10);
  border: 1.5px solid rgba(255, 255, 255, 0.18);
  border-radius: 12px;
  outline: none;

  transition: all 0.25s ease;
}

.field input::placeholder { color: #cbd5e1; }

.field input:focus {
  background: rgba(255, 255, 255, 0.14);
  border-color: rgba(139, 92, 246, 0.75);
  box-shadow:
    0 0 0 4px rgba(139, 92, 246, 0.20),
    0 0 20px -4px rgba(139, 92, 246, 0.4);
}

.field-icon {
  position: absolute;
  left: 18px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 18px;
  color: #cbd5e1;
  pointer-events: none;
  transition: color 0.25s ease;
}

.field:focus-within .field-icon { color: #c4b5fd; }

.field-password input { padding-right: 52px; }

.toggle-password {
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  background: transparent;
  border: none;
  color: #cbd5e1;
  font-size: 18px;
  cursor: pointer;
  padding: 6px 8px;
  border-radius: 8px;
  transition: all 0.2s ease;
  line-height: 1;
  font-family: inherit;
}

.toggle-password:hover {
  color: #c4b5fd;
  background: rgba(139, 92, 246, 0.15);
}

/* ============================================================
   SUBMIT BUTTON
   ============================================================ */
.btn-submit {
  width: 100%;
  padding: 16px 24px;
  font-family: inherit;
  font-size: 15px;
  font-weight: 600;
  letter-spacing: 0.5px;
  color: #fff;

  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  border: none;
  border-radius: 12px;
  cursor: pointer;

  position: relative;
  overflow: hidden;

  box-shadow:
    0 10px 30px -8px rgba(99, 102, 241, 0.55),
    0 0 0 1px rgba(255, 255, 255, 0.15) inset;

  transition: all 0.25s ease;
  animation: fadeUp 0.55s ease-out 1.05s both;
  margin-top: 8px;
}

.btn-submit::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg,
    transparent,
    rgba(255, 255, 255, 0.3),
    transparent);
  transition: left 0.6s ease;
}

.btn-submit:hover {
  transform: translateY(-2px);
  box-shadow:
    0 16px 44px -8px rgba(99, 102, 241, 0.75),
    0 0 0 1px rgba(255, 255, 255, 0.25) inset;
}

.btn-submit:hover::before { left: 100%; }
.btn-submit:active { transform: translateY(0); }

.btn-submit.loading {
  color: transparent;
  pointer-events: none;
}

.btn-submit.loading::after {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  width: 20px;
  height: 20px;
  margin: -10px 0 0 -10px;
  border: 2.5px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* ============================================================
   FOOTER
   ============================================================ */
.footer-note {
  text-align: center;
  margin-top: 28px;
  padding-top: 20px;
  border-top: 1px solid rgba(212, 175, 55, 0.25);

  font-size: 11px;
  color: #cbd5e1;
  letter-spacing: 1.5px;
  text-transform: uppercase;

  animation: fadeUp 0.5s ease-out 1.25s both;
}

.footer-note .church-name {
  color: #f6e05e;
  font-weight: 700;
  letter-spacing: 2px;
  text-shadow: 0 2px 8px rgba(246, 224, 94, 0.4);
}

/* ============================================================
   RESPONSIVE
   ============================================================ */
@media (max-width: 520px) {
  body {
    padding: 14px;
    background-attachment: scroll;
    background-position: center top;
  }

  .login-card {
    padding: 36px 24px 28px;
    border-radius: 18px;
    background: rgba(15, 23, 42, 0.75);
  }

  .logo { width: 60px; height: 60px; font-size: 30px; border-radius: 16px; margin-bottom: 20px; }
  .title { font-size: 22px; }
  .subtitle { font-size: 13px; margin-bottom: 28px; }
  .field { margin-bottom: 18px; }
  .field input { padding: 14px 16px 14px 46px; font-size: 14px; }
  .field-icon { left: 16px; font-size: 16px; }
  .toggle-password { right: 10px; font-size: 16px; }
  .btn-submit { padding: 14px 20px; font-size: 14px; }

  .field:nth-of-type(1) { animation-delay: 0.4s; }
  .field:nth-of-type(2) { animation-delay: 0.5s; }
  .btn-submit            { animation-delay: 0.65s; }
  .footer-note           { animation-delay: 0.85s; }
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
  .field, .btn-submit, .title, .subtitle, .logo, .footer-note {
    opacity: 1 !important;
    transform: none !important;
  }
}
</style>
</head>
<body>

  <div class="login-container">
    <form class="login-card" id="loginForm" method="post" autocomplete="off">

      <div class="logo">✝️</div>

      <h1 class="title">Welcome Back</h1>
      <p class="subtitle">Sign in to continue</p>

      <?php if ($error): ?>
        <div class="error-banner"><?= e($error) ?></div>
      <?php endif; ?>

      <div class="field">
        <span class="field-icon">👤</span>
        <input type="text" name="username" id="username"
               placeholder="Username" autocomplete="username" autofocus required>
      </div>

      <div class="field field-password">
        <span class="field-icon">🔒</span>
        <input type="password" name="password" id="password"
               placeholder="Password" autocomplete="current-password" required>
        <button type="button" class="toggle-password" id="togglePassword"
                aria-label="Show password" title="Show password">👁</button>
      </div>

      <button type="submit" class="btn-submit" id="submitBtn">Sign In</button>

      <div class="footer-note">
        <span class="church-name"><?= e($settings['church_name']) ?></span>
      </div>

    </form>
  </div>

<script>
(function () {
  'use strict';
  const form       = document.getElementById('loginForm');
  const userInput  = document.getElementById('username');
  const passInput  = document.getElementById('password');
  const togglePass = document.getElementById('togglePassword');
  const submitBtn  = document.getElementById('submitBtn');

  if (togglePass) {
    togglePass.addEventListener('click', function () {
      const isPassword = passInput.type === 'password';
      passInput.type = isPassword ? 'text' : 'password';
      this.textContent = isPassword ? '🙈' : '👁';
      passInput.focus();
    });
  }

  userInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && userInput.value.trim() !== '') {
      e.preventDefault();
      passInput.focus();
    }
  });

  form.addEventListener('submit', function () {
    if (userInput.value.trim() === '' || passInput.value === '') return;
    submitBtn.classList.add('loading');
    submitBtn.disabled = true;
  });

  <?php if ($error): ?>
    window.addEventListener('load', function () {
      const card = document.querySelector('.login-card');
      if (card) {
        card.classList.add('shake');
        setTimeout(() => card.classList.remove('shake'), 700);
      }
      passInput.value = '';
      passInput.focus();
    });
  <?php endif; ?>
})();
</script>

</body>
</html>