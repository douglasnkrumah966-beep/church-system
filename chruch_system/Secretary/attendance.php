<?php
/* ============================================================
   ATTENDANCE — Secretary
   ------------------------------------------------------------
   All POST/GET handling and redirects MUST happen BEFORE
   including _layout.php (which prints HTML).
   ============================================================ */

require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin','secretary']);

$u = currentUser();

/* ============================================================
   ENSURE TABLES EXIST
   ============================================================ */
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        session_date DATE NOT NULL,
        category VARCHAR(60) DEFAULT 'Catechism',
        note VARCHAR(255) DEFAULT NULL,
        created_by VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        member_id INT NOT NULL,
        status ENUM('present','absent','excused') DEFAULT 'present',
        FOREIGN KEY (session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE,
        FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
        UNIQUE KEY uniq_session_member (session_id, member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) { /* already exist */ }

/* ============================================================
   CREATE SESSION
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_session'])) {
    $title = trim(post('title'));
    if ($title === '') {
        flash('❌ Please enter a session title');
        redirect('attendance.php');
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO attendance_sessions
            (title, session_date, category, note, created_by)
            VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $title,
            post('session_date') ?: 'date'('Y-m-d'),
            post('category') ?: 'Catechism',
            post('note'),
            $u['name'] ?? 'Secretary',
        ]);
        flash('✅ Session created');
        redirect('attendance.php?id=' . (int)$pdo->lastInsertId());
    } catch (PDOException $e) {
        flash('❌ Could not create session');
        redirect('attendance.php');
    }
}

/* ============================================================
   SAVE ATTENDANCE
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $sid = (int)post('session_id');
    if ($sid <= 0) {
        flash('❌ Invalid session');
        redirect('attendance.php');
    }

    $allMembers = [];
    try {
        $rows = $pdo->query("SELECT id FROM members")->fetchAll();
        $allMembers = is_array($rows) ? $rows : [];
    } catch (PDOException $e) {
        $allMembers = [];
    }

    /* Clear existing records */
    $pdo->prepare("DELETE FROM attendance_records WHERE session_id = ?")->execute([$sid]);

    $present = (isset($_POST['present']) && is_array($_POST['present'])) ? $_POST['present'] : [];
    $excused = (isset($_POST['excused']) && is_array($_POST['excused'])) ? $_POST['excused'] : [];

    $ins = $pdo->prepare("INSERT INTO attendance_records
        (session_id, member_id, status) VALUES (?, ?, ?)");

    foreach ($allMembers as $row) {
        if (!is_array($row) || empty($row['id'])) continue;
        $mid = (int)$row['id'];
        $status = 'absent';
        if (isset($present[$mid]))     $status = 'present';
        elseif (isset($excused[$mid])) $status = 'excused';
        $ins->execute([$sid, $mid, $status]);
    }

    flash('✅ Attendance saved');
    redirect('attendance.php?id=' . $sid);
}

/* ============================================================
   NOW include layout (HTML output starts here)
   ============================================================ */
require_once __DIR__ . '/_layout.php';

/* ============================================================
   LOAD DATA
   ============================================================ */
$sessions = [];
try {
    $rows = $pdo->query("SELECT * FROM attendance_sessions
                         ORDER BY session_date DESC, id DESC")->fetchAll();
    $sessions = is_array($rows) ? $rows : [];
} catch (PDOException $e) {
    $sessions = [];
}

$currentId = (int)get('id');
$current   = null;
$marked    = [];

if ($currentId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM attendance_sessions WHERE id = ?");
        $stmt->execute([$currentId]);
        $row = $stmt->fetch();

        if (is_array($row) && !empty($row['id']) && !empty($row['title'])) {
            $current = $row;
        }
    } catch (PDOException $e) {
        $current = null;
    }

    if (is_array($current)) {
        try {
            $stmt = $pdo->prepare("SELECT member_id, status FROM attendance_records WHERE session_id = ?");
            $stmt->execute([$currentId]);
            foreach ($stmt->fetchAll() as $r) {
                if (is_array($r)) $marked[(int)$r['member_id']] = (string)$r['status'];
            }
        } catch (PDOException $e) {
            $marked = [];
        }
    }
}

$members = [];
try {
    $rows = $pdo->query("SELECT id, full_name, photo, day_born
                         FROM members ORDER BY full_name")->fetchAll();
    $members = is_array($rows) ? $rows : [];
} catch (PDOException $e) {
    $members = [];
}
?>

<style>
.sec-att-hero {
  background: linear-gradient(135deg, #2c5282 0%, #3182ce 100%);
  color: #fff;
  border-radius: 16px;
  padding: 22px;
  margin-bottom: 18px;
  box-shadow: 0 8px 30px rgba(44,82,130,0.25);
}
.sec-att-hero h1 { font-size: 20px; margin: 0 0 4px; font-weight: 800; }
.sec-att-hero p  { font-size: 13px; opacity: 0.9; margin: 0; }

.sec-att-section {
  background: #fff;
  border-radius: 14px;
  padding: 20px;
  margin-bottom: 16px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  border: 1px solid #edf2f7;
}
.sec-att-section h3 {
  font-size: 15px;
  color: #2c5282;
  margin: 0 0 4px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 8px;
}
.sec-att-section .subtitle {
  font-size: 12px;
  color: #a0aec0;
  margin: 0 0 16px;
  line-height: 1.5;
}

.att-mark-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  background: #f7fafc;
  border-radius: 10px;
  margin-bottom: 8px;
  border: 1px solid #edf2f7;
  transition: all 0.15s ease;
}
.att-mark-row:hover { background: #fff; border-color: #cbd5e0; }
.att-mark-row.present { background: #f0fff4; border-color: #9ae6b4; }
.att-mark-row.excused { background: #fffaf0; border-color: #fbd38d; }

.att-name { flex: 1; min-width: 0; }
.att-name .name { font-weight: 700; font-size: 14px; color: #2d3748; }
.att-name .meta { font-size: 11px; color: #718096; margin-top: 2px; }

.att-toggle {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  border: 2px solid transparent;
  transition: all 0.15s ease;
  white-space: nowrap;
}
.att-toggle input[type="checkbox"] { display: none; }

.att-toggle.present-toggle {
  background: #f7fafc; color: #276749; border-color: #e2e8f0;
}
.att-toggle.present-toggle:hover { background: #f0fff4; border-color: #68d391; }
.att-toggle.present-toggle.active { background: #48bb78; color: #fff; border-color: #38a169; }

.att-toggle.excused-toggle {
  background: #f7fafc; color: #7b341e; border-color: #e2e8f0;
}
.att-toggle.excused-toggle:hover { background: #fffaf0; border-color: #f6ad55; }
.att-toggle.excused-toggle.active { background: #dd6b20; color: #fff; border-color: #c05621; }

.sec-att-session {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px;
  background: #f7fafc;
  border-radius: 12px;
  margin-bottom: 8px;
  border: 1px solid #edf2f7;
  transition: all 0.15s ease;
}
.sec-att-session:hover { background: #fff; border-color: #cbd5e0; }
.sec-att-session.active { background: #ebf8ff; border-color: #90cdf4; }

.sec-att-icon {
  width: 42px; height: 42px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; flex-shrink: 0;
  background: linear-gradient(135deg, #3182ce, #2c5282);
  color: #fff;
}
.sec-att-info { flex: 1; min-width: 0; }
.sec-att-name { font-size: 14px; font-weight: 700; color: #2d3748; }
.sec-att-meta { font-size: 11px; color: #718096; margin-top: 2px; }
.sec-att-stats { display: flex; gap: 4px; margin-top: 4px; flex-wrap: wrap; }
.sec-att-stat {
  font-size: 10px; padding: 2px 8px; border-radius: 999px; font-weight: 700;
}
.sec-att-stat.present { background: #c6f6d5; color: #22543d; }
.sec-att-stat.excused { background: #feebc8; color: #7b341e; }
.sec-att-stat.absent  { background: #fed7d7; color: #742a2a; }

.bulk-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; }
.bulk-btn {
  padding: 10px 16px; font-size: 13px; font-weight: 700;
  border-radius: 10px; border: none; cursor: pointer;
  transition: all 0.15s ease;
}
.bulk-btn.present { background: #48bb78; color: #fff; }
.bulk-btn.present:hover { background: #38a169; }
.bulk-btn.absent { background: #e2e8f0; color: #4a5568; }
.bulk-btn.absent:hover { background: #cbd5e0; }
</style>

<!-- ============================================================
     HERO
     ============================================================ -->
<div class="sec-att-hero">
  <h1>📋 Attendance</h1>
  <p>Track who attends catechism sessions, meetings, or any church gathering.</p>
</div>

<!-- ============================================================
     CREATE NEW SESSION
     ============================================================ -->
<div class="sec-att-section">
  <h3>➕ Create New Session</h3>
  <p class="subtitle">
    Give it a title, pick a date, and mark attendance for that gathering.
  </p>

  <form method="post">
    <div class="grid grid-2">
      <div>
        <label>Session Title *</label>
        <input name="title" required
               placeholder="e.g. Sunday Catechism — Class 3">
      </div>
      <div>
        <label>Date *</label>
        <input name="session_date" type="date"
               value="<?= 'date'('Y-m-d') ?>" required>
      </div>
      <div>
        <label>Category</label>
        <select name="category">
          <option>Catechism</option>
          <option>Mass</option>
          <option>Meeting</option>
          <option>Choir Practice</option>
          <option>Youth Group</option>
          <option>Women's Fellowship</option>
          <option>Men's Fellowship</option>
          <option>Other</option>
        </select>
      </div>
      <div>
        <label>Note (optional)</label>
        <input name="note" placeholder="e.g. Topic: The Sacraments">
      </div>
    </div>
    <button class="btn-primary" name="create_session" value="1"
            style="background:#3182ce;">
      📋 Create Session
    </button>
  </form>
</div>

<!-- ============================================================
     MARK ATTENDANCE
     ============================================================ -->
<?php if (is_array($current)): ?>

  <div class="sec-att-section" style="border-left:4px solid #3182ce;">
    <h3>
      ✅ Mark Attendance
      <span class="badge" style="margin-left:6px;background:#3182ce;color:#fff;">
        <?= e($current['title']) ?>
      </span>
    </h3>
    <p class="subtitle">
      <?= 'date'('jS F Y', 'strtotime'($current['session_date'])) ?>
      · <?= e($current['category'] ?? '') ?>
      <?php if (!empty($current['note'])): ?>
        · <?= e($current['note']) ?>
      <?php endif; ?>
    </p>

    <form method="post">
      <input type="hidden" name="session_id" value="<?= (int)$current['id'] ?>">

      <div class="bulk-actions">
        <button type="button" class="bulk-btn present" onclick="markAll('present')">
          ✓ Mark All Present
        </button>
        <button type="button" class="bulk-btn absent" onclick="markAll('absent')">
          ✕ Mark All Absent
        </button>
      </div>

      <?php if (!$members): ?>
        <div class="empty">No members registered yet.</div>
      <?php else: foreach ($members as $m):
        if (!is_array($m)) continue;
        $mid = (int)($m['id'] ?? 0);
        $status = $marked[$mid] ?? 'absent';
      ?>
        <div class="att-mark-row <?= $status === 'present' ? 'present' : ($status === 'excused' ? 'excused' : '') ?>"
             id="row-<?= $mid ?>">
          <?= memberAvatar($m, 40) ?>
          <div class="att-name">
            <div class="name"><?= e($m['full_name'] ?? '') ?></div>
            <?php if (!empty($m['day_born'])): ?>
              <div class="meta"><?= e($m['day_born']) ?> Born</div>
            <?php endif; ?>
          </div>

          <label class="att-toggle present-toggle <?= $status === 'present' ? 'active' : '' ?>"
                 id="ptoggle-<?= $mid ?>"
                 onclick="toggleStatus(<?= $mid ?>, 'present')">
            <input type="checkbox" name="present[<?= $mid ?>]" value="1"
                   class="att-present"
                   <?= $status === 'present' ? 'checked' : '' ?>>
            ✓ Present
          </label>

          <label class="att-toggle excused-toggle <?= $status === 'excused' ? 'active' : '' ?>"
                 id="etoggle-<?= $mid ?>"
                 onclick="toggleStatus(<?= $mid ?>, 'excused')">
            <input type="checkbox" name="excused[<?= $mid ?>]" value="1"
                   class="att-excused"
                   <?= $status === 'excused' ? 'checked' : '' ?>>
            🕊 Excused
          </label>
        </div>
      <?php endforeach; endif; ?>

      <div style="position:sticky;bottom:10px;margin-top:20px;text-align:right;">
        <button class="btn-primary" name="save_attendance" value="1"
                style="max-width:280px;background:#3182ce;">
          💾 Save Attendance
        </button>
      </div>
    </form>
  </div>

  <script>
  function toggleStatus(memberId, status) {
    const pBox = document.querySelector('input[name="present[' + memberId + ']"]');
    const eBox = document.querySelector('input[name="excused[' + memberId + ']"]');
    const pTog = document.getElementById('ptoggle-' + memberId);
    const eTog = document.getElementById('etoggle-' + memberId);
    const row  = document.getElementById('row-' + memberId);

    if (!pBox || !eBox) return;

    if (status === 'present') {
      pBox.checked = !pBox.checked;
      if (pBox.checked) eBox.checked = false;
    } else {
      eBox.checked = !eBox.checked;
      if (eBox.checked) pBox.checked = false;
    }

    pTog.classList.toggle('active', pBox.checked);
    eTog.classList.toggle('active', eBox.checked);
    row.classList.toggle('present', pBox.checked);
    row.classList.toggle('excused', eBox.checked);
  }

  function markAll(type) {
    document.querySelectorAll('.att-present').forEach(cb => {
      cb.checked = (type === 'present');
    });
    document.querySelectorAll('.att-excused').forEach(cb => {
      cb.checked = false;
    });
    document.querySelectorAll('.present-toggle').forEach(el => {
      el.classList.toggle('active', type === 'present');
    });
    document.querySelectorAll('.excused-toggle').forEach(el => {
      el.classList.remove('active');
    });
    document.querySelectorAll('.att-mark-row').forEach(row => {
      row.classList.toggle('present', type === 'present');
      row.classList.remove('excused');
    });
  }
  </script>

<?php endif; ?>

<!-- ============================================================
     PAST SESSIONS
     ============================================================ -->
<div class="sec-att-section">
  <h3>📚 Past Sessions (<?= count($sessions) ?>)</h3>
  <p class="subtitle">
    Tap any session to view or edit its attendance.
  </p>

  <?php if (!$sessions): ?>
    <div class="empty">
      No sessions yet. Create one above to start tracking attendance.
    </div>
  <?php else: foreach ($sessions as $s):
    if (!is_array($s)) continue;
    $sid = (int)($s['id'] ?? 0);

    $stats = ['present' => 0, 'absent' => 0, 'excused' => 0];
    try {
        $stmt = $pdo->prepare("SELECT
            SUM(status='present') AS p,
            SUM(status='absent')  AS a,
            SUM(status='excused') AS e
            FROM attendance_records WHERE session_id = ?");
        $stmt->execute([$sid]);
        $r = $stmt->fetch();
        if (is_array($r)) {
            $stats['present'] = (int)($r['p'] ?? 0);
            $stats['absent']  = (int)($r['a'] ?? 0);
            $stats['excused'] = (int)($r['e'] ?? 0);
        }
    } catch (PDOException $e) {}

    $isActive = ($current && $current['id'] === $sid);
  ?>
    <div class="sec-att-session <?= $isActive ? 'active' : '' ?>">
      <div class="sec-att-icon">
        <?= e(strtoupper(substr($s['category'] ?? '?', 0, 1))) ?>
      </div>
      <div class="sec-att-info">
        <div class="sec-att-name"><?= e($s['title'] ?? 'Untitled') ?></div>
        <div class="sec-att-meta">
          <?= !empty($s['session_date'])
              ? 'date'('jS M Y', 'strtotime'($s['session_date']))
              : '—' ?>
          · <?= e($s['category'] ?? '') ?>
        </div>
        <div class="sec-att-stats">
          <span class="sec-att-stat present">✓ <?= $stats['present'] ?> present</span>
          <span class="sec-att-stat excused">🕊 <?= $stats['excused'] ?> excused</span>
          <span class="sec-att-stat absent">✕ <?= $stats['absent'] ?> absent</span>
        </div>
      </div>
      <a class="btn-sm" style="padding:10px 16px;font-size:13px;background:#3182ce;color:#fff;"
         href="?id=<?= $sid ?>">
        <?= $isActive ? 'Current' : 'Open' ?>
      </a>
    </div>
  <?php endforeach; endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>