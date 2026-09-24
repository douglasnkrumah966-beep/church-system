<?php
header('Content-Type: text/plain');
echo "=== SERVER ENVIRONMENT ===\n\n";
echo "HTTPS: "       . var_export($_SERVER['HTTPS'] ?? null, true) . "\n";
echo "PORT: "        . ($_SERVER['SERVER_PORT'] ?? '?') . "\n";
echo "HOST: "        . ($_SERVER['HTTP_HOST'] ?? '?') . "\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? '?') . "\n";
echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? '?') . "\n";
echo "SCHEME: "      . ($_SERVER['REQUEST_SCHEME'] ?? '?') . "\n";
echo "DOC_ROOT: "    . ($_SERVER['DOCUMENT_ROOT'] ?? '?') . "\n\n";

// What would a force-HTTPS check see?
$thinksHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
echo "Would force-HTTPS trigger? " . ($thinksHttps ? 'NO (already HTTPS)' : 'YES (thinks we are on HTTP)') . "\n";

echo "\nIf URL bar shows https:// but this says HTTP → something outside PHP is upgrading.\n";