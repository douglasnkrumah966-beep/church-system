<?php
require_once __DIR__ . '/_layout.php';

/* ============================================================
   BULK MESSAGE — Secretary
   ============================================================ */

$audience = get('audience');
$msg      = get('msg') ?: "Dear member, this is a reminder from "
                        . ($settings['church_name'] ?? 'our station') . ".";

$members = [];
$audienceLabel = '';

/* ---------- Load recipients based on audience ---------- */
if ($audience !== '') {

    /* WhatsApp group? (e.g. wa_3) */
    if (preg_match('/^wa_(\d+)$/', $audience, $match)) {
        $groupId = (int)$match[1];

        try {
            $stmt = $pdo->prepare("SELECT name FROM wa_groups WHERE id = ?");
            $stmt->execute([$groupId]);
            $group = $stmt->fetch();

            if (is_array($group)) {
                $audienceLabel = '💬 Group: ' . $group['name'];

                $stmt = $pdo->prepare("
                    SELECT m.id, m.full_name, m.phone, m.photo, m.day_born
                    FROM members m
                    JOIN wa_group_members gm ON gm.member_id = m.id
                    WHERE gm.group_id = ?
                      AND m.phone IS NOT NULL AND m.phone <> ''
                    ORDER BY m.full_name
                ");
                $stmt->execute([$groupId]);
                $rows = $stmt->fetchAll();
                $members = is_array($rows) ? $rows : [];
            }
        } catch (PDOException $e) {
            $members = [];
        }
    }
    /* Normal audiences */
    else {
        $where  = "";
        $params = [];

        switch ($audience) {
            case 'today':
                $where = "AND dob IS NOT NULL AND DATE_FORMAT(dob,'%m-%d') = ?";
                $params[] ='date'('m-d');
                $audienceLabel = '🎂 Birthdays today';
                break;
            case 'sunday':    $where = "AND day_born = 'Sunday'";    $audienceLabel = 'Sunday Born';    break;
            case 'monday':    $where = "AND day_born = 'Monday'";    $audienceLabel = 'Monday Born';    break;
            case 'tuesday':   $where = "AND day_born = 'Tuesday'";   $audienceLabel = 'Tuesday Born';   break;
            case 'wednesday': $where = "AND day_born = 'Wednesday'"; $audienceLabel = 'Wednesday Born'; break;
            case 'thursday':  $where = "AND day_born = 'Thursday'";  $audienceLabel = 'Thursday Born';  break;
            case 'friday':    $where = "AND day_born = 'Friday'";    $audienceLabel = 'Friday Born';    break;
            case 'saturday':  $where = "AND day_born = 'Saturday'";  $audienceLabel = 'Saturday Born';  break;
            case 'all':
            default:
                $audienceLabel = 'All members with phone';
                break;
        }

        try {
            $stmt = $pdo->prepare("SELECT id, full_name, phone, photo, day_born
                FROM members
                WHERE phone IS NOT NULL AND phone <> '' $where
                ORDER BY full_name");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            $members = is_array($rows) ? $rows : [];
        } catch (PDOException $e) {
            $members = [];
        }
    }
}

/* ---------- Normalize Ghana phone ---------- */
function ghPhone2($p) {
    $p = preg_replace('/[^0-9]/', '', (string)($p ?? ''));
    if ($p === '') return '';
    if (strpos($p, '0') === 0)       return '233' . substr($p, 1);
    if (strpos($p, '233') !== 0)     return '233' . $p;
    return $p;
}

/* ---------- Message templates ---------- */
$templates = [
    'Mass Reminder'        => "Dear member, this is a reminder that Mass will be celebrated tomorrow. All are welcome. 🙏",
    'Sunday Collection'    => "Beloved in Christ, please remember our Sunday collection this week. Your generosity supports our station. 🙏",
    'Birthday Greeting'    => "Happy Birthday! 🎂 Wishing you God's abundant blessings on your special day. From all of us at the station. 🎉",
    'Meeting Notice'       => "Dear member, there will be a station meeting this week. Please come with your ideas. 🙏",
    'Funeral Announcement' => "Dear member, with heavy hearts we announce the passing of our beloved. Funeral arrangements will be shared shortly. Please keep the family in prayer. 🕊",
];

/* ---------- WhatsApp groups ---------- */
$waGroups = [];
try {
    $waGroups = $pdo->query("SELECT id, name FROM wa_groups ORDER BY name")->fetchAll();
    if (!is_array($waGroups)) $waGroups = [];
} catch (PDOException $e) {
    $waGroups = [];
}
?>

<!-- ============================================================
     MESSAGE COMPOSER
     ============================================================ -->
<div class="card no-print">
  <h3>✉️ Bulk Message Composer</h3>

  <form method="get" id="bulkForm">
    <div class="grid grid-2">
      <div>
        <label>Audience</label>
        <select name="audience" required>
          <option value="">— Select audience —</option>

          <optgroup label="Everyone">
            <option value="all" <?= $audience==='all'?'selected':'' ?>>All members with phone</option>
            <option value="today" <?= $audience==='today'?'selected':'' ?>>Birthdays today</option>
          </optgroup>

          <optgroup label="Day Born Groups">
            <option value="sunday"    <?= $audience==='sunday'?'selected':'' ?>>Sunday Born</option>
            <option value="monday"    <?= $audience==='monday'?'selected':'' ?>>Monday Born</option>
            <option value="tuesday"   <?= $audience==='tuesday'?'selected':'' ?>>Tuesday Born</option>
            <option value="wednesday" <?= $audience==='wednesday'?'selected':'' ?>>Wednesday Born</option>
            <option value="thursday"  <?= $audience==='thursday'?'selected':'' ?>>Thursday Born</option>
            <option value="friday"    <?= $audience==='friday'?'selected':'' ?>>Friday Born</option>
            <option value="saturday"  <?= $audience==='saturday'?'selected':'' ?>>Saturday Born</option>
          </optgroup>

          <?php if ($waGroups): ?>
            <optgroup label="💬 WhatsApp Groups">
              <?php foreach ($waGroups as $g): ?>
                <option value="wa_<?= (int)$g['id'] ?>"
                        <?= $audience==='wa_'.$g['id']?'selected':'' ?>>
                  💬 <?= e($g['name']) ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php else: ?>
            <optgroup label="💬 WhatsApp Groups">
              <option value="" disabled>(No groups yet — create one in WhatsApp Groups)</option>
            </optgroup>
          <?php endif; ?>
        </select>
      </div>

      <div>
        <label>Quick Template</label>
        <select id="templateSelect" onchange="applyTemplate(this)">
          <option value="">— Pick a template —</option>
          <?php foreach ($templates as $name => $text): ?>
            <option value="<?= e($text) ?>"><?= e($name) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="grid-column:1/-1;">
        <label>Message</label>
        <textarea name="msg" id="msgField" rows="4" required
                  style="width:100%;padding:12px;border:2px solid #e2e8f0;
                         border-radius:10px;font-family:inherit;font-size:15px;"><?= e($msg) ?></textarea>
      </div>
    </div>

    <button class="btn-primary" type="submit">📥 Load Recipients</button>
  </form>
</div>

<!-- ============================================================
     RECIPIENTS
     ============================================================ -->
<?php if ($audience && $members): ?>
  <div class="card">
    <h3>
      📋 Recipients
      <span class="badge" style="margin-left:8px;"><?= count($members) ?></span>
    </h3>
    <p style="color:#718096;font-size:13px;margin-bottom:12px;">
      <?= e($audienceLabel) ?>
    </p>

    <div class="grid grid-2" style="margin-bottom:14px;">
      <a class="btn-sm btn-success" style="text-align:center;padding:12px;"
         href="https://wa.me/?text=<?= rawurlencode($msg) ?>"
         target="_blank" rel="noopener">
        💬 WhatsApp share
      </a>
      <button class="btn-sm btn-primary" style="padding:12px;"
              onclick="copyPhones()">
        📱 Copy phone list
      </button>
    </div>

    <textarea id="phoneList" readonly rows="5"
      style="width:100%;font-family:monospace;font-size:13px;padding:10px;
             border:2px solid #e2e8f0;border-radius:8px;"><?php
      $list = [];
      foreach ($members as $m) {
          if (!is_array($m)) continue;
          $p = ghPhone2($m['phone'] ?? '');
          if ($p !== '') $list[] = $p;
      }
      echo e(implode(', ', $list));
    ?></textarea>

    <div style="margin-top:6px;font-size:12px;color:#718096;">
      Copy into MTN / Vodafone / AirtelTigo bulk SMS, or paste into WhatsApp Broadcast.
    </div>

    <div style="margin-top:16px;">
      <?php foreach ($members as $m):
        if (!is_array($m)) continue;
        $phone = ghPhone2($m['phone'] ?? '');
        $personalMsg = "Dear " . ($m['full_name'] ?? '') . ",\n\n" . $msg;
        $waUrl = "https://wa.me/" . $phone . "?text=" . rawurlencode($personalMsg);
      ?>
        <div class="member-card">
          <?= memberAvatar($m, 40) ?>
          <div class="member-info">
            <div class="name"><?= e($m['full_name'] ?? '') ?></div>
            <div class="meta">
              <?= e($m['phone'] ?? '') ?>
              <?= !empty($m['day_born']) ? ' · ' . e($m['day_born']) . ' Born' : '' ?>
            </div>
          </div>
          <?php if ($phone !== ''): ?>
            <a class="btn-sm btn-success" style="padding:6px 12px;font-size:13px;"
               target="_blank" rel="noopener"
               href="<?= e($waUrl) ?>" title="Send WhatsApp">
              💬
            </a>
            <a class="btn-sm btn-ghost" style="padding:6px 10px;font-size:13px;"
               href="sms:<?= e($m['phone']) ?>?body=<?= rawurlencode($msg) ?>"
               title="Send SMS">
              📱
            </a>
          <?php else: ?>
            <span style="font-size:11px;color:#a0aec0;">no phone</span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <script>
  function copyPhones() {
    const t = document.getElementById('phoneList');
    t.select();
    try {
      document.execCommand('copy');
      alert('✅ Phone numbers copied!');
    } catch (e) {
      alert('Please copy manually.');
    }
  }
  </script>

<?php elseif ($audience): ?>
  <div class="card">
    <div class="empty">
      No recipients found for <strong><?= e($audienceLabel ?: $audience) ?></strong>.
      <?php if (preg_match('/^wa_/', $audience)): ?>
        <div style="margin-top:8px;font-size:13px;color:#718096;">
          Tip: open the group in <a href="wa_groups.php">WhatsApp Groups</a>
          and click <strong>+ Track</strong> next to members you want to include.
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<!-- ============================================================
     INLINE JS — templates
     ============================================================ -->
<script>
function applyTemplate(sel) {
  const val = sel.value;
  if (!val) return;
  document.getElementById('msgField').value = val;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>