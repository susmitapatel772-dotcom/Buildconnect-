<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('worker');

$page_title = "Scan QR Check-In - Worker - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$worker_user_id = (int)$user['id'];

// Expire old sessions
expire_old_attendance_sessions();

$error = '';
$flash = get_flash_message();
$token = sanitize($_GET['token'] ?? $_POST['token'] ?? '');

// Process Check-In if token is provided
if (!empty($token)) {
    // 1. Fetch QR session from database
    $sess_stmt = $db->prepare("
        SELECT s.*, p.title as project_title, p.contractor_id
        FROM attendance_sessions s
        JOIN projects p ON s.project_id = p.id
        WHERE s.token = ?
    ");
    $sess_stmt->execute([$token]);
    $session = $sess_stmt->fetch();

    if (!$session) {
        $error = 'Invalid Attendance QR: The scanned QR code token is invalid.';
    } elseif ($session['status'] !== 'active' || strtotime($session['expires_at']) <= time()) {
        $error = 'Expired QR Session: The attendance QR session has expired or been closed by the contractor.';
    } else {
        $project_id = (int)$session['project_id'];

        // 2. Verify worker is an active member of this project
        $mem_stmt = $db->prepare("SELECT id FROM project_members WHERE project_id = ? AND user_id = ? AND status = 'active'");
        $mem_stmt->execute([$project_id, $worker_user_id]);
        if (!$mem_stmt->fetch()) {
            $error = "Access Denied: You are not an active member of project '{$session['project_title']}'.";
        } else {
            // 3. Check for existing check-in today
            $today = date('Y-m-d');
            $chk_stmt = $db->prepare("SELECT id, check_in_time FROM attendance WHERE project_id = ? AND worker_id = ? AND attendance_date = ?");
            $chk_stmt->execute([$project_id, $worker_user_id, $today]);
            $existing = $chk_stmt->fetch();

            if ($existing) {
                $error = "Duplicate Check-In: You are already checked in for project '{$session['project_title']}' today (" . format_datetime($existing['check_in_time'], 'h:i A') . ").";
            } else {
                // Determine status (Late if check-in after 09:30 AM)
                $cutoff_time = strtotime($today . ' 09:30:00');
                $now_time = time();
                $att_status = ($now_time > $cutoff_time) ? 'late' : 'present';

                try {
                    $ins_stmt = $db->prepare("
                        INSERT INTO attendance (
                            project_id, worker_id, attendance_date, check_in_time, 
                            hours_worked, verified_by_qr, qr_session_id, status, created_at, updated_at
                        ) VALUES (
                            ?, ?, ?, NOW(), 
                            0.00, 1, ?, ?, NOW(), NOW()
                        )
                    ");
                    $success = $ins_stmt->execute([
                        $project_id,
                        $worker_user_id,
                        $today,
                        $session['id'],
                        $att_status
                    ]);

                    if ($success) {
                        $att_id = $db->lastInsertId();

                        // Notify contractor
                        create_notification(
                            $session['contractor_id'],
                            'Worker Checked In',
                            "Worker '{$user['name']}' checked in for '{$session['project_title']}' at " . date('h:i A') . ".",
                            'info',
                            'contractor/attendance.php'
                        );

                        // Notify worker
                        create_notification(
                            $worker_user_id,
                            'Check-in Successful',
                            "You checked in for '{$session['project_title']}' at " . date('h:i A') . ".",
                            'success',
                            'worker/attendance.php'
                        );

                        log_activity($worker_user_id, 'Attendance Check-in', "Checked in for project #{$project_id} ('{$session['project_title']}') via QR", 'attendance', $att_id);
                        set_flash_message("Check-in Successful for '{$session['project_title']}'! Status: " . ucfirst($att_status) . ".", 'success');
                        redirect('worker/attendance.php');
                    } else {
                        $error = 'Check-in failed due to a database error.';
                    }
                } catch (PDOException $ex) {
                    if ($ex->getCode() == 23000) {
                        $error = "Duplicate Check-In: You are already checked in for this project today.";
                    } else {
                        $error = "Database Error: " . $ex->getMessage();
                    }
                }
            }
        }
    }
}
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-qrcode text-warning me-2"></i>Scan Site Attendance QR
                </h1>
                <p class="text-muted small mb-0">Use your device camera or enter an attendance QR session token to check in.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/worker/attendance.php" class="btn btn-outline-light btn-sm fw-bold">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Attendance
                </a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-3 px-4 rounded-3 mb-4 text-center">
                <i class="fa-solid fa-triangle-exclamation fs-3 d-block mb-2 text-danger"></i>
                <h4 class="h5 fw-bold text-white mb-1">Check-In Failed</h4>
                <p class="small mb-0"><?= sanitize($error) ?></p>
            </div>
        <?php endif; ?>

        <!-- Camera Scanner Card -->
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="bc-card p-4 text-center border-warning">
                    <h2 class="h5 text-white fw-bold mb-3"><i class="fa-solid fa-camera me-2 text-warning"></i>Live Camera QR Scanner</h2>

                    <div id="camera-container" class="p-3 bg-dark rounded-4 border border-secondary mb-4 position-relative mx-auto" style="max-width: 450px; min-height: 280px;">
                        <div id="reader" style="width: 100%;"></div>
                        <div id="camera-fallback-msg" class="py-5 text-muted small">
                            <i class="fa-solid fa-video-slash fs-1 text-muted d-block mb-2"></i>
                            Camera scanner initializes on button click.
                        </div>
                    </div>

                    <div class="d-flex justify-content-center gap-2 mb-4">
                        <button type="button" id="start-cam-btn" class="btn btn-amber fw-bold px-4">
                            <i class="fa-solid fa-camera me-1"></i> Start Camera Scanner
                        </button>
                    </div>

                    <!-- Token Submission Fallback -->
                    <div class="pt-4 border-top border-secondary">
                        <h3 class="h6 text-white fw-bold mb-2">Manual Token Check-In</h3>
                        <p class="text-muted extra-small mb-3">If camera scanning is unavailable, paste or enter the session QR token provided by your site contractor.</p>

                        <form action="<?= BASE_URL ?>/worker/scan-attendance.php" method="POST" class="d-flex gap-2 mx-auto" style="max-width: 500px;">
                            <?= csrf_field() ?>
                            <input type="text" name="token" value="<?= e($token) ?>" class="form-control bg-dark border-secondary text-light font-monospace text-center" placeholder="Paste BC-QR Token..." required>
                            <button type="submit" class="btn btn-warning fw-bold px-4">Check In</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- HTML5 QR Code Scanner Script -->
<script src="https://unpkg.com/html5-qrcode"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const startBtn = document.getElementById('start-cam-btn');
        const fallbackMsg = document.getElementById('camera-fallback-msg');
        let html5QrCode = null;

        startBtn.addEventListener('click', function() {
            fallbackMsg.style.display = 'none';
            if (!html5QrCode) {
                html5QrCode = new Html5Qrcode("reader");
            }

            Html5Qrcode.getCameras().then(devices => {
                if (devices && devices.length) {
                    const cameraId = devices[0].id;
                    html5QrCode.start(
                        { facingMode: "environment" },
                        { fps: 10, qrbox: { width: 250, height: 250 } },
                        (decodedText, decodedResult) => {
                            html5QrCode.stop();
                            if (decodedText.includes('token=')) {
                                window.location.href = decodedText;
                            } else {
                                window.location.href = "<?= BASE_URL ?>/worker/scan-attendance.php?token=" + encodeURIComponent(decodedText);
                            }
                        },
                        (errorMessage) => {}
                    ).catch(err => {
                        alert("Camera access denied or unavailable: " + err);
                    });
                } else {
                    alert("No camera devices detected.");
                }
            }).catch(err => {
                alert("Camera permission error: " + err);
            });
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
