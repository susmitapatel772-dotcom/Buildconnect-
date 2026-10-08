<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('worker');

$page_title = "Manage Availability - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$user_id = (int)$user['id'];
$flash = get_flash_message();

// Fetch worker record
$stmt = $db->prepare("SELECT * FROM workers WHERE user_id = ?");
$stmt->execute([$user_id]);
$worker = $stmt->fetch();

$errors = [];
$allowed_statuses = ['available', 'partially_available', 'not_available'];
$allowed_work_types = ['Full-Time', 'Part-Time', 'Contract', 'Shift-Based', 'Temporary'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = "CSRF security check failed.";
    }

    $availability_status = strtolower(sanitize($_POST['availability_status'] ?? 'available'));
    $preferred_work_type = sanitize($_POST['preferred_work_type'] ?? 'Full-Time');
    $preferred_location = sanitize($_POST['preferred_location'] ?? '');
    $available_from_date = !empty($_POST['available_from_date']) ? $_POST['available_from_date'] : null;

    if (!in_array($availability_status, $allowed_statuses, true)) {
        $errors[] = "Invalid availability status selected.";
    }
    if (!in_array($preferred_work_type, $allowed_work_types, true)) {
        $errors[] = "Invalid work type selected.";
    }

    if (empty($errors)) {
        try {
            if ($worker) {
                $stmt_u = $db->prepare("
                    UPDATE workers 
                    SET availability_status = ?, preferred_work_type = ?, preferred_location = ?, available_from_date = ?, updated_at = NOW()
                    WHERE user_id = ?
                ");
                $stmt_u->execute([$availability_status, $preferred_work_type, $preferred_location, $available_from_date, $user_id]);
            } else {
                $stmt_ins = $db->prepare("
                    INSERT INTO workers (user_id, availability_status, preferred_work_type, preferred_location, available_from_date)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt_ins->execute([$user_id, $availability_status, $preferred_work_type, $preferred_location, $available_from_date]);
            }

            log_activity($user_id, 'Availability Changed', "Updated availability status to '{$availability_status}'", 'user', $user_id);

            set_flash_message("Availability settings updated successfully!", "success");
            redirect('worker/availability.php');
        } catch (PDOException $e) {
            $errors[] = "Database update error: " . $e->getMessage();
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
                    <i class="fa-solid fa-calendar-check text-success me-2"></i>Manage Site Availability
                </h1>
                <p class="text-muted small mb-0">Set your work availability, preferred work arrangements, and location preferences.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/worker/profile.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Profile
                </a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-circle-info me-2"></i> <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="bc-card p-4 mx-auto" style="max-width: 750px;">
            <form action="<?= BASE_URL ?>/worker/availability.php" method="POST" data-loading="true">
                <?= csrf_field() ?>

                <h3 class="h5 text-white fw-bold mb-3 border-bottom border-secondary pb-2">
                    <i class="fa-solid fa-clock me-2 text-warning"></i>Work Availability Settings
                </h3>

                <div class="mb-4">
                    <label class="form-label text-muted small fw-semibold">Current Availability Status <span class="text-danger">*</span></label>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <input type="radio" class="btn-check" name="availability_status" id="statusAvailable" value="available" 
                                   <?= (($worker['availability_status'] ?? 'available') === 'available') ? 'checked' : '' ?>>
                            <label class="btn btn-outline-success w-100 p-3 text-start d-flex align-items-center gap-2" for="statusAvailable">
                                <i class="fa-solid fa-circle-check fs-5"></i>
                                <div>
                                    <div class="fw-bold">Available</div>
                                    <div class="extra-small opacity-75">Ready for immediate site work</div>
                                </div>
                            </label>
                        </div>

                        <div class="col-md-4">
                            <input type="radio" class="btn-check" name="availability_status" id="statusPartial" value="partially_available" 
                                   <?= (($worker['availability_status'] ?? '') === 'partially_available') ? 'checked' : '' ?>>
                            <label class="btn btn-outline-warning w-100 p-3 text-start d-flex align-items-center gap-2" for="statusPartial">
                                <i class="fa-solid fa-clock fs-5"></i>
                                <div>
                                    <div class="fw-bold">Partially Available</div>
                                    <div class="extra-small opacity-75">Available for specific shifts</div>
                                </div>
                            </label>
                        </div>

                        <div class="col-md-4">
                            <input type="radio" class="btn-check" name="availability_status" id="statusNotAvailable" value="not_available" 
                                   <?= (($worker['availability_status'] ?? '') === 'not_available') ? 'checked' : '' ?>>
                            <label class="btn btn-outline-danger w-100 p-3 text-start d-flex align-items-center gap-2" for="statusNotAvailable">
                                <i class="fa-solid fa-circle-xmark fs-5"></i>
                                <div>
                                    <div class="fw-bold">Not Available</div>
                                    <div class="extra-small opacity-75">Currently assigned / on leave</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">Preferred Work Arrangement</label>
                        <select name="preferred_work_type" class="form-select">
                            <?php foreach ($allowed_work_types as $wt): ?>
                                <option value="<?= e($wt) ?>" <?= (($worker['preferred_work_type'] ?? 'Full-Time') === $wt) ? 'selected' : '' ?>><?= e($wt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">Available From Date</label>
                        <input type="date" name="available_from_date" class="form-control" value="<?= e($worker['available_from_date'] ?? date('Y-m-d')) ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label text-muted small fw-semibold">Preferred Work Locations / Cities</label>
                        <input type="text" name="preferred_location" class="form-control" value="<?= e($worker['preferred_location'] ?? '') ?>" placeholder="e.g., Ahmedabad, Gandhinagar, SG Highway">
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 pt-3 border-top border-secondary">
                    <a href="<?= BASE_URL ?>/worker/profile.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
                    <button type="submit" class="btn btn-amber btn-sm fw-bold">
                        <i class="fa-solid fa-save me-1"></i> Save Availability Settings
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
