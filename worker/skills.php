<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('worker');

$user = currentUser();
$db = getDB();
$user_id = (int)$user['id'];

// Get or auto-create worker profile record
$stmt = $db->prepare("SELECT id FROM workers WHERE user_id = ?");
$stmt->execute([$user_id]);
$worker = $stmt->fetch();

if (!$worker) {
    $stmt_ins = $db->prepare("INSERT INTO workers (user_id) VALUES (?)");
    $stmt_ins->execute([$user_id]);
    $worker_profile_id = (int)$db->lastInsertId();
} else {
    $worker_profile_id = (int)$worker['id'];
}

$errors = [];

// Handle Skill Addition & Removal BEFORE HTML Output (PRG Pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        set_flash_message("CSRF security check failed.", "danger");
        redirect('worker/skills.php');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $skill_id = (int)($_POST['skill_id'] ?? 0);
            $proficiency = sanitize($_POST['proficiency_level'] ?? 'intermediate');

            if ($skill_id <= 0) {
                set_flash_message("Please select a valid trade skill from the list.", "danger");
                redirect('worker/skills.php');
            } else {
                try {
                    // Check if already assigned
                    $stmt_c = $db->prepare("SELECT id FROM worker_skills WHERE worker_id = ? AND skill_id = ?");
                    $stmt_c->execute([$worker_profile_id, $skill_id]);
                    if ($stmt_c->fetch()) {
                        set_flash_message("You have already added this skill to your profile.", "warning");
                        redirect('worker/skills.php');
                    } else {
                        $stmt_ins = $db->prepare("INSERT INTO worker_skills (worker_id, skill_id, proficiency_level) VALUES (?, ?, ?)");
                        $stmt_ins->execute([$worker_profile_id, $skill_id, $proficiency]);

                        // Fetch skill name for activity log & flash message
                        $stmt_name = $db->prepare("SELECT name FROM skills WHERE id = ?");
                        $stmt_name->execute([$skill_id]);
                        $sname = $stmt_name->fetchColumn() ?: 'Trade Skill';

                        log_activity($user_id, 'Skill Added', "Added skill '{$sname}' with proficiency {$proficiency}", 'skill', $skill_id);

                        set_flash_message("Skill '{$sname}' added successfully to your inventory!", "success");
                        redirect('worker/skills.php');
                    }
                } catch (PDOException $e) {
                    error_log("Database error adding skill: " . $e->getMessage());
                    set_flash_message("Database error while adding skill.", "danger");
                    redirect('worker/skills.php');
                }
            }
        } elseif ($action === 'remove') {
            $worker_skill_id = (int)($_POST['worker_skill_id'] ?? 0);

            if ($worker_skill_id <= 0) {
                set_flash_message("Invalid skill record specified.", "danger");
                redirect('worker/skills.php');
            } else {
                try {
                    // Fetch skill name before deletion for activity logging
                    $stmt_info = $db->prepare("
                        SELECT s.name 
                        FROM worker_skills ws 
                        LEFT JOIN skills s ON ws.skill_id = s.id 
                        WHERE ws.id = ? AND ws.worker_id = ?
                    ");
                    $stmt_info->execute([$worker_skill_id, $worker_profile_id]);
                    $removed_name = $stmt_info->fetchColumn() ?: 'Trade Skill';

                    // Strict Ownership Validation before deleting
                    $stmt_del = $db->prepare("DELETE FROM worker_skills WHERE id = ? AND worker_id = ?");
                    $stmt_del->execute([$worker_skill_id, $worker_profile_id]);

                    if ($stmt_del->rowCount() > 0) {
                        log_activity($user_id, 'Skill Removed', "Removed skill '{$removed_name}' (ID #{$worker_skill_id})", 'skill', $worker_skill_id);
                        set_flash_message("Skill '{$removed_name}' removed from your profile.", "info");
                    } else {
                        set_flash_message("Access denied or skill record not found.", "danger");
                    }
                    redirect('worker/skills.php');
                } catch (PDOException $e) {
                    error_log("Error removing skill: " . $e->getMessage());
                    set_flash_message("Error removing skill.", "danger");
                    redirect('worker/skills.php');
                }
            }
        }
    }
}

// Fetch Current Worker Skills
$stmt_my_skills = $db->prepare("
    SELECT ws.id as worker_skill_id, ws.proficiency_level, ws.created_at,
           s.id as skill_id, 
           COALESCE(NULLIF(TRIM(s.name), ''), 'Skill information unavailable') as skill_name,
           COALESCE(NULLIF(TRIM(s.category), ''), 'Construction') as category
    FROM worker_skills ws
    LEFT JOIN skills s ON ws.skill_id = s.id
    WHERE ws.worker_id = ?
    ORDER BY s.name ASC, ws.id ASC
");
$stmt_my_skills->execute([$worker_profile_id]);
$my_skills = $stmt_my_skills->fetchAll(PDO::FETCH_ASSOC);

// Fetch All Available Skills in Database for dropdown
$stmt_all_skills = $db->query("SELECT * FROM skills ORDER BY category ASC, name ASC");
$all_skills = $stmt_all_skills->fetchAll(PDO::FETCH_ASSOC);

// Page Output Headers
$page_title = "My Trade Skills - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
$flash = get_flash_message();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-screwdriver-wrench text-warning me-2"></i>Trade Skills & Specializations
                </h1>
                <p class="text-muted small mb-0">Manage verified trade skills and proficiency levels to improve job matching.</p>
            </div>
            <div>
                <button class="btn btn-amber btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addSkillModal">
                    <i class="fa-solid fa-plus me-1"></i> Add Skill
                </button>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-circle-info me-2"></i> <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Active Worker Skills Card -->
        <div class="bc-card p-4">
            <h2 class="h5 text-white fw-bold mb-3">
                <i class="fa-solid fa-list-check text-info me-2"></i>My Skill Inventory
            </h2>

            <?php if (empty($my_skills)): ?>
                <div class="bc-empty-state py-5 text-center">
                    <i class="fa-solid fa-screwdriver-wrench fs-1 text-warning mb-3"></i>
                    <h3 class="h5 text-white fw-bold">No Trade Skills Added Yet</h3>
                    <p class="text-muted small mb-3">
                        Add trade skills to your profile so contractors can discover your expertise for project assignments.
                    </p>
                    <button class="btn btn-amber btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addSkillModal">
                        <i class="fa-solid fa-plus me-1"></i> Add Your First Skill
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Skill Name</th>
                                <th>Category</th>
                                <th>Proficiency Level</th>
                                <th>Added Date</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_skills as $sk): 
                                $display_name = !empty($sk['skill_name']) ? $sk['skill_name'] : 'Skill information unavailable';
                                $display_cat  = !empty($sk['category']) ? $sk['category'] : 'Construction';
                                $display_prof = !empty($sk['proficiency_level']) ? $sk['proficiency_level'] : 'intermediate';
                            ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fa-solid fa-check-circle text-info me-2"></i>
                                            <span class="fw-bold text-white fs-6">
                                                <?= e($display_name) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-dark border border-secondary text-info"><?= e($display_cat) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning text-dark text-capitalize font-monospace"><?= e($display_prof) ?></span>
                                    </td>
                                    <td class="text-muted extra-small">
                                        <?= format_date($sk['created_at']) ?>
                                    </td>
                                    <td class="text-end">
                                        <form action="<?= BASE_URL ?>/worker/skills.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to remove this skill from your profile?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="worker_skill_id" value="<?= $sk['worker_skill_id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm extra-small">
                                                <i class="fa-solid fa-trash me-1"></i> Remove
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Add Skill Modal -->
<div class="modal fade" id="addSkillModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-screwdriver-wrench me-2 text-warning"></i>Add Trade Skill</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>/worker/skills.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Select Skill from Dictionary <span class="text-danger">*</span></label>
                        <select name="skill_id" class="form-select" required>
                            <option value="">-- Choose a Trade Skill --</option>
                            <?php foreach ($all_skills as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= e($s['name']) ?> (<?= e($s['category']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Proficiency Level</label>
                        <select name="proficiency_level" class="form-select">
                            <option value="beginner">Beginner (Apprentice)</option>
                            <option value="intermediate" selected>Intermediate (Journeyman)</option>
                            <option value="advanced">Advanced (Master Craftsman)</option>
                            <option value="expert">Expert (Site Lead)</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-amber btn-sm fw-bold"><i class="fa-solid fa-plus me-1"></i> Add Skill</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
