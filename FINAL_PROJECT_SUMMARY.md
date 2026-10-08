# BuildConnect — Final Project Summary

## Project Name
**BuildConnect**

## One-Line Description
A digital construction workforce and project management platform connecting workers, contractors, real estate clients, and administrators into a single unified ecosystem.

---

## Problem Statement
Traditional construction workforce and project management suffers from severe operational inefficiencies:
1. **Fragmented & Unverified Workforce:** Contractors struggle to discover qualified labor with verified trade credentials and past performance records.
2. **Attendance & Timecard Fraud:** Paper attendance sheets and verbal sign-ins lead to inaccurate work hours and "buddy punching".
3. **Informal Hiring Agreements:** Lack of standardized digital contracts between contractors and site labor causes payment disputes and unclear terms.
4. **Opaque Client Oversight:** Property developers and clients lack real-time visibility into milestone completions, budgets, and physical site locations.
5. **Disconnected Tools:** Managing jobs, tasks, attendance, contracts, and analytics requires multiple disjointed spreadsheets and phone calls.

---

## Proposed Solution
BuildConnect provides a single, centralized web-based platform tailored for the construction industry:
- **Verified Digital Profiles:** Trade skill taxonomies, verified identity/license document uploads, and two-way star ratings.
- **Job Marketplace & One-Click Hiring:** Contractors list project openings; qualified workers apply; contractors accept applicants with automatic project onboarding.
- **Tokenized QR Site Attendance:** Time-stamped, location-aware site check-in/check-out using dynamic QR code tokens.
- **Digital Contracts:** System-generated contracts with digital terms and worker electronic acceptance tracking.
- **Client Project Transparency:** Dedicated read-only client dashboards for milestone progress visualization and interactive site location maps.
- **AI Intelligence Layer:** Rule-based candidate-job matching algorithms and project schedule delay risk forecasting.

---

## Target Users
1. **System Administrator:** Platform governance, user verification approvals, audit logs, system configuration.
2. **Contractor / Construction Manager:** Project setup, job postings, worker hiring, task delegation, QR attendance sessions, digital contracts.
3. **Skilled Construction Worker:** Trade profile management, job discovery and application, QR site check-ins, contract signing, rating reviews.
4. **Client / Property Developer:** Real-time project oversight, milestone completion tracking, interactive map location inspection.

---

## Main Modules
1. **Authentication & Access Control** (Bcrypt hashing, session RBAC, secure logout)
2. **Worker Profile & Verification Management** (Skills, experience, cert upload)
3. **Contractor Portfolio & Project Management** (Projects, locations, budgets)
4. **Client Monitoring Portal** (Progress tracking, milestone reports)
5. **Job Marketplace & Hiring Workflow** (Post jobs, applications, hiring pipeline)
6. **Task & Milestone Allocation System** (Task priorities, milestone percentages)
7. **QR Code Site Attendance System** (Token generation, instant check-in/out)
8. **Digital Contracts & Agreement Signing** (Contract terms, status tracking)
9. **Two-Way Ratings & Review System** (1–5 star reviews, feedback moderation)
10. **Notification Center** (Real-time user alerts, read/unread states)
11. **Interactive Site Maps** (Leaflet.js / Google Maps API geolocation)
12. **Analytics & CSV Reporting Engine** (Database metrics, CSV export)
13. **AI Intelligence Engine** (Worker-job matching score, delay risk insights)

---

## Technology Stack
- **Backend Core:** PHP 8.1 / 8.2 (Modular Architecture)
- **Database Engine:** MySQL 8.0 / MariaDB 10.4 (InnoDB Engine, PDO Prepared Statements)
- **Frontend Presentation:** Responsive HTML5, CSS3 Custom Tokens, Vanilla JavaScript (ES6+)
- **UI Design System:** Bootstrap 5.3 + FontAwesome 6 Icons
- **Interactive Maps:** Leaflet.js with fallback coordinates (Ahmedabad, Gujarat: `23.0225, 72.5714`)
- **Data Interchange:** RESTful JSON APIs for background fetch requests
- **External Integrations:** Google Maps API (Geocoding), AI Engine (Gemini / Rule-Based Local Engine)
- **Site Security:** Bcrypt hashing, SHA-256 tokens, 64-char CSRF protection, HTML entity escaping.
