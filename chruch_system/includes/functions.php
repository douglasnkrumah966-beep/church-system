<?php
/* ============================================================
   FUNCTIONS.PHP
   Shared helpers for the Church Management System
   ============================================================ */

function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

/* ============================================================
   MONEY FORMATTER
   ============================================================ */
function money($n) {
    global $settings;
    $value = round((float) $n + 0.0000001, 2);
    $formatted = number_format($value, 2, '.', '');
    [$int, $dec] = explode('.', $formatted);
    $int = number_format((int) $int, 0, '.', ',');
    $symbol = $settings['currency'] ?? 'GH₵';
    return $symbol . $int . '.' . $dec;
}

function cleanAmount($input): float {
    $s = preg_replace('/[^0-9.\-]/', '', (string) $input);
    if ($s === '' || $s === '-') return 0.0;
    return round((float) $s, 2);
}

function flash($msg = null) {
    if ($msg !== null) { $_SESSION['flash'] = $msg; return; }
    if (!empty($_SESSION['flash'])) {
        $m = $_SESSION['flash']; unset($_SESSION['flash']); return $m;
    }
    return null;
}

function redirect($url) { header("Location: $url"); exit; }
function post($key, $default = '') { return trim($_POST[$key] ?? $default); }
function get($key, $default = '')  { return trim($_GET[$key]  ?? $default); }

function dayOfWeek($date) {
    return $date ? 'date'('l', 'strtotime'($date)) : null;
}

/* ============================================================
   ROLE LABELS & BADGES
   ============================================================ */
function roleLabel($role) {
    return match($role) {
        'sysadmin'  => 'System Administrator',
        'admin'     => 'Station Administrator',
        'secretary' => 'Station Secretary',
        'finance'   => 'Finance Officer',
        'catechist' => 'Catechist',
        'member'    => 'Member',
        default     => ucfirst((string)$role),
    };
}

function roleLabelLong($role) {
    return match($role) {
        'sysadmin'  => '🔧 System Administrator',
        'admin'     => '✝️ Station Administrator',
        'secretary' => '✍️ Station Secretary',
        'finance'   => '💰 Finance Officer',
        'catechist' => '📖 Catechist',
        'member'    => '🙏 Member',
        default     => ucfirst((string)$role),
    };
}

function roleBadge($role) {
    $colors = [
        'sysadmin'  => '#1a365d',
        'admin'     => '#805ad5',
        'secretary' => '#3182ce',
        'finance'   => '#38a169',
        'catechist' => '#dd6b20',
        'member'    => '#718096',
    ];
    $c = $colors[$role] ?? '#718096';
    return "<span class='badge' style='background:$c;color:#fff'>"
         . e(roleLabel($role)) . "</span>";
}

/* ============================================================
   FINANCE HELPERS
   ============================================================ */
function renderFinanceForm($title, $categoryOptions, $defaultCategory = '', $actionUrl = null) {
    $actionUrl = $actionUrl ?: basename($_SERVER['PHP_SELF']);
    ?>
    <div class="card">
      <h3><?= e($title) ?></h3>
      <form method="post">
        <div class="grid grid-2">
          <div><label>Category</label>
            <select name="category">
              <?php foreach ($categoryOptions as $c): ?>
                <option <?= $c === $defaultCategory ? 'selected' : '' ?>><?= e($c) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><label>Amount</label><input name="amount" type="number" step="0.01" required></div>
          <div><label>Date</label><input name="date" type="date" value="<?= 'date'('Y-m-d') ?>" required></div>
          <div><label>Note</label><input name="note" placeholder="Optional"></div>
        </div>
        <button class="btn-primary" name="save" value="1">Save</button>
      </form>
    </div>
    <?php
}

function saveFinanceEntry($pdo, $type, $defaultCategory) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['save'])) return;
    $amount = cleanAmount(post('amount'));
    if ($amount <= 0) { flash('❌ Enter a valid amount'); redirect(basename($_SERVER['PHP_SELF'])); }
    $stmt = $pdo->prepare("INSERT INTO finances (type,category,amount,date,note,recorded_by)
                           VALUES (?,?,?,?,?,?)");
    $stmt->execute([
      $type, post('category') ?: $defaultCategory, $amount,
      post('date'), post('note'), currentUser()['name']
    ]);
    flash('✅ Saved');
    redirect(basename($_SERVER['PHP_SELF']));
}

/* ============================================================
   CONSTANTS
   ============================================================ */
const CONTRIBUTION_TYPES = [
    'Thanksgiving Offering',
    'Harvest Contribution',
    'Welfare Contribution',
    'Building Fund',
    'Donation',
    'Funeral Contribution',
    'Fundraising',
    'Special Appeal',
    'Other Contribution',
];

const EXPENSE_TYPES = [
    'Electricity',
    'Water',
    'Church Maintenance',
    'Cleaning',
    'Stationery',
    'Transportation',
    'Communication',
    'Catechetical Activities',
    'Liturgical Items',
    'Pastoral Activities',
    'Funeral Expenses',
    'Repairs',
    'Salaries / Allowances',
    'Welfare',
    'Events',
    'Bank Charges',
    'Other Expense',
];

/* ============================================================
   MEMBER SEARCH
   ------------------------------------------------------------
   Used by member_search.php (AJAX endpoint).
   - Empty query returns most recently registered members
   - Query returns matching by name or phone
   - LIMIT bound as int for safe MySQL usage
   ============================================================ */
function searchMembers(PDO $pdo, string $q, int $limit = 15): array {
    $q = trim($q);

    /* Clamp limit to a sane range */
    if ($limit < 1)  $limit = 15;
    if ($limit > 50) $limit = 50;

    try {
        if ($q === '') {
            $stmt = $pdo->prepare("SELECT id, full_name, phone, day_born
                                   FROM members
                                   ORDER BY id DESC
                                   LIMIT ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            return is_array($rows) ? $rows : [];
        }

        $stmt = $pdo->prepare("SELECT id, full_name, phone, day_born
                               FROM members
                               WHERE full_name LIKE ? OR phone LIKE ?
                               ORDER BY full_name
                               LIMIT ?");
        $like = '%' . $q . '%';
        $stmt->bindValue(1, $like,  PDO::PARAM_STR);
        $stmt->bindValue(2, $like,  PDO::PARAM_STR);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}

function getMember(PDO $pdo, ?int $id): ?array {
    if (!$id) return null;
    try {
        $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/* ============================================================
   DAY BORN GROUPS
   ============================================================ */
function getDayBornGroups(PDO $pdo): array {
    try {
        $rows = $pdo->query("SELECT * FROM day_born_groups ORDER BY sort_order")->fetchAll();
        return is_array($rows) ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}

function getGroupNameForDay(PDO $pdo, ?string $day): string {
    if (!$day) return '—';
    try {
        $stmt = $pdo->prepare("SELECT group_name FROM day_born_groups WHERE day_name = ?");
        $stmt->execute([$day]);
        $row = $stmt->fetch();
        return $row['group_name'] ?? $day;
    } catch (PDOException $e) {
        return $day;
    }
}

/* ============================================================
   MEMBER PHOTO — ABSOLUTE PATH (works from any folder)
   ============================================================
   Returns the URL to the member's photo, or null if none.
   The URL is absolute (starts with /chruch_system/...) so it
   works correctly from root, /member/, /secretary/, /finance/,
   /admin/, and any future subfolder.
   ============================================================ */
function memberPhoto(?array $member): ?string {
    if (!$member || empty($member['photo'])) return null;

    /* File path on disk */
    $diskPath = __DIR__ . '/../uploads/members/' . $member['photo'];
    if (!is_file($diskPath)) return null;

    /* Build an absolute web path from the document root */
    $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $real    = str_replace('\\', '/', realpath($diskPath));

    if (!$docRoot || !$real) return null;

    /* Strip the document root to get the web-relative path */
    $rel = substr($real, strlen($docRoot));

    return ($rel !== false && $rel !== '') ? $rel : null;
}

/* ============================================================
   MEMBER AVATAR — Photo or Initials
   ============================================================ */
function memberAvatar(?array $member, int $size = 44): string {
    $name    = $member['full_name'] ?? '?';
    $initial = strtoupper(substr($name, 0, 1));
    $photo   = memberPhoto($member);

    $style = "width:{$size}px;height:{$size}px;border-radius:50%;flex-shrink:0;";

    if ($photo) {
        return '<img src="' . e($photo) . '" alt="' . e($name) . '" '
             . 'style="' . $style . 'object-fit:cover;border:2px solid #fff;'
             . 'box-shadow:0 1px 3px rgba(0,0,0,0.1);">';
    }

    return '<div class="avatar" style="' . $style
         . 'background:#6b46c1;color:#fff;display:flex;align-items:center;'
         . 'justify-content:center;font-weight:700;font-size:' . ($size * 0.42) . 'px;">'
         . e($initial) . '</div>';
}

/* ============================================================
   DISPLAY DOB — handles full / year-only / approx-age / unknown
   ============================================================ */
function displayDob(?array $member): string {
    if (!$member) return '—';

    if (!empty($member['dob'])) {
        return 'date'('jS F Y', 'strtotime'($member['dob']));
    }
    if (!empty($member['birth_year'])) {
        return 'Year ' . $member['birth_year'] . ' (year only)';
    }
    if (!empty($member['approx_age'])) {
        $year = (int)'date'('Y') - (int)$member['approx_age'];
        return '~' . $year . ' (approx. ' . $member['approx_age'] . ' yrs)';
    }
    return 'Not known';
}

function hasFullDob(?array $member): bool {
    return !empty($member['dob']);
}

/* ============================================================
   ENSURE UPLOAD DIR EXISTS
   ============================================================ */
function ensureUploadDir(string $subdir = 'members'): string {
    $dir = __DIR__ . '/../uploads/' . trim($subdir, '/') . '/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}