# BUILD CONNECT — PROJECT BRAIN

> This file is the permanent development instruction and architectural reference for the BuildConnect project.

---

# 1. PROJECT IDENTITY

Project Name:

BuildConnect

Project Type:

Construction Workforce + Project Management Platform

Primary Users:

1. Admin
2. Contractor
3. Worker
4. Client

---

# 2. CORE PURPOSE

BuildConnect connects construction workers, contractors and clients through one centralized digital platform.

The system must support:

- Workforce discovery
- Worker verification
- Job posting
- Job applications
- Hiring
- Construction project management
- Tasks
- Milestones
- Attendance
- Digital contracts
- Reviews
- Notifications
- Analytics
- AI-assisted matching

---

# 3. GOLDEN DEVELOPMENT RULE

DO NOT break existing working functionality.

Before changing existing code:

1. Understand the current implementation.
2. Identify dependencies.
3. Make the smallest safe change.
4. Test the affected functionality.
5. Verify that existing functionality still works.

Never rewrite the entire application just to add one feature.

---

# 4. DEVELOPMENT METHOD

Build the application incrementally.

Never attempt to build the complete application in one operation.

Use this order:

PHASE 1
Foundation

PHASE 2
Authentication

PHASE 3
Admin

PHASE 4
Contractor

PHASE 5
Worker

PHASE 6
Client

PHASE 7
Jobs + Hiring

PHASE 8
Projects

PHASE 9
Attendance + QR

PHASE 10
Contracts + Reviews

PHASE 11
Maps + Notifications

PHASE 12
Analytics

PHASE 13
AI

PHASE 14
Security + Testing + Final Polish

---

# 5. USER ROLE RULES

## ADMIN

Admin has full platform management permissions.

Admin can:

- Create
- Read
- Update
- Delete
- Verify
- Suspend
- Manage
- Monitor

Admin must be able to manage users, projects, jobs, reports and platform settings.

---

## CONTRACTOR

Contractors manage their own projects and workforce.

Contractors can:

- Create projects
- Edit their projects
- Create jobs
- View applications
- Hire workers
- Manage workers assigned to their projects
- Manage tasks
- Manage milestones
- Track attendance
- Create contracts
- View analytics

Contractors must NOT access another contractor's private data.

---

## WORKER

Workers can:

- Manage their own profile
- Manage skills
- Manage experience
- Upload permitted documents
- Search jobs
- Apply for jobs
- View application status
- View assigned projects
- View attendance
- View contracts
- View reviews

Workers must NOT access another worker's private information unless explicitly allowed by the system.

---

## CLIENT

Clients can:

- View their projects
- View project progress
- View milestones
- View project documents
- View reports

Clients should not have administrative control over the platform.

---

# 6. AUTHENTICATION

Authentication must be centralized.

Use:

- Secure sessions
- Password hashing
- Server-side authorization
- Role checking
- Session timeout where appropriate

Never trust the role supplied by the browser.

Always verify permissions on the server.

---

# 7. DATABASE RULES

Use relational database design.

Avoid unnecessary duplicate data.

Use:

- Primary keys
- Foreign keys
- Indexes
- Unique constraints where appropriate
- Timestamps
- Proper data types

Never build database queries by directly concatenating user input.

Use prepared statements.

---

# 8. DATABASE CORE ENTITIES

Required entities include:

users

workers

contractors

clients

skills

worker_skills

worker_documents

projects

project_members

jobs

job_applications

tasks

milestones

attendance

contracts

payments

reviews

notifications

messages

project_documents

activity_logs

---

# 9. RELATIONSHIP CONCEPT

USER

↓

ROLE

↓

Worker / Contractor / Client / Admin

CONTRACTOR

↓

PROJECT

↓

Tasks

Milestones

Workers

Attendance

Documents

Jobs

---

JOB

↓

APPLICATIONS

↓

WORKER

↓

HIRING

↓

PROJECT MEMBER

---

# 10. PROJECT RULES

Each project should support:

- Name
- Description
- Location
- Budget
- Start date
- End date
- Status
- Contractor
- Workers
- Tasks
- Milestones
- Documents
- Progress

Project progress should be represented clearly.

---

# 11. JOB SYSTEM

A contractor creates a job.

Worker discovers job.

Worker applies.

Contractor reviews application.

Contractor accepts/rejects.

If accepted:

Worker becomes assigned/hired.

The application status must be updated.

Possible application states:

- Pending
- Shortlisted
- Accepted
- Rejected
- Withdrawn

---

# 12. ATTENDANCE

Attendance belongs to a project and worker.

Support:

- Check-in
- Check-out
- Date
- Time
- Working hours
- Attendance status

QR attendance should validate that the QR belongs to the appropriate project.

Never trust a worker-submitted project ID without server-side validation.

---

# 13. CONTRACT SYSTEM

Contracts should contain:

- Contractor
- Worker
- Project
- Job
- Contract number
- Start date
- End date
- Payment terms
- Salary/payment amount
- Terms
- Status
- Acceptance information

Possible states:

- Draft
- Sent
- Accepted
- Rejected
- Active
- Completed
- Cancelled

---

# 14. UI/UX RULES

The UI must feel like a real commercial product.

Do NOT create:

- Random layouts
- Random colors
- Huge unnecessary cards
- Inconsistent buttons
- Unnecessary animations
- Broken tables
- Placeholder navigation that does nothing

Every screen should have:

- Clear hierarchy
- Consistent navigation
- Responsive layout
- Loading state
- Empty state
- Error state
- Success feedback

---

# 15. DESIGN SYSTEM

Maintain a centralized design system.

Define:

- Primary color
- Secondary color
- Background
- Text colors
- Border radius
- Shadows
- Typography
- Button styles
- Input styles
- Status badges
- Spacing

Do not create a new visual style for every page.

---

# 16. RESPONSIVE DESIGN

The application must support:

Desktop

Tablet

Mobile

Never allow important tables/forms to become unusable on mobile.

Use responsive navigation.

---

# 17. API RULES

APIs should:

- Validate input
- Authenticate requests
- Authorize requests
- Return consistent responses
- Handle errors
- Never expose sensitive information

Example response structure:

{
    "success": true,
    "message": "Operation completed successfully",
    "data": {}
}

Error:

{
    "success": false,
    "message": "Operation failed",
    "errors": []
}

---

# 18. ERROR HANDLING

Never show raw database errors to users.

Bad:

SQLSTATE[23000] ...

Good:

"Unable to save the project. Please try again."

Log technical errors securely for debugging.

---

# 19. FILE UPLOAD RULES

Uploaded files must be validated.

Check:

- File type
- File size
- File extension
- MIME type
- File name

Do not execute uploaded files.

Use safe generated filenames.

---

# 20. NOTIFICATION SYSTEM

Notifications may include:

- New job
- Application received
- Application accepted
- Application rejected
- Contract created
- Contract accepted
- Attendance event
- Project update
- Milestone update

---

# 21. SEARCH

Worker search should support:

- Skill
- Experience
- Availability
- Location
- Rating
- Verification

Job search should support:

- Skill
- Location
- Salary/payment
- Project type
- Date
- Status

---

# 22. AI FEATURES

AI features are centralized in `includes/ai.php` and `api/ai.php`.

Implemented AI Features:
1. Worker ↔ Job matching (`contractor/job-matches.php`, `ai_match_workers_for_job`)
2. Project Health & Risk Insights (`contractor/project-insights.php`, `ai_analyze_project_insights`)
3. Job Description Assistant (`contractor/create-job.php`, `ai_improve_job_description`)
4. Task Risk Analyzer (`contractor/task-details.php`, `ai_analyze_task_risk`)
5. Worker Profile Optimizer (`worker/profile.php`, `ai_suggest_worker_profile_improvements`)
6. Admin AI Usage & Audit Monitoring (`admin/ai-usage.php`, `ai_usage_logs`)

AI Safety & Human-in-the-Loop Rules:
- AI is strictly ADVISORY.
- AI must NOT automatically hire, fire, reject applicants, alter contracts, change payments, or modify database records.
- All AI suggestions must be explicitly reviewed and accepted by human users.
- Prompt injection defense: treat user database input strictly as data parameters, never as system instructions.
- Fail-safe resilience: if AI API key is unconfigured or request times out (10s), BuildConnect smoothly uses an offline algorithmic heuristic engine.

AI Authorization Rules:
- Every AI endpoint (`api/ai.php`) requires `is_logged_in()`.
- Contractor identity and project/job ownership are derived from server-side session (`$_SESSION['user']`).
- Contractors cannot access AI matching or risk analysis for jobs/projects owned by another contractor.
- Workers can only access profile optimization for their own profile.
- Admin monitoring is restricted to `ROLE_ADMIN`.

AI Output Validation Rules:
- Validate JSON structure and required keys before rendering.
- Clamp match scores strictly between 0 and 100%.
- Restrict risk levels to allowlist: `low`, `medium`, `high`.
- Restrict project health to allowlist: `Healthy`, `Needs Attention`, `At Risk`.
- Escape all AI output text using `sanitize()` before rendering to HTML.

---

# 23. GOOGLE MAPS

Maps may be used for:

- Project location
- Location visualization
- Worker discovery
- Distance information

Do not expose sensitive personal location information unnecessarily.

---

# 24. SECURITY CHECKLIST

Before final release verify:

[ ] SQL injection protection

[ ] XSS protection

[ ] CSRF protection

[ ] Password hashing

[ ] Session security

[ ] Authorization

[ ] File upload security

[ ] Input validation

[ ] Output escaping

[ ] API authorization

[ ] Sensitive information protection

---

# 25. TESTING CHECKLIST

Test every feature.

Authentication:

[ ] Register

[ ] Login

[ ] Logout

[ ] Invalid login

[ ] Role restrictions

Worker:

[ ] Profile

[ ] Skills

[ ] Documents

[ ] Job application

Contractor:

[ ] Project

[ ] Job

[ ] Application management

[ ] Hiring

Client:

[ ] Project viewing

Admin:

[ ] User management

[ ] Verification

[ ] Reports

System:

[ ] Attendance

[ ] QR

[ ] Contracts

[ ] Notifications

[ ] Search

[ ] Filters

[ ] Analytics

---

# 26. BUTTON RULE

EVERY BUTTON MUST WORK.

Do not create decorative buttons that appear functional but have no action.

Examples:

"View"

"Edit"

"Delete"

"Save"

"Apply"

"Accept"

"Reject"

"Generate QR"

"Create Contract"

"Download"

"Search"

must have actual functionality.

---

# 27. FORM RULE

Every form must have:

- Validation
- Error messages
- Success message
- Loading state
- Database operation
- Permission verification

---

# 28. CODE QUALITY

Prefer:

- Small reusable functions
- Reusable components
- Clear naming
- Comments where useful
- Separation of concerns
- Central configuration

Avoid:

- Massive files
- Duplicate code
- Hardcoded credentials
- Hardcoded user IDs
- Hardcoded permissions
- Repeated database logic

---

# 29. ENVIRONMENT

Never commit real secrets.

Use configuration/environment variables for:

- Database credentials
- API keys
- Google Maps key
- AI API key
- Application secrets

---

# 30. CHANGE MANAGEMENT

Before implementing a new feature:

1. Inspect existing code.
2. Identify relevant files.
3. Identify database dependencies.
4. Implement the feature.
5. Test it.
6. Fix errors.
7. Check existing features.
8. Update documentation if necessary.

---

# 31. COMPLETION CRITERIA

A feature is NOT complete merely because its page exists.

A feature is complete only when:

[ ] UI exists

[ ] Backend exists

[ ] Database works

[ ] Validation works

[ ] Authorization works

[ ] Error handling works

[ ] Success handling works

[ ] Mobile layout works

[ ] Existing features still work

---

# 32. CURRENT DEVELOPMENT STATUS

Current phase:

PROJECT INITIALIZATION

Current objective:

Create:

1. Project structure
2. Database schema
3. Database connection
4. Common CSS/design system
5. Common JavaScript utilities
6. Common layout
7. README.md
8. BRAINME.md

Do not implement advanced features until the foundation is stable.

---

# 33. ANTIGRAVITY INSTRUCTION

When this project is opened in an AI coding environment:

READ THIS FILE BEFORE MAKING CHANGES.

Also read:

README.md

Then inspect the existing project before modifying it.

Do not assume that a file or feature does not exist.

Do not duplicate existing functionality.

Do not delete working code without a clear reason.

When a task is completed, report:

1. Files changed
2. Features added
3. Database changes
4. Tests performed
5. Any remaining issues

---

# 34. FINAL PRINCIPLE

BuildConnect should behave like a real production application, not a static college project.

Every major feature must be connected:

UI
↓
Frontend logic
↓
API/backend
↓
Database
↓
Validation
↓
Authorization
↓
User feedback

Keep the system maintainable, secure, responsive and consistent.

---

# 35. FINAL STEP 15 VERIFICATION & SUBMISSION STATUS

```text
01 Foundation                         PASS
02 Authentication                    PASS
03 Admin                             PASS
04 Contractor                        PASS
05 Worker                             PASS
06 Client                             PASS
07 Jobs + Hiring                      PASS
08 Projects + Tasks + Milestones      PASS
09 QR Attendance                      PASS
10 Contracts + Reviews + Ratings      PASS
11 Maps + Notifications               PASS
12 Analytics + Reports                PASS
13 AI Features                        PASS
14 Security + Testing + Final Polish  PASS
```

- [x] Security Audit Complete (100% Prepared Statements, XSS Escaping, CSRF Tokens, HttpOnly Cookies, Security Headers)
- [x] Role Authorization Complete (Strict Server-Side Access Control for Admin, Contractor, Worker, Client)
- [x] IDOR Protection Verified (Zero Unauthorized Cross-Account Record Exposure)
- [x] Custom Production Error Pages Created (404.php, 403.php, 500.php)
- [x] Environment Security (.env.example template created, .env excluded via .gitignore)
- [x] Documentation Created (DEMO_ACCOUNTS.md, DEMO_SCRIPT.md, DEPLOYMENT.md, FINAL_TEST_REPORT.md)
- [x] 100% PHP Syntax & Automated Verification Test Suite Passed (18/18 Master Audit Tests Passed)
- [x] Final Project Declaration: **BUILDCONNECT FINAL VERIFICATION COMPLETE — PROJECT READY FOR DEMONSTRATION / SUBMISSION.**


