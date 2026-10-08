-- BuildConnect Presentation Demo Data (Idempotent Seed File)
-- All demo users use the default demo password: password123 (bcrypt hash: $2y$10$7R8222d3Z.s5G9ZpXgOQ.e.m8V8U.w/g5p.92Z1.a2.X78q9)

-- 1. DEMO USERS
INSERT INTO users (id, name, email, password, role, phone, avatar, status) VALUES
(100, 'Admin User', 'admin@buildconnect.demo', '$2y$10$7R8222d3Z.s5G9ZpXgOQ.e.m8V8U.w/g5p.92Z1.a2.X78q9', 'admin', '+91 98765 00001', 'admin.png', 'active'),
(101, 'Rajesh Construction', 'contractor@buildconnect.demo', '$2y$10$7R8222d3Z.s5G9ZpXgOQ.e.m8V8U.w/g5p.92Z1.a2.X78q9', 'contractor', '+91 98765 00002', 'contractor.png', 'active'),
(102, 'Amit Patel', 'worker1@buildconnect.demo', '$2y$10$7R8222d3Z.s5G9ZpXgOQ.e.m8V8U.w/g5p.92Z1.a2.X78q9', 'worker', '+91 98765 00003', 'worker1.png', 'active'),
(103, 'Ravi Kumar', 'worker2@buildconnect.demo', '$2y$10$7R8222d3Z.s5G9ZpXgOQ.e.m8V8U.w/g5p.92Z1.a2.X78q9', 'worker', '+91 98765 00004', 'worker2.png', 'active'),
(104, 'Karan Dangi', 'client@buildconnect.demo', '$2y$10$7R8222d3Z.s5G9ZpXgOQ.e.m8V8U.w/g5p.92Z1.a2.X78q9', 'client', '+91 98765 00005', 'client.png', 'active')
ON DUPLICATE KEY UPDATE 
    name = VALUES(name),
    password = VALUES(password),
    role = VALUES(role),
    phone = VALUES(phone),
    status = VALUES(status);

-- 2. ROLE PROFILES
INSERT INTO contractors (id, user_id, company_name, license_no, company_address, company_description, city, state, website, rating_avg) VALUES
(100, 101, 'Rajesh BuildTech Pvt. Ltd.', 'GJ-LIC-100201', 'SG Highway, Bodakdev', 'Premier infrastructure and commercial development firm based in Gujarat.', 'Ahmedabad', 'Gujarat', 'https://rajeshbuildtech.demo', 4.85)
ON DUPLICATE KEY UPDATE 
    company_name = VALUES(company_name),
    city = VALUES(city),
    state = VALUES(state),
    rating_avg = VALUES(rating_avg);

INSERT INTO workers (id, user_id, trade_title, experience_years, hourly_rate, daily_rate, bio, city, state, address, availability_status, preferred_work_type, verification_status, rating_avg, reviews_count) VALUES
(100, 102, 'Mason', 6, 40.00, 320.00, 'Senior Mason specialized in reinforced concrete formwork, bricklaying, and foundation pour.', 'Ahmedabad', 'Gujarat', 'SG Highway, Bodakdev', 'available', 'Full-Time', 'approved', 5.00, 1),
(101, 103, 'Electrician', 4, 35.00, 280.00, 'Licensed Industrial Electrician specialized in 3-phase commercial panel distribution and wiring.', 'Surat', 'Gujarat', 'Ring Road, Surat', 'available', 'Full-Time', 'approved', 4.80, 1)
ON DUPLICATE KEY UPDATE 
    trade_title = VALUES(trade_title),
    experience_years = VALUES(experience_years),
    availability_status = VALUES(availability_status),
    verification_status = VALUES(verification_status),
    rating_avg = VALUES(rating_avg);

INSERT INTO clients (id, user_id, company_name, client_type, address, city, state) VALUES
(100, 104, 'Dangi Infrastructure', 'Real Estate Developer', 'SG Highway, Bodakdev', 'Ahmedabad', 'Gujarat')
ON DUPLICATE KEY UPDATE 
    company_name = VALUES(company_name),
    client_type = VALUES(client_type),
    city = VALUES(city),
    state = VALUES(state);

-- 3. PROJECTS
INSERT INTO projects (id, contractor_id, client_id, title, description, location, address, city, state, postal_code, location_lat, location_lng, budget, start_date, end_date, status, progress_percent, qr_code_token) VALUES
(1001, 101, 104, 'Green Heights Residency', 'Luxury residential high-rise complex featuring 120 residential apartments, subterranean parking, and landscaped garden.', 'SG Highway, Bodakdev, Ahmedabad, Gujarat', 'SG Highway, Bodakdev', 'Ahmedabad', 'Gujarat', '380054', 23.0225000, 72.5714000, 12500000.00, '2026-09-01', '2027-06-30', 'in_progress', 42, 'BC-DEMO-GREEN-HEIGHTS-01'),
(1002, 101, 104, 'TechPark Commercial Complex', 'Modern IT technology park development with subterranean parking, solar energy roof grid, and commercial office spaces.', 'Ring Road, Surat, Gujarat', 'Ring Road, Surat', 'Surat', 'Gujarat', '395002', 21.1702000, 72.8311000, 24000000.00, '2026-08-15', '2027-12-31', 'in_progress', 28, 'BC-DEMO-TECHPARK-SURAT-02')
ON DUPLICATE KEY UPDATE 
    title = VALUES(title),
    description = VALUES(description),
    budget = VALUES(budget),
    status = VALUES(status),
    progress_percent = VALUES(progress_percent);

-- 4. JOBS
INSERT INTO jobs (id, project_id, contractor_id, title, trade_required, description, pay_rate, pay_type, location, city, state, spots_available, spots_filled, status) VALUES
(1001, 1001, 101, 'Experienced Mason Required', 'Mason', 'Experienced Mason needed for structural brickwork, rebar layout, and foundation concrete pour.', 1066.00, 'daily', 'Ahmedabad, Gujarat', 'Ahmedabad', 'Gujarat', 3, 1, 'open'),
(1002, 1002, 101, 'Electrician – Commercial Project', 'Electrician', 'Commercial Electrician for high-voltage panel wiring, conduit routing, and 3-phase distribution transformer setup.', 1000.00, 'daily', 'Surat, Gujarat', 'Surat', 'Gujarat', 2, 0, 'open')
ON DUPLICATE KEY UPDATE 
    title = VALUES(title),
    spots_available = VALUES(spots_available),
    status = VALUES(status);

-- 5. JOB APPLICATIONS
INSERT INTO job_applications (id, job_id, worker_id, match_score, cover_note, expected_pay, status) VALUES
(1001, 1001, 102, 95, 'Certified Mason with 6 years experience in residential towers.', 1066.00, 'accepted'),
(1002, 1002, 103, 90, 'Licensed Industrial Electrician with 4 years commercial experience.', 1000.00, 'shortlisted'),
(1003, 1002, 102, 75, 'Interested in assisting with site electrical conduit installation.', 1000.00, 'pending')
ON DUPLICATE KEY UPDATE 
    status = VALUES(status),
    match_score = VALUES(match_score);

-- 6. PROJECT MEMBERS
INSERT INTO project_members (id, project_id, user_id, role_in_project, status) VALUES
(1001, 1001, 102, 'Mason', 'active'),
(1002, 1002, 103, 'Electrician', 'active')
ON DUPLICATE KEY UPDATE 
    role_in_project = VALUES(role_in_project),
    status = VALUES(status);

-- 7. MILESTONES
INSERT INTO milestones (id, project_id, title, description, target_date, amount, progress_percent, status, completed_at) VALUES
(1001, 1001, 'Foundation Completed', 'Concrete foundation slab pour completed and inspected', '2026-09-30', 2500000.00, 100, 'completed', '2026-09-30 17:00:00'),
(1002, 1001, 'Ground Floor Completed', 'Ground floor masonry walls and support columns completed', '2026-11-15', 3000000.00, 60, 'in_progress', NULL),
(1003, 1001, 'First Floor Structure', 'First floor slab casting and pillar reinforcement', '2027-01-20', 3500000.00, 0, 'pending', NULL),
(1004, 1001, 'Electrical Installation', 'Complete building wiring, panels, and transformer hookup', '2027-04-10', 3500000.00, 0, 'pending', NULL)
ON DUPLICATE KEY UPDATE 
    title = VALUES(title),
    progress_percent = VALUES(progress_percent),
    status = VALUES(status);

-- 8. TASKS
INSERT INTO tasks (id, project_id, milestone_id, title, description, assigned_to_worker_id, priority, status, progress_percent, start_date, due_date, completed_at) VALUES
(1001, 1001, 1001, 'Foundation work', 'Pouring heavy foundation slab and pile cap reinforcement', 102, 'high', 'completed', 100, '2026-09-01', '2026-09-30', '2026-09-30 17:00:00'),
(1002, 1001, 1002, 'Ground floor masonry', 'Structural brick laying and mortar formwork for ground floor walls', 102, 'high', 'in_progress', 65, '2026-10-01', '2026-11-15', NULL),
(1003, 1001, 1004, 'Electrical installation', 'Conduit laying and junction box wiring for basement levels', NULL, 'medium', 'pending', 0, '2026-11-16', '2027-01-15', NULL),
(1004, 1001, NULL, 'Plumbing', 'Main riser pipe fitting and drainage connection setup', NULL, 'medium', 'pending', 0, '2027-01-16', '2027-03-01', NULL),
(1005, 1002, NULL, 'Site preparation', 'Excavation, land grading, and utility connection setup', 103, 'high', 'completed', 100, '2026-08-15', '2026-09-15', '2026-09-15 16:30:00'),
(1006, 1002, NULL, 'Structural work', 'Subterranean pillar rebar layout and basement wall pouring', 103, 'high', 'in_progress', 40, '2026-09-16', '2026-11-30', NULL)
ON DUPLICATE KEY UPDATE 
    title = VALUES(title),
    status = VALUES(status),
    progress_percent = VALUES(progress_percent);

-- 9. ATTENDANCE SESSIONS & RECORDS
INSERT INTO attendance_sessions (id, project_id, created_by, token, expires_at, status) VALUES
(1001, 1001, 101, 'BC-DEMO-GREEN-HEIGHTS-01', '2026-10-31 23:59:59', 'active'),
(1002, 1002, 101, 'BC-DEMO-TECHPARK-SURAT-02', '2026-10-31 23:59:59', 'active')
ON DUPLICATE KEY UPDATE status = VALUES(status);

INSERT INTO attendance (id, project_id, worker_id, attendance_date, check_in_time, check_out_time, hours_worked, verified_by_qr, qr_session_id, status) VALUES
(1001, 1001, 102, '2026-10-07', '2026-10-07 08:45:00', '2026-10-07 17:30:00', 8.75, 1, 1001, 'present'),
(1002, 1001, 102, '2026-10-08', '2026-10-08 08:38:00', '2026-10-08 17:42:00', 9.07, 1, 1001, 'present'),
(1003, 1001, 102, '2026-10-06', '2026-10-06 08:50:00', '2026-10-06 17:30:00', 8.67, 1, 1001, 'present'),
(1004, 1002, 103, '2026-10-08', '2026-10-08 09:02:00', '2026-10-08 17:15:00', 8.22, 1, 1002, 'present'),
(1005, 1002, 103, '2026-10-07', '2026-10-07 08:55:00', '2026-10-07 17:20:00', 8.42, 1, 1002, 'present'),
(1006, 1002, 103, '2026-10-06', '2026-10-06 09:00:00', '2026-10-06 17:30:00', 8.50, 1, 1002, 'present')
ON DUPLICATE KEY UPDATE 
    hours_worked = VALUES(hours_worked),
    status = VALUES(status);

-- 10. CONTRACTS
INSERT INTO contracts (id, project_id, job_id, job_application_id, contractor_id, worker_id, contract_number, title, terms, payment_type, payment_amount, pay_amount, start_date, end_date, status, signed_at) VALUES
(1001, 1001, 1001, 1001, 101, 102, 'BC-CTR-2026-001', 'Masonry Contract - Green Heights', 'Standard daily mason contract terms. Working hours: 8.5 hours/day.', 'daily', 1066.00, 1066.00, '2026-09-01', '2027-06-30', 'active', '2026-09-02 10:00:00'),
(1002, 1002, 1002, 1002, 101, 103, 'BC-CTR-2026-002', 'Electrical Contract - TechPark', 'Commercial electrician installation terms. Working hours: 8 hours/day.', 'daily', 1000.00, 1000.00, '2026-09-15', '2027-12-31', 'active', '2026-09-16 11:30:00')
ON DUPLICATE KEY UPDATE 
    status = VALUES(status),
    signed_at = VALUES(signed_at);

-- 11. REVIEWS
INSERT INTO reviews (id, project_id, contract_id, reviewer_id, reviewee_id, rating, review_text, status) VALUES
(1001, 1001, 1001, 101, 102, 5, 'Excellent masonry work and good project discipline.', 'published'),
(1002, 1001, 1001, 104, 101, 5, 'Good project coordination and regular progress updates.', 'published')
ON DUPLICATE KEY UPDATE 
    rating = VALUES(rating),
    review_text = VALUES(review_text);

-- 12. NOTIFICATIONS
INSERT INTO notifications (id, user_id, title, message, type, link, is_read) VALUES
(1001, 101, 'New Job Application', 'Amit Patel applied for Experienced Mason Required', 'JOB', '/contractor/applications.php', 0),
(1002, 103, 'Application Shortlisted', 'Your application for Electrician – Commercial Project has been shortlisted', 'JOB', '/worker/applications.php', 0),
(1003, 102, 'New Task Assigned', 'New task assigned: Ground floor masonry', 'TASK', '/worker/tasks.php', 0),
(1004, 104, 'Milestone Completed', 'Milestone Foundation Completed has been completed', 'MILESTONE', '/client/milestones.php', 0),
(1005, 102, 'Contract Issued', 'Contract requires your review and digital signature', 'CONTRACT', '/worker/contracts.php', 0),
(1006, 104, 'Project Progress Updated', 'Green Heights Residency overall progress updated to 42%', 'PROJECT', '/client/projects.php', 0)
ON DUPLICATE KEY UPDATE 
    message = VALUES(message),
    is_read = VALUES(is_read);
