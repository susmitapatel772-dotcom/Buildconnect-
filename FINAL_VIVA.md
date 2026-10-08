# BuildConnect — Master Viva Voce Questions & Answers Guide

This document contains 60+ comprehensive viva voce questions and answers, categorized into 10 core technical domains, followed by a dedicated section covering tricky viva scenario questions.

---

## SECTION A: PROJECT QUESTIONS

### 1. What is BuildConnect?
**Answer:** BuildConnect is a digital web platform that connects construction workers, contractors, real estate clients, and system administrators to manage workforce hiring, project tasks, site attendance via QR codes, digital contracts, and analytics.

### 2. Why did you build BuildConnect?
**Answer:** Traditional construction workforce management relies on paper rosters, verbal agreements, and unverified worker skills, causing timecard fraud, payment disputes, and project delays. BuildConnect digitizes this entire workflow.

### 3. What problem does BuildConnect solve?
**Answer:** It eliminates timecard fraud through QR attendance, provides verified worker credentials, formalizes hiring with digital contracts, and offers real-time project progress transparency to clients.

### 4. Who are the primary target users?
**Answer:** System Administrators, Contractors/Construction Managers, Skilled Construction Workers, and Real Estate Clients/Project Owners.

### 5. What are the key modules implemented in BuildConnect?
**Answer:** Authentication, Worker Verification, Contractor Projects, Client Monitoring, Job Marketplace & Hiring, Tasks & Milestones, QR Site Attendance, Digital Contracts, Two-Way Reviews, Notifications, Google Maps, Analytics, and AI Matching.

### 6. What makes BuildConnect unique compared to generic job portals?
**Answer:** BuildConnect is construction-specific: it binds job listings directly to active construction projects, calculates daily/hourly trade rates, handles tokenized site QR check-ins, and tracks multi-phase project milestone progress.

---

## SECTION B: PHP QUESTIONS

### 7. Why was PHP chosen for BuildConnect?
**Answer:** PHP is a fast, mature, server-side scripting language with native session handling and built-in database support via PDO, making it ideal for relational web applications.

### 8. What is the difference between `require` and `include` in PHP?
**Answer:** Both include external files. However, `include` produces a warning and continues script execution if the file is missing, whereas `require` produces a fatal error and halts execution. BuildConnect uses `require_once` for critical functions and header files.

### 9. What is `$_SESSION` and how is it used?
**Answer:** `$_SESSION` is an associative array used to store user variables across multiple web pages. BuildConnect uses it to maintain user login state (`$_SESSION['user']`) and CSRF tokens (`$_SESSION['csrf_token']`).

### 10. How does `password_hash()` work in PHP?
**Answer:** `password_hash()` creates a secure cryptographic hash using algorithms like `PASSWORD_BCRYPT`. It automatically generates a unique random salt and embeds it in the resulting hash string.

### 11. What is the function `htmlspecialchars()` used for?
**Answer:** `htmlspecialchars()` converts special characters (like `<` and `>`) into HTML entities (`&lt;` and `&gt;`). This prevents Cross-Site Scripting (XSS) attacks when displaying user input.

### 12. How does file error logging work in production mode?
**Answer:** PHP configuration settings `display_errors` is turned `Off` so users never see technical stack traces, while `log_errors` is turned `On` to save errors securely in a server log file.

---

## SECTION C: MYSQL QUESTIONS

### 13. Why was MySQL chosen as the database engine?
**Answer:** MySQL is a reliable, open-source relational database management system offering ACID compliance, foreign key constraints, and fast read/write operations via the InnoDB storage engine.

### 14. What storage engine does BuildConnect use in MySQL?
**Answer:** InnoDB, because it supports foreign key constraints, row-level locking, and transactional integrity across related tables.

### 15. What is a Foreign Key and why is it essential?
**Answer:** A Foreign Key is a field in one table that uniquely identifies a row in another table. It enforces referential integrity so orphaned records cannot be created.

### 16. What is the purpose of an INDEX in MySQL?
**Answer:** Indexes speed up `SELECT` queries by creating a fast lookup tree on indexed columns (e.g. `user_id`, `project_id`, `status`), avoiding full table scans.

### 17. What is the difference between `INNER JOIN` and `LEFT JOIN`?
**Answer:** `INNER JOIN` returns only rows with matching records in both tables. `LEFT JOIN` returns all rows from the left table and matching rows from the right table (or `NULL` if no match exists).

### 18. What is `CASCADE` on `DELETE` / `UPDATE`?
**Answer:** `ON DELETE CASCADE` automatically deletes dependent records in a child table when the corresponding parent record is deleted.

---

## SECTION D: JAVASCRIPT QUESTIONS

### 19. What role does JavaScript play in BuildConnect?
**Answer:** Vanilla JavaScript (ES6) handles client-side interactive behaviors: DOM manipulations, form validation, Leaflet/Google map rendering, dynamic modal popups, and asynchronous Fetch requests.

### 20. What is `fetch()` in JavaScript?
**Answer:** `fetch()` is a modern web API used to make asynchronous HTTP network requests from the browser to server endpoints (e.g., retrieving unread notifications or triggering AI candidate matches).

### 21. What is the Event Loop in JavaScript?
**Answer:** The Event Loop is JavaScript's single-threaded concurrency model that executes code, collects events, and processes queued sub-tasks (callbacks and Promises).

### 22. How does event delegation work in DOM handling?
**Answer:** Event delegation attaches a single event listener to a parent element to handle events fired by child elements, improving memory efficiency.

### 23. What is `JSON.parse()` and `JSON.stringify()`?
**Answer:** `JSON.parse()` converts a JSON-formatted string into a JavaScript object. `JSON.stringify()` converts a JavaScript object into a JSON string.

---

## SECTION E: SECURITY QUESTIONS

### 24. What is SQL Injection and how is it prevented in BuildConnect?
**Answer:** SQL Injection is an attack where malicious SQL statements are inserted into entry fields. BuildConnect prevents it by using 100% PDO prepared statements with bound parameters (`$stmt->execute()`).

### 25. What is Cross-Site Scripting (XSS) and how is it prevented?
**Answer:** XSS is an attack where malicious scripts are injected into web pages. BuildConnect prevents XSS by escaping all rendered user output using `sanitize()` / `htmlspecialchars()`.

### 26. What is Cross-Site Request Forgery (CSRF) and how is it prevented?
**Answer:** CSRF tricks an authenticated user into performing unauthorized actions. BuildConnect attaches a 64-character secret token (`$_SESSION['csrf_token']`) to forms and validates it on every POST request.

### 27. What is Insecure Direct Object Reference (IDOR)?
**Answer:** IDOR happens when a user modifies a URL parameter (e.g., `project_id=5`) to access another user's private record. BuildConnect prevents IDOR by enforcing server-side ownership checks (`WHERE id = :id AND contractor_id = :session_contractor_id`).

### 28. What are `HttpOnly` and `SameSite` flags on cookies?
**Answer:** `HttpOnly` blocks JavaScript from reading session cookies to prevent session hijacking. `SameSite=Lax` restricts sending cookies on cross-site requests to mitigate CSRF.

---

## SECTION F: API QUESTIONS

### 29. What is a RESTful API?
**Answer:** A RESTful API is an architectural style for web services that uses standard HTTP methods (`GET`, `POST`, `PUT`, `DELETE`) and formatted JSON payloads.

### 30. How are BuildConnect API endpoints structured?
**Answer:** API files reside in `api/` (e.g., `api/notifications.php`). They enforce session authentication, set header `Content-Type: application/json`, and return structured JSON responses: `{"success": true, "data": {...}}`.

### 31. How are API errors handled?
**Answer:** If authentication fails or invalid data is received, the API returns an appropriate HTTP status code (e.g., 401 Unauthorized or 400 Bad Request) and a JSON error message without exposing database details.

---

## SECTION G: AI QUESTIONS

### 32. What AI features are implemented in BuildConnect?
**Answer:** AI Worker Matching (scoring candidate suitability 0–100%), AI Project Delay Risk Insights, AI Job Description Assistant, and AI Worker Profile Optimizer.

### 33. Is BuildConnect dependent on external paid AI APIs?
**Answer:** No. BuildConnect includes a native offline rule-based heuristic matching engine. External API keys (e.g. Gemini/OpenAI) can be optionally enabled in `.env`.

### 34. Can AI automatically hire or fire workers?
**Answer:** No. AI features are strictly advisory ("Human-in-the-Loop"). All state changes require explicit human review and button actions.

### 35. How is user data protected when communicating with AI services?
**Answer:** Sensitive attributes (passwords, tokens, phone numbers, personal identifiers) are filtered out before context is transmitted to AI algorithms.

---

## SECTION H: DATABASE QUESTIONS

### 36. How many database tables exist in BuildConnect?
**Answer:** 26 relational tables.

### 37. Name 5 core tables and their primary purpose.
**Answer:** 
- `users`: Stores login credentials and account status.
- `projects`: Stores construction project details, budget, and map coordinates.
- `jobs`: Stores job marketplace listings.
- `attendance`: Stores daily worker check-in/out timestamps.
- `contracts`: Stores digital agreements and acceptance terms.

### 38. How is project overall progress calculated in the database?
**Answer:** Project progress is calculated by aggregating the completion percentages of its associated `milestones` and `tasks` (`AVG(progress_percent)`).

---

## SECTION I: SYSTEM DESIGN QUESTIONS

### 39. What architectural pattern does BuildConnect follow?
**Answer:** BuildConnect follows a modular multi-tier MVC-inspired architecture, separating Presentation (HTML/CSS/JS), Business Logic & Security (PHP functions and role controllers), and Data Persistence (MySQL database).

### 40. How does the application scale for multiple contractors and projects?
**Answer:** Through normalized database relationships and strict scoping (`contractor_id` on projects, `project_id` on tasks/jobs), enabling clean multi-tenant data isolation.

---

## SECTION J: GENERAL QUESTIONS

### 41. What web server is required to run BuildConnect?
**Answer:** Apache 2.4+ (with `mod_rewrite`) or Nginx 1.20+ running PHP 8.1+ and MySQL 8.0+.

### 42. How does mobile responsiveness work in BuildConnect?
**Answer:** Through fluid CSS media queries, Bootstrap responsive breakpoints, and mobile-friendly collapsible drawer navigation.

---

## TRICKY VIVA QUESTIONS & ANSWERS

### Q43: Why should evaluators trust your application?
**Answer:** Evaluators can trust BuildConnect because it has undergone rigorous automated testing (18/18 master audit tests passed, 100% clean PHP syntax across 110 files), enforces 100% PDO prepared statements, escapes output against XSS, and strictly validates user ownership server-side.

### Q44: What happens if two workers apply for the last remaining vacancy simultaneously?
**Answer:** When the contractor clicks "Accept & Hire", the server initiates a database transaction. The first request accepted sets the application status to `accepted` and increments hired count. The second attempt checks available openings count; if zero openings remain, the server blocks further hires and updates remaining pending applications to `rejected` or displays a clear capacity warning.

### Q45: What happens if a worker tries to mark attendance for a project they are not assigned to?
**Answer:** The server intercepts the QR token check-in request and queries `project_members`. If the worker is not registered as an active member of that project, the check-in is rejected with an HTTP 403 error: *"Unauthorized: You are not an active member of this project site."*

### Q46: What happens if Contractor A attempts to access Contractor B's project by typing its ID in the URL?
**Answer:** The server executes an IDOR verification query: `SELECT * FROM projects WHERE id = :project_id AND contractor_id = :session_contractor_id`. Because the contractor ID does not match the session ID, the query returns empty, and the server halts execution, redirecting to `403.php` (Access Denied).

### Q47: What happens if an AI recommendation is completely wrong or inaccurate?
**Answer:** Because BuildConnect enforces "Human-in-the-Loop" design, AI suggestions are strictly advisory. The contractor or worker can ignore the recommendation and select candidate workers or edit descriptions manually. AI never mutates database state.

### Q48: What happens if the MySQL database connection fails unexpectedly?
**Answer:** The PDO wrapper inside `config/database.php` catches the `PDOException`. In production mode, it suppresses technical database connection strings and displays a friendly error page (`500.php` - Service Temporarily Unavailable) while logging technical details to a private error log.

### Q49: What happens if a user submits malicious HTML or JavaScript tags in a form input?
**Answer:** Input sanitization functions strip unsafe tags, and output rendering calls `htmlspecialchars()`. The script tags are rendered as plain text strings (`&lt;script&gt;`) rather than executed as browser code.

### Q50: What happens if an uploaded file contains a hidden PHP script (e.g. `image.png.php`)?
**Answer:** The upload handler checks both the file extension whitelist (`pdf`, `png`, `jpg`, `jpeg`) and the true MIME type using PHP `finfo`. It renames the file to a randomized hash and stores it in `uploads/`, where execution of scripts is explicitly disabled via `.htaccess` (`Deny from all` for `.php` files).

### Q51: Explain the complete worker hiring workflow from start to finish.
**Answer:** 
1. Contractor posts a job bound to a project.
2. Worker discovers job listing and submits an application.
3. Contractor reviews candidate profile, verified skills, and AI Match score.
4. Contractor clicks "Accept & Hire Worker".
5. Server updates application status to `accepted`, creates a row in `project_members`, sends a notification to the worker, and permits task assignment.

### Q52: Explain the complete project workflow from start to finish.
**Answer:** 
1. Contractor creates a project with location coordinates, budget, and target dates.
2. Contractor posts jobs and hires qualified workers.
3. Contractor assigns tasks and creates milestone target dates.
4. Workers perform work and mark attendance via site QR codes.
5. Contractor issues digital contracts; workers sign electronically.
6. Milestones are completed, updating project progress percentage.
7. Client monitors completed progress and map location on their portal.
