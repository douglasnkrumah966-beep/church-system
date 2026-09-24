<?php
/* ============================================================
   AUTH & AUTHORIZATION
   ------------------------------------------------------------
   Loads DB config, defines role helpers, and enforces
   maintenance mode on every page load.
   ============================================================ */
require_once __DIR__ . '/../config/database.php';

/* ============================================================
   LOGIN CHECKS
   ============================================================ */
function isLoggedIn() { return isset($_SESSION['user']); }

function requireLogin() {
    if (!isLoggedIn()) redirect('login.php');
}

function requireRole($roles) {
    requireLogin();
    if (!in_array($_SESSION['user']['role'], (array)$roles, true)) {
        http_response_code(403);
        die("<h2 style='font-family:sans-serif;padding:40px;text-align:center'>
             ⛔ Access Denied<br><small>Your role does not permit this page.</small>
             <br><br><a href='index.php'>← Back to Dashboard</a></h2>");
    }
}

function currentUser() { return $_SESSION['user'] ?? null; }

/* ============================================================
   MY MEMBER — returns the logged-in member's own record
   ============================================================ */
function myMember(PDO $pdo): ?array {
    $u = currentUser();
    if (!$u || empty($u['member_id'])) return null;
    $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$u['member_id']]);
    return $stmt->fetch() ?: null;
}

/* ============================================================
   ROLE CONSTANTS
   ============================================================ */
const ROLE_SYSADMIN   = 'sysadmin';
const ROLE_ADMIN      = 'admin';
const ROLE_SECRETARY  = 'secretary';
const ROLE_FINANCE    = 'finance';
const ROLE_CATECHIST  = 'catechist';
const ROLE_MEMBER     = 'member';

/* ---------- Role group helpers ---------- */
function isStaff($role = null) {
    $role = $role ?: (currentUser()['role'] ?? null);
    return in_array($role, [
        ROLE_SYSADMIN, ROLE_ADMIN, ROLE_SECRETARY, ROLE_FINANCE, ROLE_CATECHIST
    ], true);
}

function canEditMembers($role = null) {
    $role = $role ?: (currentUser()['role'] ?? null);
    return in_array($role, [ROLE_ADMIN, ROLE_SECRETARY], true);
}

function canEditSacraments($role = null) {
    $role = $role ?: (currentUser()['role'] ?? null);
    return in_array($role, [ROLE_ADMIN, ROLE_SECRETARY, ROLE_CATECHIST], true);
}

function canManageFinance($role = null) {
    $role = $role ?: (currentUser()['role'] ?? null);
    return in_array($role, [ROLE_ADMIN, ROLE_FINANCE], true);
}

function canManageSystem($role = null) {
    $role = $role ?: (currentUser()['role'] ?? null);
    return $role === ROLE_SYSADMIN;
}

function blockIfSysadmin($page) {
    if (currentUser()['role'] === ROLE_SYSADMIN && !str_starts_with($page, 'admin/')) {
        redirect('admin/index.php');
    }
}

function requireSysadmin() {
    requireLogin();
    if ((currentUser()['role'] ?? '') !== ROLE_SYSADMIN) {
        http_response_code(403);
        die("<h2 style='font-family:sans-serif;padding:40px;text-align:center'>
             ⛔ Admin access only<br>
             <small>This page is for the System Administrator.</small>
             <br><br><a href='index.php'>← Back</a></h2>");
    }
}

/* ============================================================
   MAINTENANCE MODE
   ------------------------------------------------------------
   Defined here but NOT called yet — the auto-call is at the
   bottom of this file so it always runs on page load.
   ============================================================ */
function checkMaintenance(PDO $pdo): void {
    /* Skip if not logged in yet */
    if (!isset($_SESSION['user'])) return;

    /* Skip certain pages — never redirect these */
    $current = basename($_SERVER['PHP_SELF']);
    $skipPages = ['maintenance_page.php', 'logout.php', 'login.php'];
    if (in_array($current, $skipPages, true)) return;

    /* Sysadmin always passes */
    if (($_SESSION['user']['role'] ?? '') === ROLE_SYSADMIN) return;

    /* Load maintenance state */
    try {
        $m = $pdo->query("SELECT * FROM maintenance_mode WHERE id=1")->fetch();
    } catch (PDOException $e) {
        return; /* table missing — no enforcement */
    }

    if (!$m || empty($m['enabled'])) return;

    /* Check allowed roles */
    $allowed = array_filter(array_map('trim', explode(',', $m['allowed_roles'] ?? '')));
    if (in_array($_SESSION['user']['role'] ?? '', $allowed, true)) return;

    /* Determine redirect target — subfolder pages need ../ */
    $isSubfolder = (strpos($_SERVER['PHP_SELF'], '/member/') !== false)
                || (strpos($_SERVER['PHP_SELF'], '/secretary/') !== false)
                || (strpos($_SERVER['PHP_SELF'], '/finance/') !== false)
                || (strpos($_SERVER['PHP_SELF'], '/admin/') !== false);

    $target = $isSubfolder ? '../maintenance_page.php' : 'maintenance_page.php';

    /* Prevent redirect loop if the target is already this file */
    if (basename($_SERVER['PHP_SELF']) === 'maintenance_page.php') return;

    header('Location: ' . $target);
    exit;
}

/* ============================================================
   AUTO-ENFORCE MAINTENANCE MODE
   ------------------------------------------------------------
   Runs on every page load. Safe: skips if not logged in.
   ============================================================ */
if (isset($_SESSION['user'])) {
    checkMaintenance($pdo);
}