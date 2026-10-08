<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('contractor');

$page_title = "Generate Attendance QR - Contractor - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$contractor_id = (int)$user['id'];

// Expire old sessions automatically
expire_old_attendance_sessions();

$error = '';
$flash = get_flash_message();

// Fetch contractor projects
$p_stmt = $db->prepare("SELECT id, title, location FROM projects WHERE contractor_id = ? ORDER BY id DESC");
$p_stmt->execute([$contractor_id]);
$projects = $p_stmt->fetchAll();

$selected_project_id = (int)($_GET['project_id'] ?? $_POST['project_id'] ?? 0);
if ($selected_project_id <= 0 && !empty($projects)) {
    $selected_project_id = $projects[0]['id'];
}

// Handle Starting or Closing Attendance Sessions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security validation failed (CSRF token invalid).';
    } else {
        $action = sanitize($_POST['action'] ?? '');
        $project_id = (int)($_POST['project_id'] ?? 0);

        // Verify contractor owns project
        $pv_stmt = $db->prepare("SELECT id, title FROM projects WHERE id = ? AND contractor_id = ?");
        $pv_stmt->execute([$project_id, $contractor_id]);
        $target_project = $pv_stmt->fetch();

        if (!$target_project) {
            $error = 'Access Denied: Selected project does not belong to your contractor account.';
        } else {
            if ($action === 'start') {
                // Close any existing active session for this project
                $close_old = $db->prepare("UPDATE attendance_sessions SET status = 'closed' WHERE project_id = ? AND status = 'active'");
                $close_old->execute([$project_id]);

                // Generate cryptographically secure 64-char hex token
                $token = bin2hex(random_bytes(32));
                $minutes = QR_EXPIRATION_MINUTES;

                $ins_stmt = $db->prepare("
                    INSERT INTO attendance_sessions (project_id, created_by, token, expires_at, status, created_at)
                    VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), 'active', NOW())
                ");
                $ins_stmt->execute([$project_id, $contractor_id, $token, $minutes]);
                $session_id = $db->lastInsertId();

                log_activity($contractor_id, 'Attendance Session Created', "Generated QR session #{$session_id} for project '{$target_project['title']}'", 'attendance_session', $session_id);
                set_flash_message("Attendance QR session generated for '{$target_project['title']}'. Valid for {$minutes} minutes.", 'success');
                redirect("contractor/generate-attendance.php?project_id={$project_id}");

            } elseif ($action === 'close') {
                $session_id = (int)($_POST['session_id'] ?? 0);
                $close_stmt = $db->prepare("UPDATE attendance_sessions SET status = 'closed' WHERE id = ? AND project_id = ?");
                $close_stmt->execute([$session_id, $project_id]);

                log_activity($contractor_id, 'Attendance Session Closed', "Closed attendance QR session #{$session_id}", 'attendance_session', $session_id);
                set_flash_message("Attendance session closed.", 'info');
                redirect("contractor/generate-attendance.php?project_id={$project_id}");
            }
        }
    }
}

// Fetch current active session for selected project
$active_session = null;
$project_info = null;

if ($selected_project_id > 0) {
    $pj_stmt = $db->prepare("SELECT id, title, location FROM projects WHERE id = ? AND contractor_id = ?");
    $pj_stmt->execute([$selected_project_id, $contractor_id]);
    $project_info = $pj_stmt->fetch();

    if ($project_info) {
        $sess_stmt = $db->prepare("
            SELECT *, TIMESTAMPDIFF(SECOND, NOW(), expires_at) as seconds_remaining
            FROM attendance_sessions
            WHERE project_id = ? AND status = 'active' AND expires_at > NOW()
            ORDER BY id DESC LIMIT 1
        ");
        $sess_stmt->execute([$selected_project_id]);
        $active_session = $sess_stmt->fetch();
    }
}
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-qrcode text-warning me-2"></i>Generate Site Attendance QR
                </h1>
                <p class="text-muted small mb-0">Start a secure, time-limited attendance QR session for site workers to check in using their mobile devices.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/contractor/attendance.php" class="btn btn-outline-light btn-sm fw-bold">
                    <i class="fa-solid fa-clipboard-user me-1"></i> Attendance Dashboard
                </a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show py-2 px-3 small mb-3">
                <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 px-3 small rounded-3 mb-4">
                <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= sanitize($error) ?>
            </div>
        <?php endif; ?>

        <!-- Project Selector Card -->
        <div class="bc-card p-4 mb-4">
            <form action="<?= BASE_URL ?>/contractor/generate-attendance.php" method="GET" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label text-light fw-bold small mb-1">Select Construction Site Project</label>
                    <select name="project_id" class="form-select bg-dark border-secondary text-light" onchange="this.form.submit()">
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $selected_project_id == $p['id'] ? 'selected' : '' ?>>
                                <?= e($p['title']) ?> (<?= e($p['location']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <form action="<?= BASE_URL ?>/contractor/generate-attendance.php" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="start">
                        <input type="hidden" name="project_id" value="<?= $selected_project_id ?>">
                        <button type="submit" class="btn btn-amber fw-bold w-100 py-2">
                            <i class="fa-solid fa-play me-1"></i> Start New QR Session (5 Min)
                        </button>
                    </form>
                </div>
            </form>
        </div>

        <!-- Active QR Display Card -->
        <?php if ($project_info && $active_session): ?>
            <?php
            $scan_url = BASE_URL . "/worker/scan-attendance.php?token=" . urlencode($active_session['token']);
            $remaining_secs = max(0, (int)$active_session['seconds_remaining']);
            ?>
            <div class="bc-card p-5 text-center border-warning mb-4">
                <div class="badge bg-success py-2 px-3 fs-6 mb-3">
                    <i class="fa-solid fa-signal-stream me-1"></i> ATTENDANCE SESSION ACTIVE
                </div>

                <h2 class="h3 text-white fw-bold mb-1"><?= e($project_info['title']) ?></h2>
                <p class="text-muted small mb-4"><i class="fa-solid fa-location-dot text-danger me-1"></i><?= e($project_info['location']) ?></p>

                <!-- QR Code Container -->
                <div class="d-inline-block p-4 bg-white rounded-4 shadow-lg mb-4">
                    <div id="qrcode"></div>
                </div>

                <!-- Live Countdown Timer -->
                <div class="mb-4">
                    <div class="text-muted extra-small uppercase mb-1">Session Expires In</div>
                    <div id="countdown" class="display-5 font-monospace text-warning fw-bold">
                        <?= sprintf('%02d:%02d', floor($remaining_secs / 60), $remaining_secs % 60) ?>
                    </div>
                </div>

                <div class="p-3 bg-dark rounded-3 border border-secondary d-inline-block text-start mb-4" style="max-width: 550px;">
                    <div class="extra-small text-muted mb-1 fw-bold">Direct Scan / Check-In URL:</div>
                    <a href="<?= e($scan_url) ?>" target="_blank" class="text-info font-monospace extra-small text-break">
                        <?= e($scan_url) ?>
                    </a>
                </div>

                <div>
                    <form action="<?= BASE_URL ?>/contractor/generate-attendance.php" method="POST" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="close">
                        <input type="hidden" name="project_id" value="<?= $selected_project_id ?>">
                        <input type="hidden" name="session_id" value="<?= $active_session['id'] ?>">
                        <button type="submit" class="btn btn-outline-danger fw-bold btn-sm">
                            <i class="fa-solid fa-stop me-1"></i> Stop Session
                        </button>
                    </form>
                </div>
            </div>

            <!-- Client-side QRCode Generator Script -->
            <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    new QRCode(document.getElementById("qrcode"), {
                        text: "<?= addslashes($scan_url) ?>",
                        width: 220,
                        height: 220,
                        colorDark : "#1e293b",
                        colorLight : "#ffffff",
                        correctLevel : QRCode.CorrectLevel.H
                    });

                    // Live countdown timer
                    let timeLeft = <?= $remaining_secs ?>;
                    const timerElem = document.getElementById('countdown');

                    const interval = setInterval(function() {
                        if (timeLeft <= 0) {
                            clearInterval(interval);
                            timerElem.innerHTML = "<span class='text-danger'>EXPIRED</span>";
                            setTimeout(function() { location.reload(); }, 2000);
                        } else {
                            timeLeft--;
                            let m = Math.floor(timeLeft / 60);
                            let s = timeLeft % 60;
                            timerElem.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
                        }
                    }, 1000);
                });
            </script>
        <?php else: ?>
            <div class="bc-card p-5 text-center my-4">
                <i class="fa-solid fa-qrcode fs-1 text-muted mb-3"></i>
                <h3 class="h5 text-white fw-bold">No Active Attendance Session</h3>
                <p class="text-muted small mb-4">Click below to generate a new 5-minute QR session for <?= e($project_info['title'] ?? 'the selected project') ?>.</p>
                <form action="<?= BASE_URL ?>/contractor/generate-attendance.php" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="start">
                    <input type="hidden" name="project_id" value="<?= $selected_project_id ?>">
                    <button type="submit" class="btn btn-amber fw-bold px-4 py-2">
                        <i class="fa-solid fa-play me-1"></i> Generate Attendance QR Code
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
