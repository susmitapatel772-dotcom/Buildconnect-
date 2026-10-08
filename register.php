<?php
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to role area
if (isLoggedIn()) {
    $user = currentUser();
    if ($user) {
        redirect(get_role_redirect_url($user['role']));
    }
}

$flash = get_flash_message();
$error = '';
$allowed_roles = ['contractor', 'worker', 'client'];
$prefilled_email = sanitize($_GET['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security validation failed (CSRF token invalid). Please try again.';
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = strtolower(trim($_POST['role'] ?? 'worker'));
        $location = sanitize($_POST['location'] ?? '');
        $company_name = sanitize($_POST['company_name'] ?? '');
        $trade_title = sanitize($_POST['trade_title'] ?? '');
        $experience_years = (int)($_POST['experience_years'] ?? 0);

        // Validation Checks
        if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($confirm_password) || empty($role)) {
            $error = 'Please fill out all required fields marked with an asterisk (*).';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif ($password !== $confirm_password) {
            $error = 'Password confirmation does not match.';
        } elseif (!in_array($role, $allowed_roles) || $role === 'admin') {
            $error = 'Invalid role selection. Administrator accounts cannot be created via self-registration.';
        } else {
            $db = getDB();

            // Prepared statement to verify unique email
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $error = 'This email address is already registered. Please sign in instead.';
            } else {
                // Securely hash password using password_hash()
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $ins = $db->prepare("INSERT INTO users (name, email, password, role, phone, status) VALUES (?, ?, ?, ?, ?, 'active')");
                if ($ins->execute([$name, $email, $hashed_password, $role, $phone])) {
                    $user_id = $db->lastInsertId();

                    // Create associated profile record
                    if ($role === 'worker') {
                        $trade = !empty($trade_title) ? $trade_title : 'General Construction Specialist';
                        $city = !empty($location) ? $location : 'Ahmedabad';
                        $db->prepare("INSERT INTO workers (user_id, trade_title, experience_years, city, verification_status) VALUES (?, ?, ?, ?, 'pending')")
                           ->execute([$user_id, $trade, $experience_years, $city]);
                    } elseif ($role === 'contractor') {
                        $comp = !empty($company_name) ? $company_name : ($name . ' Construction');
                        $city = !empty($location) ? $location : 'Ahmedabad';
                        $db->prepare("INSERT INTO contractors (user_id, company_name, city) VALUES (?, ?, ?)")
                           ->execute([$user_id, $comp, $city]);
                    } elseif ($role === 'client') {
                        $comp = !empty($company_name) ? $company_name : ($name . ' Developments');
                        $city = !empty($location) ? $location : 'Ahmedabad';
                        $db->prepare("INSERT INTO clients (user_id, company_name, city) VALUES (?, ?, ?)")
                           ->execute([$user_id, $comp, $city]);
                    }

                    set_flash_message('Registration successful! Please log in with your credentials.', 'success');
                    redirect('login.php');
                } else {
                    $error = 'Registration failed due to a system error. Please try again.';
                }
            }
        }
    }
}

$page_title = "Create Account - BuildConnect";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="bc-login-wrapper">
    <div class="bc-login-card bc-register-card">
        <div class="text-center mb-4">
            <div class="bc-login-header-icon">
                <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="BuildConnect Logo">
            </div>

            <h1 class="h3 text-white fw-bold brand-font mb-1">Create BuildConnect Account</h1>
            <p class="text-slate-400 small mb-0">Join the construction network and build a better tomorrow.</p>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show py-2.5 px-3 small border-0 shadow-sm mb-3" role="alert">
                <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check text-success' : 'fa-circle-info text-warning' ?> me-2"></i>
                <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2.5 px-3 small rounded-3 mb-3 border-0 bg-danger bg-opacity-25 text-danger-emphasis">
                <i class="fa-solid fa-triangle-exclamation me-1.5"></i> <?= sanitize($error) ?>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>/register.php" method="POST" data-loading="true" id="registerForm">
            <?= csrf_field() ?>

            <!-- Role Selector Buttons -->
            <div class="mb-4">
                <label class="form-label text-slate-300 small fw-semibold d-block mb-2 text-center">Select Account Role *</label>
                <div class="row g-2">
                    <div class="col-4">
                        <input type="radio" class="btn-check" name="role" id="role-worker" value="worker" <?= (!isset($_POST['role']) || $_POST['role'] === 'worker') ? 'checked' : '' ?> onchange="handleRoleChange('worker')">
                        <label class="btn bc-role-btn w-100 py-2.5 text-center" for="role-worker">
                            <i class="fa-solid fa-helmet-safety d-block fs-5 mb-1"></i>
                            <span class="small fw-bold">Worker</span>
                        </label>
                    </div>
                    <div class="col-4">
                        <input type="radio" class="btn-check" name="role" id="role-contractor" value="contractor" <?= (isset($_POST['role']) && $_POST['role'] === 'contractor') ? 'checked' : '' ?> onchange="handleRoleChange('contractor')">
                        <label class="btn bc-role-btn w-100 py-2.5 text-center" for="role-contractor">
                            <i class="fa-solid fa-building d-block fs-5 mb-1"></i>
                            <span class="small fw-bold">Contractor</span>
                        </label>
                    </div>
                    <div class="col-4">
                        <input type="radio" class="btn-check" name="role" id="role-client" value="client" <?= (isset($_POST['role']) && $_POST['role'] === 'client') ? 'checked' : '' ?> onchange="handleRoleChange('client')">
                        <label class="btn bc-role-btn w-100 py-2.5 text-center" for="role-client">
                            <i class="fa-solid fa-user-tie d-block fs-5 mb-1"></i>
                            <span class="small fw-bold">Client</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Full Name -->
            <div class="mb-3">
                <label for="name" class="form-label text-slate-300 small fw-semibold mb-1">Full Name *</label>
                <div class="input-group">
                    <span class="input-group-text bc-input-group-text"><i class="fa-solid fa-user"></i></span>
                    <input type="text" class="form-control bc-input" id="name" name="name" required placeholder="John Doe" value="<?= isset($_POST['name']) ? sanitize($_POST['name']) : '' ?>">
                </div>
            </div>

            <!-- Email & Phone -->
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label for="email" class="form-label text-slate-300 small fw-semibold mb-1">Email Address *</label>
                    <div class="input-group">
                        <span class="input-group-text bc-input-group-text"><i class="fa-solid fa-envelope"></i></span>
                        <input type="email" class="form-control bc-input" id="email" name="email" required placeholder="name@company.com" value="<?= isset($_POST['email']) ? sanitize($_POST['email']) : ($prefilled_email ? sanitize($prefilled_email) : '') ?>">
                    </div>
                </div>
                <div class="col-sm-6">
                    <label for="phone" class="form-label text-slate-300 small fw-semibold mb-1">Phone Number *</label>
                    <div class="input-group">
                        <span class="input-group-text bc-input-group-text"><i class="fa-solid fa-phone"></i></span>
                        <input type="tel" class="form-control bc-input" id="phone" name="phone" required placeholder="+91 98765 43210" value="<?= isset($_POST['phone']) ? sanitize($_POST['phone']) : '' ?>">
                    </div>
                </div>
            </div>

            <!-- Location / City -->
            <div class="mb-3">
                <label for="location" class="form-label text-slate-300 small fw-semibold mb-1">Location / City</label>
                <div class="input-group">
                    <span class="input-group-text bc-input-group-text"><i class="fa-solid fa-location-dot"></i></span>
                    <input type="text" class="form-control bc-input" id="location" name="location" placeholder="e.g. Ahmedabad, Gujarat" value="<?= isset($_POST['location']) ? sanitize($_POST['location']) : '' ?>">
                </div>
            </div>

            <!-- Company Field (Contractor & Client) -->
            <div id="company-field-group" class="mb-3 <?= (isset($_POST['role']) && in_array($_POST['role'], ['contractor', 'client'])) ? '' : 'd-none' ?>">
                <label for="company_name" class="form-label text-slate-300 small fw-semibold mb-1">Company / Organization Name</label>
                <div class="input-group">
                    <span class="input-group-text bc-input-group-text"><i class="fa-solid fa-building"></i></span>
                    <input type="text" class="form-control bc-input" id="company_name" name="company_name" placeholder="Apex BuildTech Pvt. Ltd." value="<?= isset($_POST['company_name']) ? sanitize($_POST['company_name']) : '' ?>">
                </div>
            </div>

            <!-- Worker Dynamic Fields (Skill & Experience) -->
            <div id="worker-fields-group" class="row g-3 mb-3 <?= (!isset($_POST['role']) || $_POST['role'] === 'worker') ? '' : 'd-none' ?>">
                <div class="col-sm-7">
                    <label for="trade_title" class="form-label text-slate-300 small fw-semibold mb-1">Skill / Trade Specialization</label>
                    <div class="input-group">
                        <span class="input-group-text bc-input-group-text"><i class="fa-solid fa-toolbox"></i></span>
                        <input type="text" class="form-control bc-input" id="trade_title" name="trade_title" placeholder="e.g. Mason, Electrician" value="<?= isset($_POST['trade_title']) ? sanitize($_POST['trade_title']) : '' ?>">
                    </div>
                </div>
                <div class="col-sm-5">
                    <label for="experience_years" class="form-label text-slate-300 small fw-semibold mb-1">Experience (Years)</label>
                    <div class="input-group">
                        <span class="input-group-text bc-input-group-text"><i class="fa-solid fa-calendar"></i></span>
                        <input type="number" class="form-control bc-input" id="experience_years" name="experience_years" min="0" max="50" placeholder="e.g. 5" value="<?= isset($_POST['experience_years']) ? (int)$_POST['experience_years'] : '' ?>">
                    </div>
                </div>
            </div>

            <!-- Passwords -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6">
                    <label for="password" class="form-label text-slate-300 small fw-semibold mb-1">Password *</label>
                    <div class="input-group">
                        <span class="input-group-text bc-input-group-text"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" class="form-control bc-input" id="password" name="password" required placeholder="Min 6 characters">
                        <button class="btn bc-input-btn-eye" type="button" onclick="togglePasswordVisibility('password', 'toggleIcon1')" aria-label="Toggle password visibility">
                            <i class="fa-solid fa-eye" id="toggleIcon1"></i>
                        </button>
                    </div>
                </div>
                <div class="col-sm-6">
                    <label for="confirm_password" class="form-label text-slate-300 small fw-semibold mb-1">Confirm Password *</label>
                    <div class="input-group">
                        <span class="input-group-text bc-input-group-text"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" class="form-control bc-input" id="confirm_password" name="confirm_password" required placeholder="Repeat password">
                        <button class="btn bc-input-btn-eye" type="button" onclick="togglePasswordVisibility('confirm_password', 'toggleIcon2')" aria-label="Toggle confirm password visibility">
                            <i class="fa-solid fa-eye" id="toggleIcon2"></i>
                        </button>
                    </div>
                </div>
            </div>

            <button type="submit" id="submitBtn" class="btn btn-amber-glow w-100 py-3 fw-bold text-uppercase tracking-wider">
                <i class="fa-solid fa-user-plus me-2"></i> CREATE ACCOUNT
            </button>
        </form>

        <div class="bc-auth-divider my-4">
            <span>OR</span>
        </div>

        <a href="<?= BASE_URL ?>/auth/google-login.php" class="btn btn-google-oauth w-100 py-2.5 fw-semibold d-flex align-items-center justify-content-center gap-2">
            <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
                <path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.616z"/>
                <path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z"/>
                <path fill="#FBBC05" d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z"/>
                <path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z"/>
            </svg>
            <span>Continue with Google</span>
        </a>

        <div class="text-center mt-4 pt-3 border-top border-secondary border-opacity-25">
            <span class="text-slate-400 small">Already have an account? </span>
            <a href="<?= BASE_URL ?>/login.php" class="text-warning fw-semibold small text-decoration-none ms-1">Sign In</a>
        </div>
    </div>
</div>

<script>
function handleRoleChange(role) {
    const companyGroup = document.getElementById('company-field-group');
    const workerGroup = document.getElementById('worker-fields-group');

    if (role === 'contractor' || role === 'client') {
        companyGroup?.classList.remove('d-none');
        workerGroup?.classList.add('d-none');
    } else {
        companyGroup?.classList.add('d-none');
        workerGroup?.classList.remove('d-none');
    }
}

function togglePasswordVisibility(fieldId, iconId) {
    const pwd = document.getElementById(fieldId);
    const icon = document.getElementById(iconId);
    if (pwd && icon) {
        if (pwd.type === 'password') {
            pwd.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            pwd.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
}

document.getElementById('registerForm')?.addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> CREATING ACCOUNT...';
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
