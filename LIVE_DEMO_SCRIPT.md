# BuildConnect — 5-Minute Live Demonstration Script

This document provides the exact 22-step navigation order for conducting a fast, professional, 5-minute live demonstration of BuildConnect.

---

## Pre-Demo Preparation Checklist
1. Start local PHP development server: `php -S localhost:8000`
2. Ensure MySQL server is running with imported database `buildconnect`.
3. Open browser at `http://localhost:8000`.

---

## 22-Step Live Demonstration Sequence

### Step 1: Open BuildConnect Landing Page
- **URL:** `http://localhost:8000`
- **Action:** Point out clean hero section, dark mode toggle, feature overview, active project ticker, and public marketplace navigation.

### Step 2: Contractor Login
- **URL:** `http://localhost:8000/login.php`
- **Action:** Enter credentials:
  - Email: `contractor@buildconnect.com`
  - Password: `password123`
- **Result:** Redirected to Contractor Dashboard (`/contractor/index.php`).

### Step 3: View Contractor Dashboard
- **Action:** Point out project count metric card, active job count, active workforce count, and project progress chart.

### Step 4: Open Project Management
- **URL:** `/contractor/projects.php`
- **Action:** Click on project **Metropolis Commercial Tower - Phase II**.

### Step 5: View Project Details & Location Map
- **Action:** Show budget (`$4,500,000`), completion progress (`65%`), milestone list, assigned workforce, and interactive site location map (Bodakdev, Ahmedabad: `23.0225, 72.5714`).

### Step 6: Post / View Job Marketplace Listing
- **URL:** `/contractor/jobs.php`
- **Action:** View active job: **Senior Structural Welder & Crane Operator** ($360/day). Point out AI Worker Match button.

### Step 7: Trigger AI Worker Matching
- **Action:** Click **AI Worker Match** on job page. Show AI calculating match scores (Marcus Vance: 98% Match Score - High Match).

### Step 8: Switch Role — Worker Login
- **Action:** Logout contractor and log in as primary Worker:
  - Email: `worker@buildconnect.com`
  - Password: `password123`
- **Result:** Redirected to Worker Dashboard (`/worker/index.php`).

### Step 9: Show Worker Profile & Credentials
- **URL:** `/worker/profile.php`
- **Action:** Show trade title ("Master Crane Operator"), verified skills, document upload status, and 4.95 star rating.

### Step 10: Browse Jobs & Apply
- **URL:** `/worker/jobs.php`
- **Action:** Search open job listings, click **View Job**, enter optional application cover letter, and click **Submit Application**.

### Step 11: Switch Role — Contractor Hiring
- **Action:** Logout worker and log back in as Contractor (`contractor@buildconnect.com`). Navigate to **Applications** (`/contractor/applications.php`).

### Step 12: Accept & Hire Worker
- **Action:** Click **View Application** for Marcus Vance and click **Accept & Hire Worker**.

### Step 13: Verify Database Relationship
- **URL:** `/contractor/project-members.php`
- **Action:** Show Marcus Vance automatically onboarded as an active **Project Member** for Metropolis Tower.

### Step 14: View & Assign Task
- **URL:** `/contractor/tasks.php`
- **Action:** Show project task ("Structural Steel Frame Erection") assigned to Marcus Vance with progress status (`in_progress`).

### Step 15: Generate QR Code Site Attendance
- **URL:** `/contractor/attendance-qr.php`
- **Action:** Click **Generate Site QR Code** for Metropolis Tower. Display dynamic tokenized QR code (`BC-PROJ-AHMEDABAD-X892`).

### Step 16: Simulate Worker QR Attendance Check-In
- **URL:** `/worker/attendance.php` (logged in as worker)
- **Action:** Submit QR token `BC-PROJ-AHMEDABAD-X892`. Show instant check-in timestamp and attendance confirmation log.

### Step 17: Create & Sign Digital Contract
- **URL:** `/contractor/contracts.php`
- **Action:** Show issued contract. Switch to worker (`worker@buildconnect.com`), open `/worker/contracts.php`, click **Accept & Sign Contract**.

### Step 18: Show Real-Time Notification
- **Action:** Click Notification Bell in navbar. Show unread alert: *"Your contract for Metropolis Commercial Tower has been signed."*

### Step 19: Switch Role — Client Portal
- **Action:** Logout worker and log in as Client:
  - Email: `client@buildconnect.com`
  - Password: `password123`
- **Result:** Redirected to Client Dashboard (`/client/index.php`).

### Step 20: View Client Progress & Map
- **Action:** Show client view of Metropolis Commercial Tower: overall completion bar (`65%`), completed foundation milestone (`100%`), and interactive map pin. Note strict isolation from internal contractor data.

### Step 21: View System Analytics
- **URL:** `/contractor/analytics.php` (or `/admin/analytics.php`)
- **Action:** Show real-time analytics graphs: budget disbursement, task completion distribution, workforce attendance rate, and CSV report exporter.

### Step 22: Switch Role — Admin Governance & Finish
- **Action:** Logout client and log in as System Admin:
  - Email: `admin@buildconnect.com`
  - Password: `password123`
- **Action:** Show Admin Control Panel (`/admin/index.php`), Worker Verification Queue (`/admin/verifications.php`), and Security Audit Logs (`/admin/logs.php`).
- **Conclusion:** Declare: *"BuildConnect Live Demonstration Complete."*
