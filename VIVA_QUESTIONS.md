# BuildConnect — Viva Voce Questions & Answers Guide

This document contains 40 college viva questions and clear, concise answers covering the technical stack, architecture, security, database design, and features of **BuildConnect**.

---

## 1. Core PHP & Web Development

### Q1: What is PHP and why was it chosen for BuildConnect?
**Answer:** PHP (Hypertext Preprocessor) is a widely-used server-side scripting language designed for web development. It was chosen for BuildConnect because of its native database integration, excellent session management, high performance, and rapid deployment capabilities on standard web servers (Apache/Nginx).

### Q2: What is the difference between client-side and server-side execution in BuildConnect?
**Answer:** Server-side execution (PHP) runs on the web server to process business logic, perform database queries via PDO, and generate HTML. Client-side execution (JavaScript/CSS) runs inside the user's browser to handle DOM manipulations, interactive maps, form validation, and AJAX requests.

### Q3: How are environment variables managed in BuildConnect?
**Answer:** Sensitive configuration settings (database credentials, API keys) are stored in a `.env` file at the root of the project, which is loaded securely into PHP server environment variables and excluded from version control via `.gitignore`.

---

## 2. MySQL & Relational Database Design

### Q4: What database engine is used in BuildConnect and why?
**Answer:** MySQL / MariaDB using the **InnoDB** storage engine. InnoDB supports ACID-compliant transactions, row-level locking, and foreign key constraints, ensuring data integrity across complex relationships like projects, tasks, and contracts.

### Q5: What is a Primary Key and a Foreign Key? Give an example from BuildConnect.
**Answer:** A Primary Key uniquely identifies a record in a table (e.g., `id` in `projects`). A Foreign Key links a record in one table to the primary key of another table (e.g., `contractor_id` in `projects` references `id` in `contractors`).

### Q6: What is database normalization and is it implemented in BuildConnect?
**Answer:** Normalization is the process of structuring relational tables to reduce data redundancy and improve data integrity. BuildConnect follows Third Normal Form (3NF), storing trade skills in a master `skills` dictionary table and mapping them to workers via a junction table `worker_skills`.

### Q7: What are database indexes and why are they used?
**Answer:** Indexes are special lookup tables that the database search engine uses to speed up data retrieval. BuildConnect indexes primary keys, foreign keys (`user_id`, `project_id`, `job_id`), and frequently searched columns (`status`) to keep query response times under 45ms.

---

## 3. Database Connectivity & PDO

### Q8: What is PDO in PHP?
**Answer:** PDO stands for **PHP Data Objects**. It is a lightweight, consistent database abstraction layer in PHP that allows secure database access using prepared statements across multiple database types.

### Q9: What are Prepared Statements and how do they work?
**Answer:** Prepared statements separate SQL code from dynamic user data. The database first compiles the SQL template with parameter placeholders (`:email`), and then safely binds the user parameters during execution, preventing SQL Injection.

### Q10: How does BuildConnect prevent SQL Injection attacks?
**Answer:** 100% of database queries in BuildConnect use PDO prepared statements with explicit parameter binding (`$stmt->prepare()` and `$stmt->execute([':param' => $value])`). User input is never concatenated directly into SQL query strings.

---

## 4. Sessions, Authentication & Authorization

### Q11: How does session management work in BuildConnect?
**Answer:** When a user logs in, PHP creates a secure server-side session identified by a unique Session ID stored in a client-side HTTP cookie (`PHPSESSID`). User identity details (`user_id`, `role`, `name`) are stored safely in `$_SESSION`.

### Q12: How are user passwords secured in the database?
**Answer:** Passwords are never stored in plain text. They are hashed using PHP's `password_hash()` function with the `PASSWORD_BCRYPT` algorithm, which automatically incorporates a secure cryptographic salt. Authentication verifies passwords using `password_verify()`.

### Q13: What is the difference between Authentication and Authorization?
**Answer:** 
- **Authentication** is verifying *who* the user is (e.g. checking email and password at login).
- **Authorization** is verifying *what* an authenticated user is permitted to do (e.g. checking if `$_SESSION['user']['role'] === 'admin'`).

### Q14: How does BuildConnect enforce Role-Based Access Control (RBAC)?
**Answer:** Every page script calls helper functions such as `require_login()` and `require_role(['admin'])`. If a user attempts to access an unauthorized route, they are automatically blocked and redirected to `403.php` or `unauthorized.php`.

---

## 5. Security & Web Vulnerabilities

### Q15: What is Cross-Site Scripting (XSS) and how is BuildConnect protected against it?
**Answer:** XSS is an attack where malicious JavaScript code is injected into dynamic web pages. BuildConnect prevents XSS by sanitizing all user output rendered in HTML using `htmlspecialchars($string, ENT_QUOTES, 'UTF-8')`.

### Q16: What is Cross-Site Request Forgery (CSRF) and how does BuildConnect prevent it?
**Answer:** CSRF is an attack that tricks an authenticated user into submitting unwanted actions. BuildConnect attaches a cryptographically random 64-character token (`$_SESSION['csrf_token']`) to forms and validates it on server POST requests before executing database changes.

### Q17: What is an Insecure Direct Object Reference (IDOR) flaw and how is it prevented?
**Answer:** IDOR occurs when an application exposes a database record ID in the URL without checking ownership. BuildConnect prevents IDOR by enforcing server-side ownership checks (e.g., `WHERE id = :project_id AND contractor_id = :session_contractor_id`).

### Q18: What security flags are configured on BuildConnect session cookies?
**Answer:** 
- `HttpOnly = true`: Prevents client-side JavaScript from accessing session cookies (mitigating session hijacking).
- `SameSite = Lax`: Restricts cross-site cookie transmission (mitigating CSRF).
- `Secure = true`: Transmits cookies over HTTPS connections in production.

### Q19: How are file uploads secured in BuildConnect?
**Answer:** File uploads undergo strict server-side validation: extension whitelist checking (`pdf`, `png`, `jpg`), MIME type verification via PHP `finfo`, filesize cap (5MB), unique randomized filename generation, and execution restriction inside the `uploads/` directory via `.htaccess`.

---

## 6. Frontend, Layout & Responsive UI

### Q20: What front-end technologies are used in BuildConnect?
**Answer:** HTML5 for semantic structure, Vanilla CSS with custom design tokens for styling, Bootstrap 5.3 for responsive grid utilities, FontAwesome 6 for icons, and Vanilla JavaScript (ES6) for DOM interaction.

### Q21: How does BuildConnect achieve responsive layout across different screen sizes?
**Answer:** BuildConnect uses CSS flexbox, grid layouts, fluid media queries, and Bootstrap responsive grid classes (`col-12`, `col-md-6`, `col-lg-4`). Tables and cards adapt cleanly across mobile (360px), tablet (768px), and desktop (1920px).

### Q22: What is the purpose of the central design system in BuildConnect?
**Answer:** The central stylesheet (`assets/css/style.css`) defines consistent design tokens (colors, typography, spacing, border radii, card shadows, button states), ensuring a uniform and professional UI visual language across all portals.

---

## 7. CRUD Operations & Architectural Patterns

### Q23: What does CRUD stand for? Give examples from BuildConnect.
**Answer:** CRUD stands for **Create, Read, Update, Delete**:
- **Create:** Contractor posts a new job opening.
- **Read:** Worker views the list of available jobs.
- **Update:** Worker updates task completion progress to 100%.
- **Delete:** Admin removes a suspended user profile.

### Q24: What is the architectural structure of BuildConnect?
**Answer:** BuildConnect uses a clean modular multi-tier architecture. It separates presentation templates (`includes/header.php`, `navbar.php`), role-scoped page logic (`admin/`, `contractor/`, `worker/`, `client/`), API endpoints (`api/`), and core utility libraries (`includes/functions.php`, `auth.php`).

---

## 8. AJAX, REST APIs & JSON

### Q25: What is AJAX and how is it used in BuildConnect?
**Answer:** AJAX (Asynchronous JavaScript and XML) allows web pages to update content asynchronously by exchanging data with the server in the background without reloading the full page. BuildConnect uses JavaScript `fetch()` for notification updates and AI worker matching.

### Q26: What is a REST API endpoint and how do BuildConnect API files work?
**Answer:** A REST API endpoint receives HTTP requests, processes business logic, and returns structured JSON responses. Files in `api/` (e.g. `api/notifications.php`) set `Content-Type: application/json`, verify authentication and CSRF headers, and output JSON payloads.

---

## 9. QR Code Site Attendance System

### Q27: How does the QR Attendance workflow function in BuildConnect?
**Answer:** 
1. Contractor generates a dynamic QR session token (`BC-PROJ-AHMEDABAD-X892`) bound to a specific project site and date.
2. The QR token is displayed on site.
3. The worker scans or submits the token via their mobile portal.
4. The server validates that the session token is active and records check-in timestamp and attendance status in `attendance`.

### Q28: How does BuildConnect prevent fake QR attendance check-ins?
**Answer:** The server validates that the QR code token exists in active `attendance_sessions`, verifies that the worker is an accepted `project_member`, and checks that an existing active check-in does not already exist for that date.

---

## 10. Digital Contracts, Reviews & Ratings

### Q29: What is the lifecycle of a digital contract in BuildConnect?
**Answer:** 
1. Contractor creates a contract for a hired worker (`status = 'draft'`).
2. Contractor issues contract to worker (`status = 'sent'`).
3. Worker reviews terms and signs digitally (`status = 'signed'` or `'accepted'`).
4. Contract becomes active during project execution and transitions to `completed` upon job sign-off.

### Q30: How does the two-way rating system update profile averages?
**Answer:** When a contractor or worker submits a 1–5 star review, the server inserts a record into `reviews` and dynamically recalculates `rating_avg` and `reviews_count` on the `workers` or `contractors` table.

---

## 11. Google Maps & Location Features

### Q31: How is Google Maps integrated into BuildConnect?
**Answer:** BuildConnect uses Leaflet.js / Google Maps API to render interactive map containers on project detail pages. Projects store latitude (`location_lat`) and longitude (`location_lng`) floats in the database, which are rendered as map pins.

### Q32: What is the fallback mechanism if Google Maps API key is unconfigured?
**Answer:** BuildConnect falls back smoothly to Leaflet.js open-source map rendering using default project coordinates (e.g., Ahmedabad, Gujarat: `23.0225, 72.5714`), ensuring maps always render visually without breaking the UI.

---

## 12. Analytics, Notifications & AI Intelligence

### Q33: How are project analytics calculated in BuildConnect?
**Answer:** Analytics scripts (`includes/analytics-functions.php`) run SQL aggregation queries (`COUNT()`, `SUM()`, `AVG()`, `GROUP BY`) against real database tables (`projects`, `tasks`, `attendance`, `contracts`) to generate metric cards, progress bars, and budget distribution summaries.

### Q34: How does the Notification system deliver alerts to users?
**Answer:** When system events occur (e.g., application submitted, worker hired, contract issued), the system inserts a record into `notifications`. The UI badge displays unread counts, and users can mark alerts as read.

### Q35: How does the AI Worker Matching engine work?
**Answer:** The AI engine (`includes/ai.php`) compares job trade requirements against worker profiles. It evaluates trade skill alignment, years of experience, document verification status, proximity, and past star ratings to calculate a structured Match Score (0–100%).

### Q36: Is the AI feature dependent on paid external APIs?
**Answer:** No. BuildConnect includes a native offline rule-based heuristic algorithm that computes matching scores and project risk indicators locally. If a Gemini/OpenAI API key is provided in `.env`, external AI capabilities are selectively enabled.

### Q37: What safety rules govern AI actions in BuildConnect?
**Answer:** AI recommendations are strictly advisory ("Human-in-the-loop"). AI is **never** permitted to automatically hire, fire, reject applicants, alter contracts, change attendance records, or modify database tables without human review.

---

## 13. System Testing, Production & Maintenance

### Q38: What testing methodologies were used to verify BuildConnect?
**Answer:** Unit syntax testing (PHP `-l` across 110 files), automated regression test suites (`scratch/test_step14_master.php`), manual role-based workflow testing, cross-browser responsive testing, and security payload injection testing.

### Q39: What custom error pages are implemented for production?
**Answer:** BuildConnect includes clean custom error handlers:
- `403.php` (Forbidden / Unauthorized Access)
- `404.php` (Page Not Found)
- `500.php` (Internal Server Error)
Production mode suppresses raw PHP error displays to prevent information leakage.

### Q40: What steps are involved in backing up the BuildConnect application?
**Answer:** 
1. Database backup via `mysqldump` to export `schema.sql` and data dumps.
2. File system backup of project files and user uploads in `uploads/`.
3. Secure preservation of `.env` configuration files.
