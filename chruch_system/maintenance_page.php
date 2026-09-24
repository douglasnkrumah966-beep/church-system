<?php
require_once 'config/database.php';

$m = [];
try {
    $m = $pdo->query("SELECT * FROM maintenance_mode WHERE id=1")->fetch();
} catch (PDOException $e) { $m = []; }

$message = $m['message'] ?? 'System is under maintenance. Please check back shortly.';
$churchName = $settings['church_name'] ?? 'Church';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Under Maintenance · <?= e($churchName) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
  body {
    min-height: 100vh; display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #2d3748, #1a202c);
    padding: 20px; margin: 0;
  }
  .maint-card {
    background: #fff; padding: 40px 30px; border-radius: 20px;
    max-width: 440px; text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
  }
  .maint-icon {
    font-size: 60px; margin-bottom: 10px;
    animation: pulse 2s ease-in-out infinite;
  }
  @keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
  }
  .maint-card h1 { color: #2d3748; font-size: 22px; margin: 0 0 6px; font-weight: 800; }
  .maint-card .sub {
    color: #718096; font-size: 14px; margin-bottom: 20px;
    letter-spacing: 1px; text-transform: uppercase;
  }
  .maint-card .msg {
    color: #4a5568; font-size: 15px; line-height: 1.7;
    background: #f7fafc; padding: 16px; border-radius: 12px;
    margin-bottom: 20px; border-left: 4px solid #dd6b20;
  }
  .maint-card .footer { color: #a0aec0; font-size: 12px; line-height: 1.6; }
</style>
</head>
<body>
  <div class="maint-card">
    <div class="maint-icon">🛠</div>
    <h1><?= e($churchName) ?></h1>
    <p class="sub">Under Maintenance</p>
    <div class="msg"><?= e($message) ?></div>
    <p class="footer">
      We apologize for the inconvenience.<br>
      Please check back shortly.
    </p>
  </div>
</body>
</html>