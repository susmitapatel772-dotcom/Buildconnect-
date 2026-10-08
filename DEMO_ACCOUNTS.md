# BuildConnect — Demo Accounts & Login Credentials

> [!WARNING]
> **DEVELOPMENT & PRESENTATION PURPOSES ONLY**
> DO NOT USE THESE CREDENTIALS IN A PRODUCTION ENVIRONMENT. ALWAYS ROTATE PASSWORDS AND RE-HASH WITH SECURE BCRYPT BEFORE DEPLOYMENT.

This document contains pre-configured test and presentation demonstration accounts seeded in the BuildConnect database. All accounts use the standard demo password (`password123`) designed for live walkthroughs and role-based evaluation.

---

## 1. Presentation Demonstration Accounts (`.demo`)

| Role | User Name | Email Address | Password | Profile & Demo Data Overview |
| :--- | :--- | :--- | :--- | :--- |
| **System Admin** | Admin User | `admin@buildconnect.demo` | `password123` | Platform oversight, user approvals, verification queue, security audit trail. |
| **Contractor** | Rajesh Construction | `contractor@buildconnect.demo` | `password123` | Rajesh BuildTech Pvt. Ltd. (Managing Green Heights & TechPark projects, hiring, tasks, QR attendance). |
| **Worker 1 (Mason)** | Amit Patel | `worker1@buildconnect.demo` | `password123` | Verified Mason (6 yrs exp, accepted for Green Heights, 3 attendance logs, active contract). |
| **Worker 2 (Electrician)** | Ravi Kumar | `worker2@buildconnect.demo` | `password123` | Verified Electrician (4 yrs exp, shortlisted for TechPark, 3 attendance logs, active contract). |
| **Client** | Karan Dangi | `client@buildconnect.demo` | `password123` | Dangi Infrastructure (Monitoring Green Heights Residency & TechPark Commercial Complex). |

---

## 2. Additional Development Accounts (`.com`)

| Role | User Name | Email Address | Password | Purpose |
| :--- | :--- | :--- | :--- | :--- |
| **System Admin** | System Administrator (Demo) | `admin@buildconnect.com` | `password123` | Primary admin development account. |
| **Contractor** | Apex Builders Inc. (Demo) | `contractor@buildconnect.com` | `password123` | Commercial tower contractor account. |
| **Worker** | Marcus Vance (Demo) | `worker@buildconnect.com` | `password123` | Master Crane Operator & Senior Welder. |
| **Client** | Skyline Urban Developers (Demo) | `client@buildconnect.com` | `password123` | Commercial Real Estate Developer. |

---

## 3. Recommended Walkthrough Flow Per Role

### Admin Walkthrough (`admin@buildconnect.demo`)
1. Log in to access **Admin Executive Dashboard**.
2. View user counts, active projects, and system health metrics.
3. Review **Worker Verifications** and approve/reject pending documents.
4. Inspect **Activity Audit Logs** and **System Settings**.

### Contractor Walkthrough (`contractor@buildconnect.demo`)
1. Log in to view **Contractor Dashboard** for Rajesh BuildTech.
2. View projects (**Green Heights Residency**, **TechPark Commercial Complex**) and map locations.
3. Manage **Jobs & Hiring** (Experienced Mason Required, Electrician Commercial Project).
4. Review worker applications and view accepted hires (Amit Patel, Ravi Kumar).
5. Generate **QR Site Attendance Sessions** and inspect logged attendance.
6. Manage **Tasks**, **Milestones**, and **Digital Contracts**.

### Worker Walkthrough (`worker1@buildconnect.demo` / `worker2@buildconnect.demo`)
1. Log in to view **Worker Dashboard**.
2. Inspect trade profile, verified skills, and work history.
3. View active job applications and accepted project assignments.
4. Perform **QR Attendance Check-In** and review logged attendance history.
5. Review signed **Digital Contracts** and assigned tasks.

### Client Walkthrough (`client@buildconnect.demo`)
1. Log in to view **Client Monitoring Portal** for Dangi Infrastructure.
2. Monitor **Green Heights Residency** (42% complete) & **TechPark Commercial Complex** (28% complete).
3. Inspect completed foundation milestones, upcoming target dates, and map site pins.
4. Verify data isolation (cannot access internal contractor cost sheets or worker private files).

---

## 4. Local Server Environment
- **Local URL:** `http://localhost:8000`
- **Database Name:** `buildconnect`
- **Database User:** `root`
- **Default Password for All Demo Accounts:** `password123`
