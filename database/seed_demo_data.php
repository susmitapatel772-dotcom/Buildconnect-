<?php
/**
 * BuildConnect - Comprehensive Presentation Demo Data Seeder
 * Idempotent, FK-safe MySQL seeder creating real database records.
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    echo "Starting BuildConnect Complete Presentation Seed...\n";

    // 1. Password hash for Demo@12345
    $demoPasswordHash = password_hash('Demo@12345', PASSWORD_DEFAULT);

    // 2. Define Demo Users
    $usersData = [
        [
            'email' => 'admin@buildconnect.demo',
            'name' => 'System Admin',
            'role' => 'admin',
            'phone' => '+91 98765 00000',
            'avatar' => 'default_avatar.png'
        ],
        [
            'email' => 'karan.client@buildconnect.demo',
            'name' => 'Karan Dangi',
            'role' => 'client',
            'phone' => '+91 98250 11111',
            'avatar' => 'default_avatar.png'
        ],
        [
            'email' => 'neha.client@buildconnect.demo',
            'name' => 'Neha Patel',
            'role' => 'client',
            'phone' => '+91 98250 22222',
            'avatar' => 'default_avatar.png'
        ],
        [
            'email' => 'rajesh.contractor@buildconnect.demo',
            'name' => 'Rajesh Sharma',
            'role' => 'contractor',
            'phone' => '+91 98790 33333',
            'avatar' => 'default_avatar.png'
        ],
        [
            'email' => 'vikram.contractor@buildconnect.demo',
            'name' => 'Vikram Mehta',
            'role' => 'contractor',
            'phone' => '+91 98790 44444',
            'avatar' => 'default_avatar.png'
        ],
        [
            'email' => 'amit.worker@buildconnect.demo',
            'name' => 'Amit Patel',
            'role' => 'worker',
            'phone' => '+91 99090 55555',
            'avatar' => 'default_avatar.png'
        ],
        [
            'email' => 'ravi.worker@buildconnect.demo',
            'name' => 'Ravi Kumar',
            'role' => 'worker',
            'phone' => '+91 99090 66666',
            'avatar' => 'default_avatar.png'
        ],
        [
            'email' => 'dhruv.worker@buildconnect.demo',
            'name' => 'Dhruv Shah',
            'role' => 'worker',
            'phone' => '+91 99090 77777',
            'avatar' => 'default_avatar.png'
        ],
        [
            'email' => 'rahul.worker@buildconnect.demo',
            'name' => 'Rahul Solanki',
            'role' => 'worker',
            'phone' => '+91 99090 88888',
            'avatar' => 'default_avatar.png'
        ]
    ];

    $userMap = []; // email => user_id

    foreach ($usersData as $u) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$u['email']]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            $userMap[$u['email']] = $existing;
            $up = $db->prepare("UPDATE users SET name = ?, role = ?, password = ?, phone = ?, status = 'active' WHERE id = ?");
            $up->execute([$u['name'], $u['role'], $demoPasswordHash, $u['phone'], $existing]);
        } else {
            $ins = $db->prepare("INSERT INTO users (name, email, password, role, phone, avatar, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $ins->execute([$u['name'], $u['email'], $demoPasswordHash, $u['role'], $u['phone'], $u['avatar']]);
            $userMap[$u['email']] = $db->lastInsertId();
        }
    }

    echo "Users populated: " . count($userMap) . "\n";

    // 3. Clients
    $clientsData = [
        [
            'email' => 'karan.client@buildconnect.demo',
            'company_name' => 'Dangi Infrastructure',
            'client_type' => 'Real Estate Developer',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'address' => 'Satellite Road, Ahmedabad'
        ],
        [
            'email' => 'neha.client@buildconnect.demo',
            'company_name' => 'Patel Developers',
            'client_type' => 'Commercial Developer',
            'city' => 'Surat',
            'state' => 'Gujarat',
            'address' => 'Vesu, Surat'
        ]
    ];

    foreach ($clientsData as $c) {
        $uId = $userMap[$c['email']];
        $stmt = $db->prepare("SELECT id FROM clients WHERE user_id = ?");
        $stmt->execute([$uId]);
        if ($stmt->fetchColumn()) {
            $up = $db->prepare("UPDATE clients SET company_name = ?, client_type = ?, city = ?, state = ?, address = ? WHERE user_id = ?");
            $up->execute([$c['company_name'], $c['client_type'], $c['city'], $c['state'], $c['address'], $uId]);
        } else {
            $ins = $db->prepare("INSERT INTO clients (user_id, company_name, client_type, city, state, address) VALUES (?, ?, ?, ?, ?, ?)");
            $ins->execute([$uId, $c['company_name'], $c['client_type'], $c['city'], $c['state'], $c['address']]);
        }
    }
    echo "Clients populated.\n";

    // 4. Contractors
    $contractorsData = [
        [
            'email' => 'rajesh.contractor@buildconnect.demo',
            'company_name' => 'Rajesh BuildTech',
            'license_no' => 'GJ-BC-2024-001',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'company_address' => 'SG Highway, Ahmedabad',
            'rating_avg' => 4.80
        ],
        [
            'email' => 'vikram.contractor@buildconnect.demo',
            'company_name' => 'Vikram Infrastructure Pvt. Ltd.',
            'license_no' => 'GJ-BC-2024-002',
            'city' => 'Vadodara',
            'state' => 'Gujarat',
            'company_address' => 'Alkapuri, Vadodara',
            'rating_avg' => 4.90
        ]
    ];

    foreach ($contractorsData as $c) {
        $uId = $userMap[$c['email']];
        $stmt = $db->prepare("SELECT id FROM contractors WHERE user_id = ?");
        $stmt->execute([$uId]);
        if ($stmt->fetchColumn()) {
            $up = $db->prepare("UPDATE contractors SET company_name = ?, license_no = ?, city = ?, state = ?, company_address = ?, rating_avg = ? WHERE user_id = ?");
            $up->execute([$c['company_name'], $c['license_no'], $c['city'], $c['state'], $c['company_address'], $c['rating_avg'], $uId]);
        } else {
            $ins = $db->prepare("INSERT INTO contractors (user_id, company_name, license_no, city, state, company_address, rating_avg) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$uId, $c['company_name'], $c['license_no'], $c['city'], $c['state'], $c['company_address'], $c['rating_avg']]);
        }
    }
    echo "Contractors populated.\n";

    // 5. Workers
    $adminId = $userMap['admin@buildconnect.demo'];
    $workersData = [
        [
            'email' => 'amit.worker@buildconnect.demo',
            'trade_title' => 'Mason',
            'experience_years' => 6,
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'address' => 'Satellite Road, Ahmedabad',
            'rating_avg' => 4.90
        ],
        [
            'email' => 'ravi.worker@buildconnect.demo',
            'trade_title' => 'Electrician',
            'experience_years' => 4,
            'city' => 'Surat',
            'state' => 'Gujarat',
            'address' => 'Vesu, Surat',
            'rating_avg' => 4.70
        ],
        [
            'email' => 'dhruv.worker@buildconnect.demo',
            'trade_title' => 'Plumber',
            'experience_years' => 5,
            'city' => 'Vadodara',
            'state' => 'Gujarat',
            'address' => 'Manjalpur, Vadodara',
            'rating_avg' => 4.80
        ],
        [
            'email' => 'rahul.worker@buildconnect.demo',
            'trade_title' => 'Carpenter',
            'experience_years' => 7,
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'address' => 'Nikol, Ahmedabad',
            'rating_avg' => 4.90
        ]
    ];

    $workerProfileIds = [];

    foreach ($workersData as $w) {
        $uId = $userMap[$w['email']];
        $stmt = $db->prepare("SELECT id FROM workers WHERE user_id = ?");
        $stmt->execute([$uId]);
        $existingWorkerId = $stmt->fetchColumn();

        if ($existingWorkerId) {
            $up = $db->prepare("UPDATE workers SET trade_title = ?, experience_years = ?, city = ?, state = ?, address = ?, rating_avg = ?, verification_status = 'approved', verified_at = NOW(), verified_by_user_id = ? WHERE user_id = ?");
            $up->execute([$w['trade_title'], $w['experience_years'], $w['city'], $w['state'], $w['address'], $w['rating_avg'], $adminId, $uId]);
            $workerProfileIds[$w['email']] = $existingWorkerId;
        } else {
            $ins = $db->prepare("INSERT INTO workers (user_id, trade_title, experience_years, city, state, address, rating_avg, verification_status, verified_at, verified_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, 'approved', NOW(), ?)");
            $ins->execute([$uId, $w['trade_title'], $w['experience_years'], $w['city'], $w['state'], $w['address'], $w['rating_avg'], $adminId]);
            $workerProfileIds[$w['email']] = $db->lastInsertId();
        }
    }
    echo "Workers populated.\n";

    // 6. Skills & Worker Skills
    $skillsList = [
        'Brick Masonry', 'Concrete Work', 'Foundation Work', 'Plastering', 'Construction Safety',
        'Electrical Wiring', 'Panel Installation', 'Lighting', 'Industrial Electrical Work', 'Safety Inspection',
        'Pipeline Installation', 'Water Supply', 'Drainage', 'Bathroom Fitting', 'Plumbing Maintenance',
        'Wood Work', 'Furniture Installation', 'Interior Carpentry', 'Door Installation', 'Modular Work'
    ];

    $skillMap = [];
    foreach ($skillsList as $sk) {
        $stmt = $db->prepare("SELECT id FROM skills WHERE name = ?");
        $stmt->execute([$sk]);
        $skId = $stmt->fetchColumn();
        if (!$skId) {
            $ins = $db->prepare("INSERT INTO skills (name, category) VALUES (?, 'Construction')");
            $ins->execute([$sk]);
            $skId = $db->lastInsertId();
        }
        $skillMap[$sk] = $skId;
    }

    $workerSkillsMapping = [
        'amit.worker@buildconnect.demo' => ['Brick Masonry', 'Concrete Work', 'Foundation Work', 'Plastering', 'Construction Safety'],
        'ravi.worker@buildconnect.demo' => ['Electrical Wiring', 'Panel Installation', 'Lighting', 'Industrial Electrical Work', 'Safety Inspection'],
        'dhruv.worker@buildconnect.demo' => ['Pipeline Installation', 'Water Supply', 'Drainage', 'Bathroom Fitting', 'Plumbing Maintenance'],
        'rahul.worker@buildconnect.demo' => ['Wood Work', 'Furniture Installation', 'Interior Carpentry', 'Door Installation', 'Modular Work']
    ];

    foreach ($workerSkillsMapping as $wEmail => $skillsArr) {
        $wProfId = $workerProfileIds[$wEmail];
        foreach ($skillsArr as $skName) {
            $skId = $skillMap[$skName];
            $stmt = $db->prepare("INSERT IGNORE INTO worker_skills (worker_id, skill_id, proficiency_level) VALUES (?, ?, 'expert')");
            $stmt->execute([$wProfId, $skId]);
        }
    }
    echo "Skills populated.\n";

    // 7. Projects
    $projectsData = [
        [
            'key' => 'green_heights',
            'title' => 'Green Heights Residency',
            'type' => 'Residential',
            'location' => 'Satellite Road, Ahmedabad, Gujarat',
            'address' => 'Satellite Road',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'budget' => 12500000.00,
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => 'in_progress',
            'progress_percent' => 42,
            'contractor_email' => 'rajesh.contractor@buildconnect.demo',
            'client_email' => 'karan.client@buildconnect.demo',
            'lat' => 23.0225,
            'lng' => 72.5714,
            'description' => 'Modern residential development consisting of premium apartments, parking facilities and common amenities.'
        ],
        [
            'key' => 'sunrise_villa',
            'title' => 'Sunrise Villa Project',
            'type' => 'Residential Villa',
            'location' => 'Manjalpur, Vadodara, Gujarat',
            'address' => 'Manjalpur',
            'city' => 'Vadodara',
            'state' => 'Gujarat',
            'budget' => 8500000.00,
            'start_date' => '2026-07-01',
            'end_date' => '2027-02-28',
            'status' => 'in_progress',
            'progress_percent' => 67,
            'contractor_email' => 'vikram.contractor@buildconnect.demo',
            'client_email' => 'karan.client@buildconnect.demo',
            'lat' => 22.3072,
            'lng' => 73.1812,
            'description' => 'Premium villa construction project with modern architecture, landscaped outdoor areas and smart-home infrastructure.'
        ],
        [
            'key' => 'techpark_commercial',
            'title' => 'TechPark Commercial Complex',
            'type' => 'Commercial',
            'location' => 'Vesu, Surat, Gujarat',
            'address' => 'Vesu Main Road',
            'city' => 'Surat',
            'state' => 'Gujarat',
            'budget' => 24000000.00,
            'start_date' => '2026-08-15',
            'end_date' => '2027-12-31',
            'status' => 'in_progress',
            'progress_percent' => 28,
            'contractor_email' => 'rajesh.contractor@buildconnect.demo',
            'client_email' => 'neha.client@buildconnect.demo',
            'lat' => 21.1702,
            'lng' => 72.8311,
            'description' => 'State-of-the-art commercial tech park featuring modern office spaces, basement parking, and advanced security system.'
        ]
    ];

    $projectMap = [];

    foreach ($projectsData as $p) {
        $cUserId = $userMap[$p['contractor_email']];
        $clUserId = $userMap[$p['client_email']];

        $stmt = $db->prepare("SELECT id FROM projects WHERE title = ? AND contractor_id = ?");
        $stmt->execute([$p['title'], $cUserId]);
        $pId = $stmt->fetchColumn();

        if ($pId) {
            $up = $db->prepare("UPDATE projects SET client_id = ?, description = ?, location = ?, address = ?, city = ?, state = ?, budget = ?, start_date = ?, end_date = ?, status = ?, progress_percent = ?, location_lat = ?, location_lng = ? WHERE id = ?");
            $up->execute([$clUserId, $p['description'], $p['location'], $p['address'], $p['city'], $p['state'], $p['budget'], $p['start_date'], $p['end_date'], $p['status'], $p['progress_percent'], $p['lat'], $p['lng'], $pId]);
            $projectMap[$p['key']] = $pId;
        } else {
            $ins = $db->prepare("INSERT INTO projects (contractor_id, client_id, title, description, location, address, city, state, budget, start_date, end_date, status, progress_percent, location_lat, location_lng) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$cUserId, $clUserId, $p['title'], $p['description'], $p['location'], $p['address'], $p['city'], $p['state'], $p['budget'], $p['start_date'], $p['end_date'], $p['status'], $p['progress_percent'], $p['lat'], $p['lng']]);
            $projectMap[$p['key']] = $db->lastInsertId();
        }
    }
    echo "Projects populated: " . count($projectMap) . "\n";

    // 8. Contractor Jobs
    $jobsData = [
        [
            'key' => 'mason_job',
            'project_key' => 'green_heights',
            'contractor_email' => 'rajesh.contractor@buildconnect.demo',
            'title' => 'Experienced Mason Required',
            'trade_required' => 'Mason',
            'description' => 'Looking for experienced masons for brickwork and ground floor masonry work.',
            'pay_rate' => 28000.00,
            'pay_type' => 'daily',
            'location' => 'Ahmedabad',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'spots_available' => 3,
            'spots_filled' => 1,
            'status' => 'open'
        ],
        [
            'key' => 'electrician_job',
            'project_key' => 'techpark_commercial',
            'contractor_email' => 'rajesh.contractor@buildconnect.demo',
            'title' => 'Electrician for Commercial Project',
            'trade_required' => 'Electrician',
            'description' => 'Commercial project requires skilled electricians for internal wiring and panels.',
            'pay_rate' => 26000.00,
            'pay_type' => 'daily',
            'location' => 'Surat',
            'city' => 'Surat',
            'state' => 'Gujarat',
            'spots_available' => 2,
            'spots_filled' => 1,
            'status' => 'open'
        ],
        [
            'key' => 'carpenter_job',
            'project_key' => 'green_heights',
            'contractor_email' => 'rajesh.contractor@buildconnect.demo',
            'title' => 'Senior Carpenter',
            'trade_required' => 'Carpenter',
            'description' => 'Senior carpenter needed for custom framing and interior woodwork.',
            'pay_rate' => 32000.00,
            'pay_type' => 'daily',
            'location' => 'Ahmedabad',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'spots_available' => 1,
            'spots_filled' => 1,
            'status' => 'open'
        ],
        [
            'key' => 'plumber_job',
            'project_key' => 'sunrise_villa',
            'contractor_email' => 'vikram.contractor@buildconnect.demo',
            'title' => 'Skilled Plumber Required',
            'trade_required' => 'Plumber',
            'description' => 'Plumber required for luxury villa piping, water lines and modern fittings.',
            'pay_rate' => 27000.00,
            'pay_type' => 'daily',
            'location' => 'Vadodara',
            'city' => 'Vadodara',
            'state' => 'Gujarat',
            'spots_available' => 2,
            'spots_filled' => 1,
            'status' => 'open'
        ]
    ];

    $jobMap = [];

    foreach ($jobsData as $j) {
        $pId = $projectMap[$j['project_key']];
        $cUserId = $userMap[$j['contractor_email']];

        $stmt = $db->prepare("SELECT id FROM jobs WHERE project_id = ? AND title = ?");
        $stmt->execute([$pId, $j['title']]);
        $jId = $stmt->fetchColumn();

        if ($jId) {
            $up = $db->prepare("UPDATE jobs SET trade_required = ?, description = ?, pay_rate = ?, pay_type = ?, location = ?, city = ?, state = ?, spots_available = ?, spots_filled = ?, status = ? WHERE id = ?");
            $up->execute([$j['trade_required'], $j['description'], $j['pay_rate'], $j['pay_type'], $j['location'], $j['city'], $j['state'], $j['spots_available'], $j['spots_filled'], $j['status'], $jId]);
            $jobMap[$j['key']] = $jId;
        } else {
            $ins = $db->prepare("INSERT INTO jobs (project_id, contractor_id, title, trade_required, description, pay_rate, pay_type, location, city, state, spots_available, spots_filled, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$pId, $cUserId, $j['title'], $j['trade_required'], $j['description'], $j['pay_rate'], $j['pay_type'], $j['location'], $j['city'], $j['state'], $j['spots_available'], $j['spots_filled'], $j['status']]);
            $jobMap[$j['key']] = $db->lastInsertId();
        }
    }
    echo "Jobs populated: " . count($jobMap) . "\n";

    // 9. Applications
    $applicationsData = [
        [
            'job_key' => 'mason_job',
            'worker_email' => 'amit.worker@buildconnect.demo',
            'status' => 'accepted',
            'match_score' => 96,
            'cover_note' => 'I have 6 years of masonry experience and specialize in residential foundation and brickwork.'
        ],
        [
            'job_key' => 'electrician_job',
            'worker_email' => 'ravi.worker@buildconnect.demo',
            'status' => 'shortlisted',
            'match_score' => 92,
            'cover_note' => 'Licensed electrician with 4 years commercial wiring experience in Surat.'
        ],
        [
            'job_key' => 'plumber_job',
            'worker_email' => 'dhruv.worker@buildconnect.demo',
            'status' => 'pending',
            'match_score' => 88,
            'cover_note' => 'Experienced plumber available for villa piping and sanitation work.'
        ],
        [
            'job_key' => 'carpenter_job',
            'worker_email' => 'rahul.worker@buildconnect.demo',
            'status' => 'accepted',
            'match_score' => 98,
            'cover_note' => '7 years experience in interior woodwork, furniture installation and doors.'
        ]
    ];

    $appMap = [];

    foreach ($applicationsData as $a) {
        $jId = $jobMap[$a['job_key']];
        $wUserId = $userMap[$a['worker_email']];

        $stmt = $db->prepare("SELECT id FROM job_applications WHERE job_id = ? AND worker_id = ?");
        $stmt->execute([$jId, $wUserId]);
        $aId = $stmt->fetchColumn();

        if ($aId) {
            $up = $db->prepare("UPDATE job_applications SET status = ?, match_score = ?, cover_note = ? WHERE id = ?");
            $up->execute([$a['status'], $a['match_score'], $a['cover_note'], $aId]);
            $appMap[$a['worker_email']] = $aId;
        } else {
            $ins = $db->prepare("INSERT INTO job_applications (job_id, worker_id, status, match_score, cover_note) VALUES (?, ?, ?, ?, ?)");
            $ins->execute([$jId, $wUserId, $a['status'], $a['match_score'], $a['cover_note']]);
            $appMap[$a['worker_email']] = $db->lastInsertId();
        }
    }
    echo "Applications populated.\n";

    // 10. Project Members
    $membersData = [
        ['project_key' => 'green_heights', 'worker_email' => 'amit.worker@buildconnect.demo', 'role' => 'Mason'],
        ['project_key' => 'green_heights', 'worker_email' => 'rahul.worker@buildconnect.demo', 'role' => 'Carpenter'],
        ['project_key' => 'techpark_commercial', 'worker_email' => 'ravi.worker@buildconnect.demo', 'role' => 'Electrician'],
        ['project_key' => 'sunrise_villa', 'worker_email' => 'dhruv.worker@buildconnect.demo', 'role' => 'Plumber']
    ];

    foreach ($membersData as $m) {
        $pId = $projectMap[$m['project_key']];
        $wUserId = $userMap[$m['worker_email']];

        $stmt = $db->prepare("INSERT IGNORE INTO project_members (project_id, user_id, role_in_project, status) VALUES (?, ?, ?, 'active')");
        $stmt->execute([$pId, $wUserId, $m['role']]);
    }
    echo "Project members populated.\n";

    // 11. Milestones
    $milestonesData = [
        ['project_key' => 'green_heights', 'title' => 'Foundation Work', 'progress_percent' => 100, 'status' => 'completed'],
        ['project_key' => 'green_heights', 'title' => 'Ground Floor Masonry', 'progress_percent' => 65, 'status' => 'in_progress'],
        ['project_key' => 'green_heights', 'title' => 'Roof Structure', 'progress_percent' => 0, 'status' => 'pending'],
        ['project_key' => 'techpark_commercial', 'title' => 'Site Preparation', 'progress_percent' => 100, 'status' => 'completed'],
        ['project_key' => 'techpark_commercial', 'title' => 'Electrical Work', 'progress_percent' => 45, 'status' => 'in_progress'],
        ['project_key' => 'sunrise_villa', 'title' => 'Foundation', 'progress_percent' => 100, 'status' => 'completed'],
        ['project_key' => 'sunrise_villa', 'title' => 'Plumbing Installation', 'progress_percent' => 70, 'status' => 'in_progress'],
        ['project_key' => 'sunrise_villa', 'title' => 'Interior Finishing', 'progress_percent' => 0, 'status' => 'pending']
    ];

    $milestoneMap = [];

    foreach ($milestonesData as $ms) {
        $pId = $projectMap[$ms['project_key']];
        $stmt = $db->prepare("SELECT id FROM milestones WHERE project_id = ? AND title = ?");
        $stmt->execute([$pId, $ms['title']]);
        $msId = $stmt->fetchColumn();

        if ($msId) {
            $up = $db->prepare("UPDATE milestones SET progress_percent = ?, status = ? WHERE id = ?");
            $up->execute([$ms['progress_percent'], $ms['status'], $msId]);
            $milestoneMap[$pId . '_' . $ms['title']] = $msId;
        } else {
            $ins = $db->prepare("INSERT INTO milestones (project_id, title, progress_percent, status) VALUES (?, ?, ?, ?)");
            $ins->execute([$pId, $ms['title'], $ms['progress_percent'], $ms['status']]);
            $milestoneMap[$pId . '_' . $ms['title']] = $db->lastInsertId();
        }
    }
    echo "Milestones populated.\n";

    // 12. Tasks
    $tasksData = [
        [
            'project_key' => 'green_heights',
            'milestone_title' => 'Foundation Work',
            'title' => 'Foundation Work',
            'status' => 'completed',
            'progress_percent' => 100,
            'worker_email' => null
        ],
        [
            'project_key' => 'green_heights',
            'milestone_title' => 'Ground Floor Masonry',
            'title' => 'Ground Floor Masonry',
            'status' => 'in_progress',
            'progress_percent' => 65,
            'worker_email' => 'amit.worker@buildconnect.demo'
        ],
        [
            'project_key' => 'green_heights',
            'milestone_title' => 'Roof Structure',
            'title' => 'Interior Carpentry',
            'status' => 'pending',
            'progress_percent' => 0,
            'worker_email' => 'rahul.worker@buildconnect.demo'
        ],
        [
            'project_key' => 'green_heights',
            'milestone_title' => 'Roof Structure',
            'title' => 'Roof Structure',
            'status' => 'pending',
            'progress_percent' => 0,
            'worker_email' => null
        ],
        [
            'project_key' => 'techpark_commercial',
            'milestone_title' => 'Site Preparation',
            'title' => 'Site Preparation',
            'status' => 'completed',
            'progress_percent' => 100,
            'worker_email' => null
        ],
        [
            'project_key' => 'techpark_commercial',
            'milestone_title' => 'Electrical Work',
            'title' => 'Electrical Wiring',
            'status' => 'in_progress',
            'progress_percent' => 45,
            'worker_email' => 'ravi.worker@buildconnect.demo'
        ],
        [
            'project_key' => 'techpark_commercial',
            'milestone_title' => 'Electrical Work',
            'title' => 'Final Electrical Inspection',
            'status' => 'pending',
            'progress_percent' => 0,
            'worker_email' => null
        ],
        [
            'project_key' => 'sunrise_villa',
            'milestone_title' => 'Foundation',
            'title' => 'Foundation',
            'status' => 'completed',
            'progress_percent' => 100,
            'worker_email' => null
        ],
        [
            'project_key' => 'sunrise_villa',
            'milestone_title' => 'Plumbing Installation',
            'title' => 'Plumbing Installation',
            'status' => 'in_progress',
            'progress_percent' => 70,
            'worker_email' => 'dhruv.worker@buildconnect.demo'
        ],
        [
            'project_key' => 'sunrise_villa',
            'milestone_title' => 'Interior Finishing',
            'title' => 'Interior Finishing',
            'status' => 'pending',
            'progress_percent' => 0,
            'worker_email' => null
        ]
    ];

    foreach ($tasksData as $t) {
        $pId = $projectMap[$t['project_key']];
        $msId = isset($milestoneMap[$pId . '_' . $t['milestone_title']]) ? $milestoneMap[$pId . '_' . $t['milestone_title']] : null;
        $wUserId = $t['worker_email'] ? $userMap[$t['worker_email']] : null;

        $stmt = $db->prepare("SELECT id FROM tasks WHERE project_id = ? AND title = ?");
        $stmt->execute([$pId, $t['title']]);
        $tId = $stmt->fetchColumn();

        if ($tId) {
            $up = $db->prepare("UPDATE tasks SET milestone_id = ?, assigned_to_worker_id = ?, status = ?, progress_percent = ? WHERE id = ?");
            $up->execute([$msId, $wUserId, $t['status'], $t['progress_percent'], $tId]);
        } else {
            $ins = $db->prepare("INSERT INTO tasks (project_id, milestone_id, title, assigned_to_worker_id, status, progress_percent) VALUES (?, ?, ?, ?, ?, ?)");
            $ins->execute([$pId, $msId, $t['title'], $wUserId, $t['status'], $t['progress_percent']]);
        }
    }
    echo "Tasks populated.\n";

    // 13. Attendance
    $workersAttendance = [
        ['worker' => 'amit.worker@buildconnect.demo', 'project' => 'green_heights', 'time_in' => '08:45:00', 'time_out' => '17:30:00'],
        ['worker' => 'rahul.worker@buildconnect.demo', 'project' => 'green_heights', 'time_in' => '09:00:00', 'time_out' => '17:15:00'],
        ['worker' => 'ravi.worker@buildconnect.demo', 'project' => 'techpark_commercial', 'time_in' => '08:50:00', 'time_out' => '17:40:00'],
        ['worker' => 'dhruv.worker@buildconnect.demo', 'project' => 'sunrise_villa', 'time_in' => '08:55:00', 'time_out' => '17:25:00']
    ];

    for ($dayOffset = 0; $dayOffset < 15; $dayOffset++) {
        $dateStr = date('Y-m-d', strtotime("-$dayOffset days"));
        if (date('N', strtotime($dateStr)) == 7) {
            continue; // skip Sundays
        }

        foreach ($workersAttendance as $att) {
            $wUserId = $userMap[$att['worker']];
            $pId = $projectMap[$att['project']];

            $checkIn = "$dateStr " . $att['time_in'];
            $checkOut = "$dateStr " . $att['time_out'];
            $hours = 8.5;

            $stmt = $db->prepare("INSERT INTO attendance (project_id, worker_id, attendance_date, check_in_time, check_out_time, hours_worked, verified_by_qr, status) 
                                 VALUES (?, ?, ?, ?, ?, ?, 1, 'present') 
                                 ON DUPLICATE KEY UPDATE check_in_time = VALUES(check_in_time), check_out_time = VALUES(check_out_time), status = 'present'");
            $stmt->execute([$pId, $wUserId, $dateStr, $checkIn, $checkOut, $hours]);
        }
    }
    echo "Attendance populated.\n";

    // 14. Contracts
    $contractsData = [
        [
            'project_key' => 'green_heights',
            'job_key' => 'mason_job',
            'contractor_email' => 'rajesh.contractor@buildconnect.demo',
            'worker_email' => 'amit.worker@buildconnect.demo',
            'title' => 'Masonry Services Contract - Green Heights',
            'terms' => 'Standard masonry construction terms for ground floor masonry and foundation work.',
            'pay_amount' => 30000.00,
            'status' => 'active'
        ],
        [
            'project_key' => 'green_heights',
            'job_key' => 'carpenter_job',
            'contractor_email' => 'rajesh.contractor@buildconnect.demo',
            'worker_email' => 'rahul.worker@buildconnect.demo',
            'title' => 'Carpentry Services Contract - Green Heights',
            'terms' => 'Standard carpentry agreement for interior framing and door installation.',
            'pay_amount' => 32000.00,
            'status' => 'active'
        ],
        [
            'project_key' => 'techpark_commercial',
            'job_key' => 'electrician_job',
            'contractor_email' => 'rajesh.contractor@buildconnect.demo',
            'worker_email' => 'ravi.worker@buildconnect.demo',
            'title' => 'Electrical Wiring Contract - TechPark',
            'terms' => 'Commercial electrical installation terms and compliance with local electrical safety norms.',
            'pay_amount' => 28000.00,
            'status' => 'active'
        ],
        [
            'project_key' => 'sunrise_villa',
            'job_key' => 'plumber_job',
            'contractor_email' => 'vikram.contractor@buildconnect.demo',
            'worker_email' => 'dhruv.worker@buildconnect.demo',
            'title' => 'Plumbing Contract - Sunrise Villa',
            'terms' => 'Villa plumbing, water pipeline and modern fixture fitting contract terms.',
            'pay_amount' => 27000.00,
            'status' => 'active'
        ]
    ];

    $contractMap = [];

    foreach ($contractsData as $c) {
        $pId = $projectMap[$c['project_key']];
        $jId = $jobMap[$c['job_key']];
        $cUserId = $userMap[$c['contractor_email']];
        $wUserId = $userMap[$c['worker_email']];
        $aId = isset($appMap[$c['worker_email']]) ? $appMap[$c['worker_email']] : null;

        $stmt = $db->prepare("SELECT id FROM contracts WHERE project_id = ? AND worker_id = ?");
        $stmt->execute([$pId, $wUserId]);
        $cId = $stmt->fetchColumn();

        if ($cId) {
            $up = $db->prepare("UPDATE contracts SET title = ?, terms = ?, payment_amount = ?, pay_amount = ?, status = ? WHERE id = ?");
            $up->execute([$c['title'], $c['terms'], $c['pay_amount'], $c['pay_amount'], $c['status'], $cId]);
            $contractMap[$c['worker_email']] = $cId;
        } else {
            $ins = $db->prepare("INSERT INTO contracts (project_id, job_id, job_application_id, contractor_id, worker_id, contract_number, title, terms, payment_amount, pay_amount, status, signed_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $contractNo = 'BC-CNT-' . strtoupper(substr(md5(uniqid()), 0, 8));
            $ins->execute([$pId, $jId, $aId, $cUserId, $wUserId, $contractNo, $c['title'], $c['terms'], $c['pay_amount'], $c['pay_amount'], $c['status']]);
            $contractMap[$c['worker_email']] = $db->lastInsertId();
        }
    }
    echo "Contracts populated.\n";

    // 15. Payments
    $paymentsData = [
        [
            'worker_email' => 'amit.worker@buildconnect.demo',
            'contractor_email' => 'rajesh.contractor@buildconnect.demo',
            'project_key' => 'green_heights',
            'amount' => 30000.00,
            'status' => 'completed'
        ],
        [
            'worker_email' => 'rahul.worker@buildconnect.demo',
            'contractor_email' => 'rajesh.contractor@buildconnect.demo',
            'project_key' => 'green_heights',
            'amount' => 32000.00,
            'status' => 'pending'
        ],
        [
            'worker_email' => 'ravi.worker@buildconnect.demo',
            'contractor_email' => 'rajesh.contractor@buildconnect.demo',
            'project_key' => 'techpark_commercial',
            'amount' => 28000.00,
            'status' => 'completed'
        ]
    ];

    foreach ($paymentsData as $p) {
        $pId = $projectMap[$p['project_key']];
        $payerId = $userMap[$p['contractor_email']];
        $payeeId = $userMap[$p['worker_email']];
        $cntId = isset($contractMap[$p['worker_email']]) ? $contractMap[$p['worker_email']] : null;

        $stmt = $db->prepare("SELECT id FROM payments WHERE project_id = ? AND payee_id = ?");
        $stmt->execute([$pId, $payeeId]);
        if (!$stmt->fetchColumn()) {
            $ins = $db->prepare("INSERT INTO payments (contract_id, project_id, payer_id, payee_id, amount, status) VALUES (?, ?, ?, ?, ?, ?)");
            $ins->execute([$cntId, $pId, $payerId, $payeeId, $p['amount'], $p['status']]);
        }
    }
    echo "Payments populated.\n";

    // 16. Reviews
    $reviewsData = [
        [
            'reviewer_email' => 'karan.client@buildconnect.demo',
            'reviewee_email' => 'rajesh.contractor@buildconnect.demo',
            'project_key' => 'green_heights',
            'rating' => 5,
            'review_text' => 'Excellent project coordination and regular progress updates.'
        ],
        [
            'reviewer_email' => 'neha.client@buildconnect.demo',
            'reviewee_email' => 'rajesh.contractor@buildconnect.demo',
            'project_key' => 'techpark_commercial',
            'rating' => 4,
            'review_text' => 'Good communication and professional project management.'
        ],
        [
            'reviewer_email' => 'karan.client@buildconnect.demo',
            'reviewee_email' => 'vikram.contractor@buildconnect.demo',
            'project_key' => 'sunrise_villa',
            'rating' => 5,
            'review_text' => 'Quality work and good workforce coordination.'
        ]
    ];

    foreach ($reviewsData as $r) {
        $rId = $userMap[$r['reviewer_email']];
        $eId = $userMap[$r['reviewee_email']];
        $pId = $projectMap[$r['project_key']];

        $stmt = $db->prepare("SELECT id FROM reviews WHERE reviewer_id = ? AND reviewee_id = ? AND project_id = ?");
        $stmt->execute([$rId, $eId, $pId]);
        if (!$stmt->fetchColumn()) {
            $ins = $db->prepare("INSERT INTO reviews (project_id, reviewer_id, reviewee_id, rating, review_text, status) VALUES (?, ?, ?, ?, ?, 'published')");
            $ins->execute([$pId, $rId, $eId, $r['rating'], $r['review_text']]);
        }
    }
    echo "Reviews populated.\n";

    // 17. Notifications
    $notificationsData = [
        [
            'user_email' => 'amit.worker@buildconnect.demo',
            'title' => 'Application Accepted',
            'message' => 'Your application for Experienced Mason Required was accepted.',
            'type' => 'JOB_APPLICATION'
        ],
        [
            'user_email' => 'amit.worker@buildconnect.demo',
            'title' => 'Task Assigned',
            'message' => 'You have been assigned Ground Floor Masonry.',
            'type' => 'TASK_ASSIGNMENT'
        ],
        [
            'user_email' => 'amit.worker@buildconnect.demo',
            'title' => 'Attendance Verified',
            'message' => 'Your attendance was recorded successfully.',
            'type' => 'ATTENDANCE'
        ],
        [
            'user_email' => 'amit.worker@buildconnect.demo',
            'title' => 'Project Update',
            'message' => 'Green Heights Residency project progress updated to 42%.',
            'type' => 'PROJECT_UPDATE'
        ],
        [
            'user_email' => 'ravi.worker@buildconnect.demo',
            'title' => 'Application Shortlisted',
            'message' => 'Your application has been shortlisted.',
            'type' => 'JOB_APPLICATION'
        ],
        [
            'user_email' => 'rahul.worker@buildconnect.demo',
            'title' => 'Project Assigned',
            'message' => 'You have been assigned to Green Heights Residency.',
            'type' => 'PROJECT_ASSIGNMENT'
        ],
        [
            'user_email' => 'karan.client@buildconnect.demo',
            'title' => 'Project Progress',
            'message' => 'Green Heights Residency is currently 42% complete.',
            'type' => 'PROJECT_PROGRESS'
        ],
        [
            'user_email' => 'karan.client@buildconnect.demo',
            'title' => 'Project Progress',
            'message' => 'Sunrise Villa Project has reached 67% completion.',
            'type' => 'PROJECT_PROGRESS'
        ],
        [
            'user_email' => 'karan.client@buildconnect.demo',
            'title' => 'Milestone Update',
            'message' => 'New milestone update available for Green Heights Residency.',
            'type' => 'MILESTONE_UPDATE'
        ],
        [
            'user_email' => 'neha.client@buildconnect.demo',
            'title' => 'Project Progress',
            'message' => 'TechPark Commercial Complex is now 28% complete.',
            'type' => 'PROJECT_PROGRESS'
        ],
        [
            'user_email' => 'rajesh.contractor@buildconnect.demo',
            'title' => 'New Application',
            'message' => 'New application received for Experienced Mason Required.',
            'type' => 'JOB_APPLICATION'
        ],
        [
            'user_email' => 'rajesh.contractor@buildconnect.demo',
            'title' => 'Task Progress Update',
            'message' => 'Ground Floor Masonry task is currently 65% complete.',
            'type' => 'TASK_UPDATE'
        ]
    ];

    foreach ($notificationsData as $n) {
        $uId = $userMap[$n['user_email']];
        $stmt = $db->prepare("SELECT id FROM notifications WHERE user_id = ? AND message = ?");
        $stmt->execute([$uId, $n['message']]);
        if (!$stmt->fetchColumn()) {
            $ins = $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, ?, 0)");
            $ins->execute([$uId, $n['title'], $n['message'], $n['type']]);
        }
    }
    echo "Notifications populated.\n";

    // 18. Project Documents
    $documentsData = [
        ['project_key' => 'green_heights', 'user_email' => 'karan.client@buildconnect.demo', 'title' => 'Project Plan', 'file_path' => '/uploads/docs/green_heights_plan.pdf'],
        ['project_key' => 'green_heights', 'user_email' => 'rajesh.contractor@buildconnect.demo', 'title' => 'Progress Report', 'file_path' => '/uploads/docs/green_heights_progress.pdf'],
        ['project_key' => 'green_heights', 'user_email' => 'rajesh.contractor@buildconnect.demo', 'title' => 'Site Update', 'file_path' => '/uploads/docs/green_heights_site.pdf'],
        ['project_key' => 'sunrise_villa', 'user_email' => 'karan.client@buildconnect.demo', 'title' => 'Project Plan', 'file_path' => '/uploads/docs/sunrise_villa_plan.pdf'],
        ['project_key' => 'sunrise_villa', 'user_email' => 'vikram.contractor@buildconnect.demo', 'title' => 'Construction Report', 'file_path' => '/uploads/docs/sunrise_villa_report.pdf'],
        ['project_key' => 'sunrise_villa', 'user_email' => 'vikram.contractor@buildconnect.demo', 'title' => 'Milestone Report', 'file_path' => '/uploads/docs/sunrise_villa_milestones.pdf']
    ];

    foreach ($documentsData as $doc) {
        $pId = $projectMap[$doc['project_key']];
        $uId = $userMap[$doc['user_email']];

        $stmt = $db->prepare("SELECT id FROM project_documents WHERE project_id = ? AND title = ?");
        $stmt->execute([$pId, $doc['title']]);
        if (!$stmt->fetchColumn()) {
            $ins = $db->prepare("INSERT INTO project_documents (project_id, uploaded_by_user_id, title, file_path) VALUES (?, ?, ?, ?)");
            $ins->execute([$pId, $uId, $doc['title'], $doc['file_path']]);
        }
    }
    echo "Project documents populated.\n";

    echo "\nSEED SUCCESSFUL! All BuildConnect demo dataset records successfully inserted/verified.\n";

} catch (Exception $e) {
    echo "ERROR during seed execution: " . $e->getMessage() . "\n";
    exit(1);
}
