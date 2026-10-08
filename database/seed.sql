-- BuildConnect Development Seed Data (MySQL)

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE activity_logs;
TRUNCATE TABLE project_documents;
TRUNCATE TABLE messages;
TRUNCATE TABLE notifications;
TRUNCATE TABLE review_reports;
TRUNCATE TABLE reviews;
TRUNCATE TABLE payments;
TRUNCATE TABLE contracts;
TRUNCATE TABLE attendance;
TRUNCATE TABLE attendance_sessions;
TRUNCATE TABLE milestones;
TRUNCATE TABLE tasks;
TRUNCATE TABLE job_applications;
TRUNCATE TABLE jobs;
TRUNCATE TABLE project_members;
TRUNCATE TABLE projects;
TRUNCATE TABLE worker_experience;
TRUNCATE TABLE worker_documents;
TRUNCATE TABLE worker_skills;
TRUNCATE TABLE skills;
TRUNCATE TABLE clients;
TRUNCATE TABLE contractors;
TRUNCATE TABLE workers;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

-- Demo Password for all development accounts: password123
-- Secure bcrypt hash: $2y$10$7R8222d3Z.s5G9ZpXgOQ.e.m8V8U.w/g5p.92Z1.a2.X78q9
-- Demo Password for all development accounts: password123
-- Secure bcrypt hash: $2y$10$7R8222d3Z.s5G9ZpXgOQ.e.m8V8U.w/g5p.92Z1.a2.X78q9
INSERT INTO users (id, name, email, password, role, phone, avatar, status) VALUES
(1, 'System Administrator (Demo)', 'admin@buildconnect.com', '$2y$10$7R8222d3Z.s5G9ZpXgOQ.e.m8V8U.w/g5p.92Z1.a2.X78q9', 'admin', '+91 98765 43210', 'admin.png', 'active'),
(2, 'Apex Builders Inc. (Demo)', 'contractor@buildconnect.com', '$2y$10$7R8222d3Z.s5G9ZpXgOQ.e.m8V8U.w/g5p.92Z1.a2.X78q9', 'contractor', '+91 98765 43211', 'contractor.png', 'active'),
(3, 'Marcus Vance (Demo)', 'worker@buildconnect.com', '$2y$10$7R8222d3Z.s5G9ZpXgOQ.e.m8V8U.w/g5p.92Z1.a2.X78q9', 'worker', '+91 98765 43212', 'worker1.png', 'active'),
(4, 'Skyline Urban Developers (Demo)', 'client@buildconnect.com', '$2y$10$7R8222d3Z.s5G9ZpXgOQ.e.m8V8U.w/g5p.92Z1.a2.X78q9', 'client', '+91 98765 43213', 'client.png', 'active'),
(5, 'Elena Rostova (Demo)', 'worker2@buildconnect.com', '$2y$10$7R8222d3Z.s5G9ZpXgOQ.e.m8V8U.w/g5p.92Z1.a2.X78q9', 'worker', '+91 98765 43214', 'worker2.png', 'active'),
(6, 'Vanguard Infra Systems (Demo)', 'contractor2@buildconnect.com', '$2y$10$7R8222d3Z.s5G9ZpXgOQ.e.m8V8U.w/g5p.92Z1.a2.X78q9', 'contractor', '+91 98765 43215', 'contractor.png', 'active'),
(7, 'Apex Horizon Realty (Demo)', 'client2@buildconnect.com', '$2y$10$7R8222d3Z.s5G9ZpXgOQ.e.m8V8U.w/g5p.92Z1.a2.X78q9', 'client', '+91 98765 43216', 'client.png', 'active'),
(8, 'David Miller (Demo)', 'worker3@buildconnect.com', '$2y$10$7R8222d3Z.s5G9ZpXgOQ.e.m8V8U.w/g5p.92Z1.a2.X78q9', 'worker', '+91 98765 43217', 'worker3.png', 'active');

-- Worker Profiles
INSERT INTO workers (id, user_id, trade_title, experience_years, hourly_rate, daily_rate, bio, city, state, address, availability_status, preferred_work_type, preferred_location, available_from_date, verification_status, rating_avg, reviews_count) VALUES
(1, 3, 'Structural Welder & Crane Operator', 8, 45.00, 360.00, 'Certified Master Crane Operator & Senior Structural Welder with 8+ years experience in commercial steel frame erection.', 'Ahmedabad', 'Gujarat', 'Bodakdev, SG Highway', 'available', 'Full-Time', 'Ahmedabad / Gujarat', '2026-01-01', 'approved', 4.95, 24),
(2, 5, 'Commercial Electrician', 5, 40.00, 320.00, 'Licensed Industrial Electrician specializing in commercial solar installation and high voltage distribution panels.', 'Ahmedabad', 'Gujarat', 'Navrangpura', 'partially_available', 'Contract', 'Ahmedabad', '2026-02-15', 'pending', 4.80, 12),
(3, 8, 'Concrete & Masonry Specialist', 6, 38.00, 300.00, 'Specialized in high-strength foundation pour, rebar layout, and masonry formwork for commercial towers.', 'Ahmedabad', 'Gujarat', 'Changodar', 'available', 'Full-Time', 'Ahmedabad', '2026-01-10', 'approved', 4.85, 18);

-- Worker Experience Seed Data
INSERT INTO worker_experience (id, worker_id, job_title, company_name, description, start_date, end_date, is_current) VALUES
(1, 1, 'Senior Structural Welder', 'Gujarat Infra Steel Corp', 'Lead welder on heavy structural steel beams and high-altitude joints.', '2021-03-01', '2025-11-30', 0),
(2, 1, 'Master Crane Operator', 'Metropolis Infra Ltd', 'Operated tower cranes for 20+ story commercial developments.', '2025-12-01', NULL, 1),
(3, 2, 'Solar Substation Electrician', 'SunPower Grids', 'Installed commercial inverter systems and 3-phase transformers.', '2022-05-15', '2025-10-10', 0);

-- Worker Documents Seed Data
INSERT INTO worker_documents (id, worker_id, document_type, file_path, file_name, status) VALUES
(1, 1, 'Skill Certificate', 'uploads/documents/cert_welding_mance.pdf', 'AWS_Master_Welder_Certificate.pdf', 'approved'),
(2, 1, 'Identity Verification', 'uploads/documents/id_marcus_vance.png', 'Govt_ID_Verification.png', 'approved'),
(3, 2, 'Training Certificate', 'uploads/documents/cert_elena_elec.pdf', 'Electrical_Safety_License.pdf', 'pending');

-- Contractor Profiles
INSERT INTO contractors (id, user_id, company_name, license_no, company_address, company_description, city, state, website, rating_avg) VALUES
(1, 2, 'Apex Builders Inc.', 'GJ-LIC-984210', 'SG Highway, Bodakdev', 'Premier commercial & residential infrastructure construction firm based in Gujarat.', 'Ahmedabad', 'Gujarat', 'https://apexbuilders.example.com', 4.90),
(2, 6, 'Vanguard Infra Systems', 'GJ-LIC-441209', 'CG Road, Navrangpura', 'Industrial warehousing and infrastructure contractor.', 'Ahmedabad', 'Gujarat', 'https://vanguardinfra.example.com', 4.80);

-- Client Profiles
INSERT INTO clients (id, user_id, company_name, client_type, address, city, state) VALUES
(1, 4, 'Skyline Urban Developers', 'Commercial Real Estate Developer', 'Prahlad Nagar, SG Highway', 'Ahmedabad', 'Gujarat'),
(2, 7, 'Apex Horizon Realty', 'Residential Property Group', 'Drive-In Road, Thaltej', 'Ahmedabad', 'Gujarat');

-- Skills Dictionary
INSERT INTO skills (id, name, category) VALUES
(1, 'Structural Welding', 'Welding'),
(2, 'Tower Crane Operation', 'Heavy Machinery'),
(3, 'Commercial Electrical Wiring', 'Electrical'),
(4, 'Concrete Formwork & Masonry', 'Masonry'),
(5, 'Plumbing & High Pressure Piping', 'Plumbing'),
(6, 'Carpentry & Framing', 'Carpentry');

-- Worker Skills Mapping
INSERT INTO worker_skills (worker_id, skill_id, proficiency_level) VALUES
(1, 1, 'expert'),
(1, 2, 'expert'),
(2, 3, 'advanced');

-- Sample Projects (Ahmedabad & Gandhinagar, Gujarat)
-- Client A (User 4) owns Project 1 and Project 2. Client B (User 7) owns Project 3.
INSERT INTO projects (id, contractor_id, client_id, title, description, location, address, city, state, postal_code, location_lat, location_lng, budget, start_date, end_date, status, progress_percent, qr_code_token) VALUES
(1, 2, 4, 'Metropolis Commercial Tower - Phase II', 'Construction of 24-story commercial office building including foundation reinforcement, structural steel frame, and HVAC.', 'SG Highway, Bodakdev, Ahmedabad, Gujarat, India', 'SG Highway, Bodakdev', 'Ahmedabad', 'Gujarat', '380054', 23.0225000, 72.5714000, 4500000.00, '2026-01-15', '2026-12-20', 'in_progress', 65, 'BC-PROJ-AHMEDABAD-X892'),
(2, 2, 4, 'Skyline IT Park - Block B', 'Development of 10-story software technology park with subterranean parking structure.', 'Prahlad Nagar, Ahmedabad, Gujarat, India', 'Prahlad Nagar Main Rd', 'Ahmedabad', 'Gujarat', '380015', 23.0120000, 72.5100000, 3200000.00, '2026-02-01', '2026-10-30', 'in_progress', 45, 'BC-PROJ-SKYLINE-Z441'),
(3, 6, 7, 'Apex Horizon Residential Complex', 'Luxury 18-story residential apartment complex with automated parking and clubhouse.', 'Thaltej, Ahmedabad, Gujarat, India', 'Drive-In Road, Thaltej', 'Ahmedabad', 'Gujarat', '380059', 23.0485000, 72.5180000, 5100000.00, '2026-03-10', '2027-02-28', 'in_progress', 25, 'BC-PROJ-HORIZON-W902');

-- Project Milestones Seed Data
INSERT INTO milestones (id, project_id, title, description, target_date, amount, progress_percent, status, completed_at) VALUES
(1, 1, 'Foundation & Pile Reinforcement', 'Pouring 3000 cu.m heavy concrete foundation and pile cap reinforcement.', '2026-03-15', 850000.00, 100, 'completed', '2026-03-14 17:00:00'),
(2, 1, 'Structural Steel Frame Erection', 'Assembly of primary load-bearing I-beams up to story 18.', '2026-07-30', 1400000.00, 70, 'in_progress', NULL),
(3, 2, 'Commercial Electrical Distribution & Cabling', 'High voltage cabling, conduit installation, and 3-phase panel distribution.', '2026-08-15', 750000.00, 40, 'in_progress', NULL),
(4, 1, 'Facade & Curtain Wall Glazing', 'Installation of tempered glass curtain walls and insulation panels.', '2026-11-15', 950000.00, 0, 'pending', NULL),
(5, 3, 'Reinforced Foundation Slab', 'Basement slab pouring and waterproofing membrane installation.', '2026-08-20', 1100000.00, 40, 'in_progress', NULL);

-- Tasks Seed Data
INSERT INTO tasks (id, project_id, milestone_id, title, description, assigned_to_worker_id, priority, status, progress_percent, start_date, due_date, completed_at) VALUES
(1, 1, 1, 'Foundation excavation & soil testing', 'Excavate pile caps to 12m depth and conduct soil load-bearing analysis.', 3, 'high', 'completed', 100, '2026-01-20', '2026-03-10', '2026-03-09 16:30:00'),
(2, 1, 2, 'Structural steel beam welding & jointing', 'Weld floor 12-16 primary girders according to AWS D1.1 structural standards.', 3, 'critical', 'in_progress', 75, '2026-03-16', '2026-07-15', NULL),
(3, 1, 2, 'High altitude crane hoisting & positioning', 'Operate tower crane for steel beam elevation to story 16.', 3, 'high', 'in_progress', 65, '2026-04-01', '2026-07-25', NULL),
(4, 2, 3, 'Substation panel wiring & conduit layout', 'Lay main electrical feeder conduits for IT Park Block B ground floor.', 5, 'high', 'in_progress', 50, '2026-03-20', '2026-06-30', NULL),
(5, 2, 3, 'Transformer grounding & circuit safety audit', 'Install grounding rods and test circuit breakers for 3-phase transformer.', 5, 'medium', 'pending', 0, '2026-07-01', '2026-08-10', NULL),
(6, 3, 5, 'Basement slab rebar tying & concrete pour', 'Tie grade 60 rebar grid for basement levels and supervise 500 cu.m pour.', 8, 'critical', 'in_progress', 40, '2026-04-15', '2026-08-15', NULL);

-- Project Documents Seed Data
INSERT INTO project_documents (id, project_id, uploaded_by_user_id, title, file_path, file_type, created_at) VALUES
(1, 1, 2, 'Master Construction & General Conditions Agreement', 'uploads/documents/doc_proj1_agreement.pdf', 'contract', '2026-01-16 10:00:00'),
(2, 1, 2, 'Q3 Structural Steel Inspection & Progress Report', 'uploads/documents/doc_proj1_q3_report.pdf', 'report', '2026-06-20 14:30:00'),
(3, 3, 6, 'Architectural Site Blueprint & Engineering Plan', 'uploads/documents/doc_proj3_blueprint.pdf', 'blueprint', '2026-03-12 11:15:00');

-- Project Members
INSERT INTO project_members (project_id, user_id, role_in_project) VALUES
(1, 2, 'Contractor Manager'),
(1, 3, 'Structural Welder'),
(1, 4, 'Client Representative'),
(2, 2, 'Contractor Manager'),
(2, 5, 'Commercial Electrician'),
(2, 4, 'Client Representative'),
(3, 6, 'Contractor Manager'),
(3, 8, 'Concrete Specialist'),
(3, 7, 'Client Representative');

-- Jobs Seed Data
INSERT INTO jobs (id, project_id, contractor_id, title, trade_required, description, pay_rate, pay_type, location, city, state, employment_type, start_date, end_date, spots_available, spots_filled, status) VALUES
(1, 1, 2, 'Senior Structural Welder', 'Structural Welding', 'Looking for certified structural welder for steel frame jointing and beam assembly at Metropolis Commercial Tower.', 45.00, 'hourly', 'SG Highway, Bodakdev', 'Ahmedabad', 'Gujarat', 'Full-Time', '2026-02-01', '2026-11-30', 2, 1, 'published'),
(2, 3, 6, 'Industrial Concrete Specialist', 'Concrete Formwork & Masonry', 'Requires foundation slab pouring, rebar tie work, and reinforced mesh laying for residential basement.', 350.00, 'daily', 'Changodar Industrial Zone', 'Ahmedabad', 'Gujarat', 'Full-Time', '2026-03-01', '2026-09-30', 4, 0, 'published'),
(3, 2, 2, 'Commercial Master Electrician', 'Commercial Electrical Wiring', 'High voltage cabling, conduit installation, and 3-phase panel distribution for IT Park Block B.', 40.00, 'hourly', 'Prahlad Nagar, SG Highway', 'Ahmedabad', 'Gujarat', 'Contract', '2026-03-15', '2026-08-30', 3, 0, 'published'),
(4, 1, 2, 'Heavy Equipment Crane Operator (Draft)', 'Tower Crane Operation', 'Tower crane operator position reserved for high rise erection phase. Internal draft state.', 55.00, 'hourly', 'SG Highway, Bodakdev', 'Ahmedabad', 'Gujarat', 'Full-Time', '2026-05-01', '2026-12-01', 1, 0, 'draft');

-- Job Applications Seed Data
INSERT INTO job_applications (id, job_id, worker_id, match_score, cover_note, expected_pay, status) VALUES
(1, 1, 3, 95, 'I have 8+ years experience in structural steel welding and hold AWS Master certification. Available immediately.', 45.00, 'accepted'),
(2, 1, 5, 82, 'Commercial electrician interested in structural site wiring work. Familiar with high altitude safety procedures.', 40.00, 'pending'),
(3, 2, 8, 90, 'Lead concrete specialist with 6 years experience in foundation slab pouring and heavy rebar laying.', 350.00, 'shortlisted');

-- Notifications Seed Data
INSERT INTO notifications (id, user_id, title, message, type, related_type, related_id, is_read, link, created_at) VALUES
(1, 4, 'Milestone Completed', 'Structural Steel Frame Erection has reached 70% completion for Metropolis Commercial Tower.', 'MILESTONE_COMPLETED', 'milestone', 2, 0, 'client/milestones.php', NOW()),
(2, 4, 'New Document Uploaded', 'Contractor Apex Builders uploaded Q3 Structural Steel Inspection & Progress Report.', 'PROJECT_STATUS', 'project', 1, 0, 'client/documents.php', NOW()),
(3, 7, 'Site Excavation Completed', 'Site Excavation & Ground Prep milestone has been marked completed by Vanguard Infra Systems.', 'MILESTONE_COMPLETED', 'milestone', 5, 0, 'client/milestones.php', NOW()),
(4, 2, 'New Job Application', 'Elena Rostova submitted an application for Senior Structural Welder.', 'JOB_APPLICATION', 'job_application', 2, 0, 'contractor/applications.php', NOW()),
(5, 3, 'Application Accepted', 'Congratulations! Your application for Senior Structural Welder has been accepted.', 'APPLICATION_STATUS', 'job_application', 1, 0, 'worker/applications.php', NOW()),
(6, 3, 'New Task Assigned', 'You have been assigned to task: Structural steel beam welding & jointing.', 'TASK_ASSIGNED', 'task', 2, 0, 'worker/task-details.php?id=2', NOW()),
(7, 3, 'Digital Contract Sent', 'Apex Builders issued digital contract BC-2026-000001 for structural welding.', 'CONTRACT', 'contract', 1, 0, 'worker/contract-details.php?id=1', NOW()),
(8, 2, 'Contract Accepted by Worker', 'Marcus Vance accepted and signed contract BC-2026-000001.', 'CONTRACT', 'contract', 1, 0, 'contractor/contract-details.php?id=1', NOW()),
(9, 3, 'New Performance Review', 'Apex Builders posted a 5-star review for your structural welding work.', 'REVIEW', 'review', 1, 0, 'worker/reviews.php', NOW());

-- Attendance Sessions Seed Data
INSERT INTO attendance_sessions (id, project_id, created_by, token, expires_at, status, created_at) VALUES
(1, 1, 2, 'BC-QR-DEMO-TOK-PROJECT1-ACTIVE', DATE_ADD(NOW(), INTERVAL 10 MINUTE), 'active', NOW()),
(2, 3, 6, 'BC-QR-DEMO-TOK-PROJECT3-EXPIRED', DATE_SUB(NOW(), INTERVAL 1 HOUR), 'expired', DATE_SUB(NOW(), INTERVAL 1 HOUR));

-- Attendance History Seed Data
INSERT INTO attendance (id, project_id, worker_id, attendance_date, check_in_time, check_out_time, hours_worked, verified_by_qr, qr_session_id, status, created_at) VALUES
(1, 1, 3, CURRENT_DATE(), CONCAT(CURRENT_DATE(), ' 08:55:00'), CONCAT(CURRENT_DATE(), ' 17:30:00'), 8.58, 1, 1, 'present', NOW()),
(2, 2, 5, CURRENT_DATE(), CONCAT(CURRENT_DATE(), ' 09:42:00'), NULL, 0.00, 1, NULL, 'late', NOW()),
(3, 1, 3, DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY), CONCAT(DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY), ' 08:50:00'), CONCAT(DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY), ' 17:15:00'), 8.42, 1, NULL, 'present', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(4, 2, 5, DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY), CONCAT(DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY), ' 09:05:00'), CONCAT(DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY), ' 13:05:00'), 4.00, 1, NULL, 'half_day', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(5, 3, 8, DATE_SUB(CURRENT_DATE(), INTERVAL 2 DAY), CONCAT(DATE_SUB(CURRENT_DATE(), INTERVAL 2 DAY), ' 09:10:00'), NULL, 0.00, 1, NULL, 'incomplete', DATE_SUB(NOW(), INTERVAL 2 DAY));

-- Contracts Seed Data
INSERT INTO contracts (id, project_id, job_id, job_application_id, contractor_id, worker_id, contract_number, title, description, terms, payment_type, payment_amount, pay_amount, working_hours, start_date, end_date, contractor_signature, worker_signature, contractor_signed_at, worker_signed_at, status, created_at) VALUES
(1, 1, 1, 1, 2, 3, 'BC-2026-000001', 'Structural Steel Welding Agreement', 'Employment agreement for high-altitude beam joint welding on Metropolis Commercial Tower.', '1. Standard site safety procedures must be observed at all times.\n2. Payment rate is $45.00/hour payable bi-weekly.\n3. Standard shift is 8 hours/day, Monday through Friday.', 'hourly', 45.00, 45.00, '8 hours/day, Mon-Fri', '2026-02-01', '2026-11-30', 'Apex Builders Inc. (E-Signed)', 'Marcus Vance (E-Signed)', '2026-01-20 10:00:00', '2026-01-20 11:30:00', 'active', '2026-01-20 09:30:00'),
(2, 2, 3, NULL, 2, 5, 'BC-2026-000002', 'Commercial Substation Cabling Contract', 'Electrical wiring and conduit distribution for IT Park Block B.', '1. Compliance with NEC commercial electrical code mandatory.\n2. Agreed compensation $40.00/hr.\n3. Shifts subject to site access window.', 'hourly', 40.00, 40.00, '8 hours/day, Mon-Sat', '2026-03-15', '2026-08-30', 'Apex Builders Inc. (E-Signed)', NULL, '2026-03-14 15:00:00', NULL, 'pending', '2026-03-14 14:00:00'),
(3, 3, 2, NULL, 6, 8, 'BC-2026-000003', 'Basement Concrete & Rebar Contract', 'Foundation slab pouring and rebar grid assembly for Apex Horizon Residential Complex.', '1. Work completed according to structural design spec.\n2. Daily rate $350.00 per full day worked.', 'daily', 350.00, 350.00, '9 hours/day, Mon-Sat', '2026-03-01', '2026-04-30', 'Vanguard Infra Systems (E-Signed)', 'David Miller (E-Signed)', '2026-02-28 10:00:00', '2026-02-28 14:00:00', 'completed', '2026-02-28 09:00:00'),
(4, 1, 4, NULL, 2, 3, 'BC-2026-000004', 'Crane Operation Secondary Support (Draft)', 'Draft agreement for upcoming phase II crane hoisting.', 'Draft terms pending contractor and worker review.', 'hourly', 50.00, 50.00, '8 hours/day, Mon-Fri', '2026-05-01', '2026-12-01', NULL, NULL, NULL, NULL, 'draft', '2026-04-01 10:00:00');

-- Reviews Seed Data
INSERT INTO reviews (id, project_id, contract_id, reviewer_id, reviewee_id, rating, review_text, status, created_at) VALUES
(1, 1, 1, 2, 3, 5, 'Marcus is an exceptional structural welder. Outstanding precision, zero safety violations, and always punctual.', 'published', '2026-03-01 10:00:00'),
(2, 1, 1, 3, 2, 5, 'Apex Builders provides top tier safety equipment and clear project management. Excellent contractor to work with.', 'published', '2026-03-02 11:30:00'),
(3, 3, 3, 6, 8, 5, 'David completed the basement concrete pour on schedule with flawless rebar grid alignment. Highly recommended.', 'published', '2026-05-01 09:00:00'),
(4, 3, 3, 8, 6, 4, 'Great work environment and timely compensation payouts from Vanguard Infra Systems.', 'published', '2026-05-02 14:15:00');

-- Sample Review Report Seed Data
INSERT INTO review_reports (id, review_id, reported_by, reason, status, created_at) VALUES
(1, 4, 2, 'Requesting review verification check.', 'pending', NOW());



