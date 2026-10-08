# BuildConnect — Professional 5–10 Minute Demonstration Script

This script provides a step-by-step presentation narrative for demonstrating BuildConnect to stakeholders, clients, or evaluators.

---

## Demo Overview
- **Target Duration:** 8 to 10 Minutes
- **Key Highlight:** End-to-end digital lifecycle of a construction project — from project setup, job posting, AI matching, hiring, QR site attendance, digital contracts, and progress monitoring, to admin governance.

---

## Step-by-Step Presentation Flow

### Phase 1: Landing Page & Public Marketplace (0:00 - 0:45)
1. **Open Browser:** Navigate to `http://localhost:8000`.
2. **Showcase Landing Page:**
   - Highlight modern responsive UI, dark mode toggle, and value proposition for Contractors, Workers, and Clients.
   - Point out active project highlights, open jobs ticker, and feature highlights (QR Attendance, AI Worker Matching, Interactive Maps).
3. **Key Narrative:** *"BuildConnect unifies all stakeholders in construction—contractors, skilled workers, real estate clients, and system admins—into a single high-trust digital platform."*

---

### Phase 2: Contractor Experience — Project & Job Creation (0:45 - 2:30)
1. **Login as Contractor:**
   - Email: `contractor@buildconnect.com`
   - Password: `password123`
2. **Dashboard Review:**
   - Show active projects count, hired workforce count, active jobs, and recent project progress charts.
3. **Project Management & Maps:**
   - Click **Projects** → Select **Metropolis Commercial Tower - Phase II**.
   - Show interactive **Google Maps** displaying exact GPS coordinates (`23.0225, 72.5714`).
   - Show milestones progress bar and project workforce members.
4. **Job Posting:**
   - Click **Jobs & Hiring** → **Post New Job**.
   - Title: `Senior Structural Welder & Crane Operator`.
   - Set daily rate (`$360/day`), required trade, and project binding. Click **Create Job**.
5. **Key Narrative:** *"Contractors can set up multi-phase construction projects with precise GPS locations, milestones, and instantly broadcast job openings to qualified trade workers."*

---

### Phase 3: AI Assistant & Worker Matching (2:30 - 3:45)
1. **AI Match Generation:**
   - On the newly created job page, click **AI Worker Match**.
   - Show AI analysis scanning worker trade skills, verification status, proximity, and past ratings.
   - Point out top match: **Marcus Vance** (Match Score: 98% - High Match).
2. **Key Narrative:** *"BuildConnect utilizes an intelligent rule-based AI engine to match project job requirements with certified workers based on trade skills, location, availability, and past performance ratings."*

---

### Phase 4: Worker Experience — Job Search & Application (3:45 - 4:45)
1. **Logout & Login as Worker:**
   - Email: `worker@buildconnect.com`
   - Password: `password123`
2. **Worker Profile & Credentials:**
   - Show worker trade profile, verified skills (Structural Welding, Tower Crane Operation), uploaded identity/license documents, and overall rating (`4.95 ⭐`).
3. **Find Jobs & Apply:**
   - Navigate to **Find Jobs** → Filter by Trade / Location.
   - Click on the open job listing and click **Submit Application**. Add an optional cover message and click **Apply**.
4. **Key Narrative:** *"Skilled tradespeople can maintain verified digital credentials and easily apply to jobs in their area with transparent pay rates."*

---

### Phase 5: Contractor Hiring & Project Onboarding (4:45 - 5:45)
1. **Switch Back to Contractor:** (`contractor@buildconnect.com`)
2. **Review & Accept Application:**
   - Navigate to **Jobs & Hiring** → **Applications**.
   - Click **View Application** for Marcus Vance.
   - Click **Accept & Hire Worker**.
3. **Database Consistency Verification:**
   - Point out automatic system actions: Application status updates to `accepted`, candidate is automatically onboarded as a **Project Member**, and a notification is dispatched to the worker.
4. **Key Narrative:** *"With a single click, contractors accept applications, updating database relationships and instantly granting the worker project member access."*

---

### Phase 6: Site Management — QR Attendance & Digital Contracts (5:45 - 7:00)
1. **Digital Contract Generation:**
   - Go to **Contracts** → **Create Contract**.
   - Select Project, Worker (Marcus Vance), Daily Pay Rate, and Start Date. Click **Issue Contract**.
2. **Worker Contract Signing:**
   - Switch briefly to Worker (`worker@buildconnect.com`), open **Contracts**, and click **Accept & Sign Contract**.
3. **QR Code Site Attendance:**
   - Switch to Contractor (`contractor@buildconnect.com`), open **QR Attendance**.
   - Click **Generate Site QR Code** for Metropolis Commercial Tower.
   - Show dynamic QR code with security token.
   - Open worker attendance check-in tool (`/worker/attendance.php`), simulate scanning/submitting the QR token.
   - Show instant check-in confirmation with timestamp and location tag.
4. **Key Narrative:** *"BuildConnect eliminates timecard fraud through secure, tokenized QR site check-ins and legally transparent digital contracts."*

---

### Phase 7: Client Experience — Real-Time Monitoring (7:00 - 8:00)
1. **Logout & Login as Client:**
   - Email: `client@buildconnect.com`
   - Password: `password123`
2. **Client Portal:**
   - Show client dashboard featuring **Metropolis Commercial Tower - Phase II**.
   - Show overall progress bar (`65%`), completed milestones (Foundation & Pile Reinforcement - `100%`), and interactive project map location.
3. **Security Check (IDOR Isolation):**
   - Note that client can view overall high-level progress and milestone summaries, but has **zero access** to worker private documents, contractor internal costs, or administrative settings.
4. **Key Narrative:** *"Real estate developers and project owners get complete visibility into project milestones and location maps without exposure to sensitive internal contractor data."*

---

### Phase 8: Admin Panel & Security Demonstration (8:00 - 9:30)
1. **Logout & Login as Admin:**
   - Email: `admin@buildconnect.com`
   - Password: `password123`
2. **Admin Control Panel:**
   - View global platform analytics: Total users, workers, contractors, active projects, and system health.
   - Navigate to **Worker Verifications** and review submitted trade certificates.
   - Inspect **Activity & Security Audit Logs** showing all logged actions (logins, status updates, contract creations).
3. **Security Controls Demo:**
   - Show CSRF token fields in forms (`csrf_token`).
   - Demonstrate role-based URL protection: attempt to navigate directly to `/admin/users.php` while logged in as a Worker -> automatically blocked and redirected to `403.php` or dashboard.
4. **Key Narrative:** *"BuildConnect implements robust enterprise-grade security: PDO prepared statements against SQL injection, HTML entity sanitization against XSS, cryptographically secure CSRF protection, and strict server-side role authorization."*

---

### Conclusion (9:30 - 10:00)
- Summarize platform capabilities: Full lifecycle construction project management, transparent hiring, fraud-proof QR attendance, digital contracts, and cross-role communication.
- Open for Questions & Evaluation.
