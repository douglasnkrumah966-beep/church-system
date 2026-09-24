<?php
/* Temporary fix — forces HTTP by overriding HTTPS env */
$_SERVER['HTTPS'] = 'off';
$_SERVER['SERVER_PORT'] = 80;
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http';

require 'config/database.php';

echo "HTTPS env: " . var_export($_SERVER['HTTPS'], true) . "<br>";
echo "Port: " . $_SERVER['SERVER_PORT'] . "<br>";
echo "Host: " . ($_SERVER['HTTP_HOST'] ?? 'none') . "<br>";
echo "Protocol: " . ($_SERVER['REQUEST_SCHEME'] ?? 'none') . "<br>";
echo "<br>Actual HTTPS detected by server: " .
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'YES' : 'NO');
echo "<br><br><a href='login.php'>Go to Login</a>";