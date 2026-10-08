# BuildConnect 🏗️

> **Centralized Construction Workforce & Project Management Platform**

BuildConnect connects Construction Workers, Contractors, Clients, and Administrators in one unified system. It simplifies construction workforce hiring, project tracking, attendance, contracts, and platform analytics.

---

## 👥 Demo User Accounts (Development)

All demo accounts come pre-configured in `database/seed.sql` with the default password: `password123`

| User Role | Demo Email Address | Description |
|---|---|---|
| **Admin** | `admin@buildconnect.com` | Complete platform control, user management, worker verification. |
| **Contractor** | `contractor@buildconnect.com` | Apex Builders Inc., project creation, job postings, workforce management. |
| **Worker** | `worker@buildconnect.com` | Marcus Vance (Structural Welder / Crane Operator), profile, applications. |
| **Client** | `client@buildconnect.com` | Skyline Urban Developers, project oversight, milestones. |

---

## 🛠️ Technology Stack

- **Backend**: PHP 7.4+ / 8.x
- **Database Engine**: MySQL / MariaDB via PDO (InnoDB, `utf8mb4`)
- **Frontend Architecture**: HTML5, CSS3 (Custom Design System), JavaScript (ES6+)
- **UI Framework**: Bootstrap 5.3 + FontAwesome 6 Icons
- **Interactive Maps**: Leaflet.js with fallback coordinates (Ahmedabad, Gujarat, India: `23.0225, 72.5714`)

---

## 🔒 Security Warnings & Production Best Practices

- **Never Commit Secrets:** Never commit the `.env` file or database credentials to public source repositories. Use `.env.example` as a template.
- **Rotate Demo Passwords:** Development demo passwords (`password123`) must be changed before deploying to production environments.
- **HTTPS Requirement:** Deploy on web servers configured with SSL/TLS certificates (HTTPS only).
- **API Key Security:** Restrict Google Maps API keys by HTTP referrer (`https://yourdomain.com/*`). Keep AI API keys server-side.
- **Secure Sessions:** Configure `session.cookie_httponly = 1`, `session.cookie_secure = 1`, and `session.cookie_samesite = Lax`.
- **Database Backups:** Schedule automated daily `mysqldump` database backups in production environments.


---

## 🔐 Google Sign-In Setup (OAuth 2.0)

BuildConnect supports secure Google OAuth 2.0 authentication for seamless user sign-in.

### Configuration Steps:
1. Go to the [Google Cloud Console](https://console.cloud.google.com/) and create a new project.
2. Navigate to **APIs & Services > Credentials** and click **Create Credentials > OAuth client ID**.
3. Select **Web application** as the application type.
4. Add your authorized JavaScript origins (e.g., `http://localhost:8000`).
5. Add the authorized redirect URI: `http://localhost:8000/auth/google-callback.php` (or your domain's callback URL).
6. Copy the generated **Client ID** and **Client Secret**.
7. Create or edit your `.env` file in the project root and populate the credentials:
   ```env
   GOOGLE_CLIENT_ID=your_actual_google_client_id
   GOOGLE_CLIENT_SECRET=your_actual_google_client_secret
   GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google-callback.php
   ```
8. Reload the application and click **Continue with Google** on the login page.

---

## 💻 Quick Setup & Execution

### 1. Database Setup (MySQL)
1. Ensure your MySQL server (e.g. XAMPP, WAMP, or standalone MySQL) is running.
2. Create a database named `buildconnect`:
   ```sql
   CREATE DATABASE buildconnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Import the schema and seed data:
   ```bash
   mysql -u root -p buildconnect < database/schema.sql
   mysql -u root -p buildconnect < database/seed.sql
   ```
4. Verify your MySQL credentials in `config/constants.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'buildconnect');
   define('DB_DRIVER', 'mysql');
   ```

### 2. Running Locally
Run the built-in PHP server from the project directory:
```bash
php -S localhost:8000
```
Open `http://localhost:8000` in your web browser.

---

## 🤖 AI Engine Features & Configuration (Step 13)

BuildConnect incorporates a centralized AI intelligence layer (`includes/ai.php` and `api/ai.php`) designed to assist contractors, workers, and administrators.

### 🌟 Key AI Capabilities
1. **AI Worker-Job Matching (`contractor/job-matches.php`)**: Evaluates candidate pools against job trade requirements, experience, availability, verification, and ratings. Generates structured match scores (0-100%), strengths, considerations, and recommendation badges.
2. **AI Project Risk Insights (`contractor/project-insights.php`)**: Evaluates project task deadlines, milestone target schedules, and site progress to diagnose project health (`Healthy`, `Needs Attention`, `At Risk`).
3. **AI Job Description Assistant (`contractor/create-job.php`)**: Assists contractors by expanding job titles and trade skills into detailed descriptions, responsibilities, and requirements.
4. **AI Task Risk Analyzer (`contractor/task-details.php`)**: Assesses task completion risk based on due dates, priority, and progress.
5. **AI Worker Profile Assistant (`worker/profile.php`)**: Analyzes worker bio, skills count, experience entries, and document verification to suggest profile optimizations.
6. **Admin AI Usage & Audit Monitor (`admin/ai-usage.php`)**: Monitors AI request volumes, latency, success/failure counts, and detailed audit logs.

### ⚙️ AI Configuration Parameters (`config/constants.php`)
```php
define('AI_ENABLED', getenv('AI_ENABLED') !== 'false');
define('AI_API_KEY', getenv('AI_API_KEY') ?: '');
define('AI_API_URL', getenv('AI_API_URL') ?: 'https://api.openai.com/v1/chat/completions');
define('AI_MODEL', getenv('AI_MODEL') ?: 'gpt-4o-mini');
define('AI_TIMEOUT_SECONDS', 10);
define('AI_CACHE_TTL_HOURS', 24);
```

### 🛡️ AI Safety, Privacy & Limitations
- **Human-in-the-Loop**: AI recommendations are strictly advisory. AI **never** automatically hires, fires, rejects applicants, alters contracts, or modifies database state.
- **Privacy Controls**: Sensitive attributes (passwords, tokens, payment credentials, personal characteristics) are **never** transmitted to external AI endpoints.
- **Fail-Safe Resilience**: If no API key is configured or an API request times out, BuildConnect automatically falls back to an offline algorithmic heuristic engine without crashing or interrupting platform features.

---

## 📁 Directory Structure

```text
BuildConnect/
│
├── README.md
├── BRAINME.md
│
├── index.php
├── login.php
├── register.php
├── logout.php
├── notifications.php
│
├── config/
│   ├── database.php
│   └── constants.php
│
├── includes/
│   ├── header.php
│   ├── footer.php
│   ├── navbar.php
│   ├── sidebar.php
│   ├── auth.php
│   ├── functions.php
│   ├── analytics-functions.php
│   └── ai.php
│
├── admin/
│   ├── analytics.php
│   ├── ai-usage.php
│   └── ...
├── contractor/
│   ├── job-matches.php
│   ├── project-insights.php
│   └── ...
├── worker/
│   ├── analytics.php
│   └── ...
├── client/
│   ├── analytics.php
│   └── ...
│
├── api/
│   ├── ai.php
│   ├── analytics.php
│   ├── export-report.php
│   └── attendance.php
│
---

## 🏆 BuildConnect Platform Status & Production Readiness

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

**BUILDCONNECT FINAL VERIFICATION COMPLETE — PROJECT READY FOR DEMONSTRATION / SUBMISSION.** 🚀

