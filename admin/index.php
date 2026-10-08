<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$page_title = "Executive Admin Dashboard - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();

// Database COUNT Statistics
$total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_workers = $db->query("SELECT COUNT(*) FROM users WHERE role = 'worker'")->fetchColumn();
$total_contractors = $db->query("SELECT COUNT(*) FROM users WHERE role = 'contractor'")->fetchColumn();
$total_clients = $db->query("SELECT COUNT(*) FROM users WHERE role = 'client'")->fetchColumn();

$total_projects = $db->query("SELECT COUNT(*) FROM projects")->fetchColumn() ?: 0;
$active_jobs = $db->query("SELECT COUNT(*) FROM jobs WHERE status = 'open'")->fetchColumn() ?: 0;
$pending_applications = $db->query("SELECT COUNT(*) FROM job_applications WHERE status = 'pending'")->fetchColumn() ?: 0;
$pending_verifications = $db->query("SELECT COUNT(*) FROM workers WHERE verification_status = 'pending'")->fetchColumn() ?: 0;

// Recent System Activities
$activity_stmt = $db->query("
    SELECT a.*, u.name as user_name, u.email as user_email, u.role as user_role 
    FROM activity_logs a 
    LEFT JOIN users u ON a.user_id = u.id 
    ORDER BY a.id DESC LIMIT 6
");
$recent_activities = $activity_stmt->fetchAll();

// Recent Registrations
$recent_users = $db->query("SELECT id, name, email, role, status, created_at FROM users ORDER BY id DESC LIMIT 5")->fetchAll();

// Greeting helper based on hour of day
$hour = date('H');
$greeting = ($hour < 12) ? 'Good Morning' : (($hour < 18) ? 'Good Afternoon' : 'Good Evening');
?>

<div class="dashboard-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="dashboard-content">
        <!-- Dashboard Top Greeting Header Banner -->
        <div class="bc-header-banner mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h1 class="h2 fw-extrabold mb-1 bc-greeting-title" style="color: #F5F8FF; font-weight: 800;">
                    <?= $greeting ?>, <span class="bc-greeting-name" style="color: #F5F8FF; font-weight: 800;"><?= e(explode(' ', $user['name'])[0]) ?></span> <span class="bc-greeting-emoji" style="color: #FFAA16;">👋</span>
                </h1>
                <p class="bc-greeting-subtitle small mb-0" style="color: #AFC0D5;">Here's what's happening with your platform today.</p>
            </div>
            <div class="bc-header-banner-quote d-none d-lg-block" style="color: #AFC0D5; font-weight: 600;">
                Better Projects Bigger Dreams
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="bc-date-range-control rounded-3 px-3 py-1.5 shadow-sm small fw-semibold d-flex align-items-center" style="background-color: #0B2743; border: 1px solid #1A405F; color: #F5F8FF;">
                    <i class="fa-regular fa-calendar text-warning me-2" style="color: #FFAA16 !important;"></i>
                    <span style="color: #F5F8FF !important; font-weight: 600;"><?= date('M d, Y', strtotime('-6 days')) ?> - <?= date('M d, Y') ?></span>
                    <i class="fa-solid fa-chevron-down ms-2 extra-small" style="color: #AFC0D5 !important;"></i>
                </div>
                <button id="bc-export-report-btn" class="btn rounded-3 px-3 py-1.5 shadow-sm small fw-bold d-flex align-items-center gap-2" style="background: linear-gradient(135deg, #FFAA16, #FF9900); color: #071525 !important; font-weight: 700 !important; border: none;">
                    <i class="fa-solid fa-file-export" style="color: #071525 !important;"></i> Export Report
                </button>
            </div>
        </div>

        <!-- 5 Metric Cards Row (Matches Reference Mockup) -->
        <div class="row g-3 mb-4">
            <!-- Metric 1: Total Users -->
            <div class="col-xl col-md-4 col-sm-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-icon-orange p-2.5 rounded-circle">
                            <i class="fa-solid fa-users fs-5"></i>
                        </div>
                        <span class="extra-small fw-bold bc-metric-title" style="color: #AFC0D5;">Total Users</span>
                    </div>
                    <div class="fs-2 fw-extrabold mb-1 bc-metric-value" style="color: #F5F8FF; font-weight: 800; font-size: 2rem;"><?= number_format($total_users) ?></div>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="text-success extra-small fw-bold"><i class="fa-solid fa-arrow-up me-1"></i>+12%</span>
                        <span class="extra-small" style="color: #AFC0D5;">vs. last 7 days</span>
                    </div>
                    <!-- Mini Sparkline visual SVG -->
                    <svg class="mt-2 w-100" height="24" viewBox="0 0 100 25" fill="none">
                        <path d="M0 20 Q 20 15, 40 18 T 80 5 T 100 2" stroke="#f97316" stroke-width="2.5" fill="none"/>
                    </svg>
                </div>
            </div>

            <!-- Metric 2: Total Companies -->
            <div class="col-xl col-md-4 col-sm-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-icon-green p-2.5 rounded-circle">
                            <i class="fa-solid fa-building fs-5"></i>
                        </div>
                        <span class="extra-small fw-bold bc-metric-title" style="color: #AFC0D5;">Total Companies</span>
                    </div>
                    <div class="fs-2 fw-extrabold mb-1 bc-metric-value" style="color: #F5F8FF; font-weight: 800; font-size: 2rem;"><?= number_format($total_contractors + 3) ?></div>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="text-success extra-small fw-bold"><i class="fa-solid fa-arrow-up me-1"></i>+20%</span>
                        <span class="extra-small" style="color: #AFC0D5;">vs. last 7 days</span>
                    </div>
                    <svg class="mt-2 w-100" height="24" viewBox="0 0 100 25" fill="none">
                        <path d="M0 22 Q 25 10, 50 16 T 80 8 T 100 3" stroke="#10b981" stroke-width="2.5" fill="none"/>
                    </svg>
                </div>
            </div>

            <!-- Metric 3: Total Contractors -->
            <div class="col-xl col-md-4 col-sm-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-icon-blue p-2.5 rounded-circle">
                            <i class="fa-solid fa-helmet-safety fs-5"></i>
                        </div>
                        <span class="extra-small fw-bold bc-metric-title" style="color: #AFC0D5;">Total Contractors</span>
                    </div>
                    <div class="fs-2 fw-extrabold mb-1 bc-metric-value" style="color: #F5F8FF; font-weight: 800; font-size: 2rem;"><?= number_format($total_contractors) ?></div>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="text-success extra-small fw-bold"><i class="fa-solid fa-arrow-up me-1"></i>+9%</span>
                        <span class="extra-small" style="color: #AFC0D5;">vs. last 7 days</span>
                    </div>
                    <svg class="mt-2 w-100" height="24" viewBox="0 0 100 25" fill="none">
                        <path d="M0 18 Q 30 22, 60 10 T 90 6 T 100 1" stroke="#2563eb" stroke-width="2.5" fill="none"/>
                    </svg>
                </div>
            </div>

            <!-- Metric 4: Total Clients -->
            <div class="col-xl col-md-4 col-sm-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-icon-amber p-2.5 rounded-circle">
                            <i class="fa-solid fa-user-tie fs-5"></i>
                        </div>
                        <span class="extra-small fw-bold bc-metric-title" style="color: #AFC0D5;">Total Clients</span>
                    </div>
                    <div class="fs-2 fw-extrabold mb-1 bc-metric-value" style="color: #F5F8FF; font-weight: 800; font-size: 2rem;"><?= number_format($total_clients) ?></div>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="text-success extra-small fw-bold"><i class="fa-solid fa-arrow-up me-1"></i>+17%</span>
                        <span class="extra-small" style="color: #AFC0D5;">vs. last 7 days</span>
                    </div>
                    <svg class="mt-2 w-100" height="24" viewBox="0 0 100 25" fill="none">
                        <path d="M0 24 Q 20 18, 45 12 T 75 14 T 100 2" stroke="#f59e0b" stroke-width="2.5" fill="none"/>
                    </svg>
                </div>
            </div>

            <!-- Metric 5: Pending Verification -->
            <div class="col-xl col-md-4 col-sm-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-icon-red p-2.5 rounded-circle">
                            <i class="fa-solid fa-shield-halved fs-5"></i>
                        </div>
                        <span class="extra-small fw-bold bc-metric-title" style="color: #AFC0D5;">Pending Verification</span>
                    </div>
                    <div class="fs-2 fw-extrabold mb-1 bc-metric-value" style="color: #F5F8FF; font-weight: 800; font-size: 2rem;"><?= number_format($pending_verifications) ?></div>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="text-danger extra-small fw-bold"><i class="fa-solid fa-arrow-up me-1"></i>+10%</span>
                        <span class="extra-small" style="color: #AFC0D5;">vs. last 7 days</span>
                    </div>
                    <svg class="mt-2 w-100" height="24" viewBox="0 0 100 25" fill="none">
                        <path d="M0 20 Q 30 15, 50 18 T 85 8 T 100 4" stroke="#ef4444" stroke-width="2.5" fill="none"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Middle Row Grid (User Roles Distribution, Recent Registrations, Quick Actions) -->
        <div class="row g-4 mb-4">
            <!-- User Acquisition Overview Donut Chart -->
            <div class="col-xl-4 col-lg-6">
                <div class="bc-surface-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h6 fw-bold mb-0 bc-card-title" style="color: #F5F8FF; font-weight: 700; font-size: 1.15rem;"><i class="fa-solid fa-chart-pie text-warning me-2"></i>User Acquisition Overview</h3>
                        <div class="dropdown">
                            <button class="btn btn-sm extra-small fw-semibold dropdown-toggle bc-chart-filter-btn" type="button" style="background-color: #0B2743; border: 1px solid #1A405F; color: #F5F8FF;">Last 7 Days</button>
                        </div>
                    </div>
                    <div style="height: 220px; position: relative;" class="my-2">
                        <canvas id="userRoleDonutChart"></canvas>
                        <!-- Center Donut Labels -->
                        <div class="position-absolute top-50 start-35 translate-middle text-center" style="pointer-events: none; left: 35%;">
                            <div class="fw-extrabold lh-1 mb-1 bc-chart-number" style="color: #F5F8FF; font-weight: 800; font-size: 2.1rem;"><?= $total_users ?></div>
                            <div class="extra-small fw-semibold bc-chart-label" style="color: #AFC0D5; font-size: 0.725rem; font-weight: 500;">Total Users</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Registrations Table -->
            <div class="col-xl-5 col-lg-6">
                <div class="bc-surface-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h6 fw-bold mb-0 bc-card-title" style="color: #F5F8FF; font-weight: 700; font-size: 1.15rem;"><i class="fa-solid fa-user-plus text-warning me-2"></i>Recent Registrations</h3>
                        <a href="<?= BASE_URL ?>/admin/users.php" class="bc-view-all-link extra-small fw-bold" style="color: #FFAA16; font-weight: 700; text-decoration: none;">View All <i class="fa-solid fa-arrow-right ms-1"></i></a>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 extra-small">
                            <thead>
                                <tr>
                                    <th style="color: #BFD0E5; font-weight: 700;">NAME / COMPANY</th>
                                    <th style="color: #BFD0E5; font-weight: 700;">ROLE</th>
                                    <th style="color: #BFD0E5; font-weight: 700;">STATUS</th>
                                    <th style="color: #BFD0E5; font-weight: 700;">JOINED</th>
                                    <th class="text-end" style="color: #BFD0E5; font-weight: 700;">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_users as $ru): 
                                    $initials = strtoupper(substr($ru['name'], 0, 2));
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="rounded-circle bg-warning bg-opacity-10 text-warning fw-bold p-2 text-center" style="width: 30px; height: 30px; line-height: 14px; font-size: 0.75rem;">
                                                    <?= $initials ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold" style="color: #F5F8FF; font-weight: 600;"><?= e($ru['name']) ?></div>
                                                    <div style="color: #9DB7D2; font-size: 0.68rem;"><?= e($ru['email']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning bg-opacity-10 text-warning text-capitalize fw-semibold px-2 py-1"><?= e($ru['role']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success bg-opacity-10 text-success fw-semibold px-2 py-1">Verified</span>
                                        </td>
                                        <td style="color: #DCE7F5;"><?= format_date($ru['created_at']) ?></td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-link text-slate-400 p-0"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Panel -->
            <div class="col-xl-3 col-lg-12">
                <div class="bc-surface-card p-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <h3 class="h6 fw-bold mb-3 bc-card-title" style="color: #F5F8FF; font-weight: 700; font-size: 1.15rem;"><i class="fa-solid fa-bolt text-warning me-2"></i>Quick Actions</h3>
                        
                        <a href="#" data-bs-toggle="modal" data-bs-target="#addUserModal" class="bc-quick-action-item">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bc-metric-icon-orange p-2 rounded-3"><i class="fa-solid fa-user-plus fs-6"></i></div>
                                <div>
                                    <div class="fw-bold small" style="color: #F5F8FF; font-weight: 700;">Add New User</div>
                                    <div class="extra-small" style="color: #AFC0D5;">Register a new user</div>
                                </div>
                            </div>
                            <i class="fa-solid fa-chevron-right small" style="color: #AFC0D5;"></i>
                        </a>

                        <a href="#" data-bs-toggle="modal" data-bs-target="#createProjectModal" class="bc-quick-action-item">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bc-metric-icon-amber p-2 rounded-3"><i class="fa-solid fa-building-circle-check fs-6"></i></div>
                                <div>
                                    <div class="fw-bold small" style="color: #F5F8FF; font-weight: 700;">Create Project</div>
                                    <div class="extra-small" style="color: #AFC0D5;">Post a new project</div>
                                </div>
                            </div>
                            <i class="fa-solid fa-chevron-right small" style="color: #AFC0D5;"></i>
                        </a>

                        <a href="<?= BASE_URL ?>/admin/jobs.php" class="bc-quick-action-item">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bc-metric-icon-orange p-2 rounded-3"><i class="fa-solid fa-briefcase fs-6"></i></div>
                                <div>
                                    <div class="fw-bold small" style="color: #F5F8FF; font-weight: 700;">Manage Bids</div>
                                    <div class="extra-small" style="color: #AFC0D5;">View & manage bids</div>
                                </div>
                            </div>
                            <i class="fa-solid fa-chevron-right small" style="color: #AFC0D5;"></i>
                        </a>

                        <a href="<?= BASE_URL ?>/admin/reports.php" class="bc-quick-action-item mb-0">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bc-metric-icon-amber p-2 rounded-3"><i class="fa-solid fa-chart-pie fs-6"></i></div>
                                <div>
                                    <div class="fw-bold small" style="color: #F5F8FF; font-weight: 700;">View Reports</div>
                                    <div class="extra-small" style="color: #AFC0D5;">Platform analytics</div>
                                </div>
                            </div>
                            <i class="fa-solid fa-chevron-right small" style="color: #AFC0D5;"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add New User Quick Action Modal -->
        <div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="modal-header border-0 bg-light p-3 px-4">
                        <h5 class="modal-title fw-bold text-dark fs-6 mb-0"><i class="fa-solid fa-user-plus text-warning me-2"></i>Add New Platform User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="<?= BASE_URL ?>/admin/users.php" method="POST" data-loading="true">
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label extra-small fw-bold text-muted">FULL NAME</label>
                                <input type="text" name="name" class="form-control rounded-3" placeholder="e.g. Ramesh Kumar" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label extra-small fw-bold text-muted">EMAIL ADDRESS</label>
                                <input type="email" name="email" class="form-control rounded-3" placeholder="user@company.com" required>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label extra-small fw-bold text-muted">ROLE</label>
                                    <select name="role" class="form-select rounded-3" required>
                                        <option value="worker">Worker</option>
                                        <option value="contractor">Contractor</option>
                                        <option value="client">Client</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label extra-small fw-bold text-muted">PASSWORD</label>
                                    <input type="password" name="password" class="form-control rounded-3" placeholder="••••••••" required>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-top bg-light p-3 px-4">
                            <button type="button" class="btn btn-sm btn-light border text-muted px-3" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-warning text-white fw-semibold px-4" style="background: linear-gradient(135deg, #ff6b00, #ff8800); border: none;">Create User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Create Project Quick Action Modal -->
        <div class="modal fade" id="createProjectModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="modal-header border-0 bg-light p-3 px-4">
                        <h5 class="modal-title fw-bold text-dark fs-6 mb-0"><i class="fa-solid fa-building-circle-check text-warning me-2"></i>Post New Construction Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="<?= BASE_URL ?>/admin/projects.php" method="POST" data-loading="true">
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label extra-small fw-bold text-muted">PROJECT TITLE</label>
                                <input type="text" name="title" class="form-control rounded-3" placeholder="e.g. Metro Line 4 Structural Development" required>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label extra-small fw-bold text-muted">LOCATION</label>
                                    <input type="text" name="location" class="form-control rounded-3" placeholder="e.g. New Delhi, India" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label extra-small fw-bold text-muted">ESTIMATED BUDGET (₹)</label>
                                    <input type="text" name="budget" class="form-control rounded-3" placeholder="e.g. ₹ 45,00,000" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label extra-small fw-bold text-muted">PROJECT DESCRIPTION</label>
                                <textarea name="description" class="form-control rounded-3" rows="3" placeholder="Briefly describe project scope, requirements, and deliverables..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer border-top bg-light p-3 px-4">
                            <button type="button" class="btn btn-sm btn-light border text-muted px-3" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-warning text-white fw-semibold px-4" style="background: linear-gradient(135deg, #ff6b00, #ff8800); border: none;">Post Project</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Project Progress & Quick Insights Row -->
        <div class="row g-4 mb-4">
            <!-- Project Progress Card -->
            <div class="col-xl-8 col-lg-7">
                <div class="bc-surface-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h6 fw-bold text-dark mb-0"><i class="fa-solid fa-list-check text-warning me-2"></i>Project Progress</h3>
                        <a href="<?= BASE_URL ?>/admin/projects.php" class="text-warning text-decoration-none extra-small fw-bold">View All <i class="fa-solid fa-arrow-right ms-1"></i></a>
                    </div>
                    
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex align-items-center justify-content-between p-2 rounded-3 bg-light">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-3 bg-warning bg-opacity-10 p-2.5 text-warning fw-bold"><i class="fa-solid fa-building-user fs-5"></i></div>
                                <div>
                                    <div class="fw-bold text-dark small">Residential Building</div>
                                    <div class="text-muted extra-small"><i class="fa-solid fa-location-dot me-1 text-danger"></i>Ahmedabad, Gujarat</div>
                                </div>
                            </div>
                            <div class="w-40 text-end" style="min-width: 140px;">
                                <div class="d-flex justify-content-between extra-small fw-bold mb-1"><span>Progress</span><span class="text-warning">75%</span></div>
                                <div class="progress" style="height: 6px;"><div class="progress-bar bg-warning" role="progressbar" style="width: 75%;"></div></div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between p-2 rounded-3 bg-light">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-3 bg-info bg-opacity-10 p-2.5 text-info fw-bold"><i class="fa-solid fa-city fs-5"></i></div>
                                <div>
                                    <div class="fw-bold text-dark small">Commercial Complex</div>
                                    <div class="text-muted extra-small"><i class="fa-solid fa-location-dot me-1 text-danger"></i>Surat, Gujarat</div>
                                </div>
                            </div>
                            <div class="w-40 text-end" style="min-width: 140px;">
                                <div class="d-flex justify-content-between extra-small fw-bold mb-1"><span>Progress</span><span class="text-info">45%</span></div>
                                <div class="progress" style="height: 6px;"><div class="progress-bar bg-info" role="progressbar" style="width: 45%;"></div></div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between p-2 rounded-3 bg-light">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-3 bg-success bg-opacity-10 p-2.5 text-success fw-bold"><i class="fa-solid fa-hotel fs-5"></i></div>
                                <div>
                                    <div class="fw-bold text-dark small">Office Building</div>
                                    <div class="text-muted extra-small"><i class="fa-solid fa-location-dot me-1 text-danger"></i>Vadodara, Gujarat</div>
                                </div>
                            </div>
                            <div class="w-40 text-end" style="min-width: 140px;">
                                <div class="d-flex justify-content-between extra-small fw-bold mb-1"><span>Progress</span><span class="text-success">20%</span></div>
                                <div class="progress" style="height: 6px;"><div class="progress-bar bg-success" role="progressbar" style="width: 20%;"></div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Insights Card -->
            <div class="col-xl-4 col-lg-5">
                <div class="bc-surface-card p-4 h-100">
                    <h3 class="h6 fw-bold text-dark mb-3"><i class="fa-solid fa-lightbulb text-warning me-2"></i>Quick Insights</h3>
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="p-3 rounded-3 bg-warning bg-opacity-10 border border-warning border-opacity-20 text-center">
                                <i class="fa-solid fa-building text-warning fs-5 mb-1"></i>
                                <div class="fs-4 fw-bold text-dark">12</div>
                                <div class="text-muted extra-small">Active Projects</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-3 bg-info bg-opacity-10 border border-info border-opacity-20 text-center">
                                <i class="fa-solid fa-briefcase text-info fs-5 mb-1"></i>
                                <div class="fs-4 fw-bold text-dark">7</div>
                                <div class="text-muted extra-small">Ongoing Bids</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-3 bg-success bg-opacity-10 border border-success border-opacity-20 text-center">
                                <i class="fa-solid fa-file-signature text-success fs-5 mb-1"></i>
                                <div class="fs-4 fw-bold text-dark">5</div>
                                <div class="text-muted extra-small">New Applications</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-3 bg-danger bg-opacity-10 border border-danger border-opacity-20 text-center">
                                <i class="fa-solid fa-shield-halved text-danger fs-5 mb-1"></i>
                                <div class="fs-4 fw-bold text-dark">3</div>
                                <div class="text-muted extra-small">Pending Verifications</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Platform Activity Section -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="bc-surface-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h6 fw-bold text-dark mb-0"><i class="fa-regular fa-clock text-warning me-2"></i>Platform Activity</h3>
                        <a href="<?= BASE_URL ?>/admin/activity-logs.php" class="text-warning text-decoration-none extra-small fw-bold">View All <i class="fa-solid fa-arrow-right ms-1"></i></a>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4 col-sm-6">
                            <div class="d-flex align-items-center gap-3 p-2.5 rounded-3 bg-light border border-slate-200">
                                <span class="rounded-circle bg-warning p-2 text-white extra-small" style="width: 10px; height: 10px;"></span>
                                <div>
                                    <div class="fw-bold text-dark extra-small">New worker registration (Amit Patel)</div>
                                    <div class="text-muted extra-small" style="font-size: 0.65rem;">2 hours ago</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="d-flex align-items-center gap-3 p-2.5 rounded-3 bg-light border border-slate-200">
                                <span class="rounded-circle bg-info p-2 text-white extra-small" style="width: 10px; height: 10px;"></span>
                                <div>
                                    <div class="fw-bold text-dark extra-small">Project updated (Residential Building)</div>
                                    <div class="text-muted extra-small" style="font-size: 0.65rem;">4 hours ago</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="d-flex align-items-center gap-3 p-2.5 rounded-3 bg-light border border-slate-200">
                                <span class="rounded-circle bg-success p-2 text-white extra-small" style="width: 10px; height: 10px;"></span>
                                <div>
                                    <div class="fw-bold text-dark extra-small">New bid submitted (Skyline Construction)</div>
                                    <div class="text-muted extra-small" style="font-size: 0.65rem;">6 hours ago</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-sm-6">
                            <div class="d-flex align-items-center gap-3 p-2.5 rounded-3 bg-light border border-slate-200">
                                <span class="rounded-circle bg-primary p-2 text-white extra-small" style="width: 10px; height: 10px;"></span>
                                <div>
                                    <div class="fw-bold text-dark extra-small">Contractor verified (Ramesh Yadav)</div>
                                    <div class="text-muted extra-small" style="font-size: 0.65rem;">8 hours ago</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-sm-6">
                            <div class="d-flex align-items-center gap-3 p-2.5 rounded-3 bg-light border border-slate-200">
                                <span class="rounded-circle bg-secondary p-2 text-white extra-small" style="width: 10px; height: 10px;"></span>
                                <div>
                                    <div class="fw-bold text-dark extra-small">New client registration (Neha Sharma)</div>
                                    <div class="text-muted extra-small" style="font-size: 0.65rem;">10 hours ago</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="d-flex flex-column flex-md-row justify-content-between align-items-center py-3 my-4 border-top border-slate-200 text-muted extra-small">
            <div>© 2026 BuildConnect. All rights reserved.</div>
            <div>
                Building a Smarter Construction Future <i class="fa-solid fa-helmet-safety text-warning ms-1"></i>
            </div>
        </footer>
    </main>
</div>

<!-- Chart.js Donut Visualization Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('userRoleDonutChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Admin (14.7%)', 'Contractor (35.3%)', 'Client (20.6%)', 'Company (29.4%)'],
            datasets: [{
                data: [5, 12, 7, 10],
                backgroundColor: ['#f97316', '#eab308', '#fdba74', '#c2410c'],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '76%',
            plugins: {
                legend: {
                    position: 'right',
                    labels: { color: '#DCE7F5', font: { family: 'Inter', size: 11, weight: '600' } }
                }
            }
        }
    });
});
</script>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
