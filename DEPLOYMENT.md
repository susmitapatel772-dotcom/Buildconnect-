# BuildConnect — Production Deployment Guide

This document outlines the operational requirements, server configuration, database setup, environment configuration, security hardening, and deployment checklist for hosting BuildConnect in a production environment.

---

## 1. System & Server Requirements

### Core Requirements
- **Operating System:** Linux (Ubuntu 22.04 LTS / Debian 12 recommended) or Windows Server 2022.
- **Web Server:** Apache 2.4+ (with `mod_rewrite` enabled) or Nginx 1.20+.
- **PHP Version:** PHP 8.1 / 8.2 / 8.3 (64-bit).
- **Database Engine:** MySQL 8.0+ or MariaDB 10.6+ (InnoDB storage engine default).
- **RAM:** Minimum 2 GB (4 GB+ recommended for production).
- **Disk Space:** Minimum 10 GB SSD space (additional storage required for worker document uploads).

### Required PHP Extensions
Ensure the following PHP extensions are installed and enabled in `php.ini`:
- `pdo_mysql` (Database connectivity)
- `mbstring` (Multibyte string handling)
- `json` (JSON parsing and responses)
- `gd` or `imagick` (Image upload thumbnailing / avatar processing)
- `openssl` (Secure token generation and HTTPS support)
- `fileinfo` (MIME type validation for file uploads)
- `curl` (External API communication for AI / Maps integrations)
- `session` (HTTP Session management)

---

## 2. Database Installation & Setup

1. **Create Database:**
   ```sql
   CREATE DATABASE buildconnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Create Database User & Grant Privileges:**
   ```sql
   CREATE USER 'buildconnect_user'@'localhost' IDENTIFIED BY 'Strong_Production_Password_982!';
   GRANT ALL PRIVILEGES ON buildconnect.* TO 'buildconnect_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

3. **Import Database Schema:**
   ```bash
   mysql -u buildconnect_user -p buildconnect < database/schema.sql
   ```

4. **Import Initial Seed Data (Optional for testing, omit for clean production):**
   ```bash
   mysql -u buildconnect_user -p buildconnect < database/seed.sql
   ```

---

## 3. Environment Configuration (`.env`)

1. Copy `.env.example` to `.env` in the project root directory:
   ```bash
   cp .env.example .env
   ```

2. Configure production environment variables inside `.env`:
   ```ini
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://yourdomain.com

   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=buildconnect
   DB_USER=buildconnect_user
   DB_PASSWORD=Strong_Production_Password_982!

   SESSION_SECURE=true
   SESSION_HTTPONLY=true

   GOOGLE_MAPS_API_KEY=YOUR_PRODUCTION_GOOGLE_MAPS_API_KEY
   AI_API_KEY=YOUR_OPTIONAL_GEMINI_OR_OPENAI_KEY
   ```

---

## 4. File Permissions & Directory Security

Set strict file and directory permissions on Linux servers:

```bash
# Set base ownership (replace www-data with your web server user)
sudo chown -R www-data:www-data /var/www/buildconnect

# Set standard permissions: 755 for directories, 644 for files
sudo find /var/www/buildconnect -type d -exec chmod 755 {} \;
sudo find /var/www/buildconnect -type f -exec chmod 644 {} \;

# Secure uploads directory (write access for web server, block execution)
sudo chmod -R 775 /var/www/buildconnect/uploads

# Prevent code execution inside uploads directory (.htaccess for Apache)
cat << 'EOF' > /var/www/buildconnect/uploads/.htaccess
<FilesMatch "\.(php|php5|php7|php8|phtml|exe|pl|py|cgi)$">
    Order Deny,Allow
    Deny from all
</FilesMatch>
Options -ExecCGI -Indexes
EOF
```

---

## 5. Security & HTTPS Hardening

### HTTPS Requirement
- Configure SSL/TLS certificates using Let's Encrypt (Certbot) or a commercial SSL provider.
- Redirect all HTTP traffic to HTTPS.

### PHP Production Error Settings
Ensure PHP does not display runtime errors or stack traces to end users in production. In `php.ini`:
```ini
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php/buildconnect_errors.log
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
```

### Session Security Configurations
BuildConnect enforces secure session cookie attributes in `includes/functions.php`:
- `session.cookie_httponly = 1` (Prevents client-side JS session hijacking)
- `session.cookie_secure = 1` (Transmits cookies over HTTPS only)
- `session.cookie_samesite = Lax` (Protects against cross-site request forgery)
- `session.use_strict_mode = 1` (Prevents session fixation attacks)

---

## 6. External API Integrations

### Google Maps API
1. Obtain an API Key from Google Cloud Console.
2. Enable **Maps JavaScript API** and **Geocoding API**.
3. Restrict the API Key by HTTP referrer (`https://yourdomain.com/*`).
4. Set `GOOGLE_MAPS_API_KEY` in `.env`.

### AI Worker Matching & Project Insights
- BuildConnect includes a native, deterministic rule-based AI worker matching engine that operates locally without external API dependencies.
- Optional external AI APIs (e.g. Gemini API) can be enabled by specifying `AI_API_KEY` in `.env`.

---

## 7. Cron & Scheduled Maintenance (Optional)

To automatically clean up expired attendance session tokens and unread notification logs, add a daily cron job:

```cron
0 2 * * * php /var/www/buildconnect/scratch/cron_cleanup.php >/dev/null 2>&1
```

---

## 8. Backup & Recovery Protocol

### Daily Database Backup Script
```bash
#!/bin/bash
BACKUP_DIR="/var/backups/buildconnect"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p $BACKUP_DIR
mysqldump -u buildconnect_user -p'Strong_Production_Password_982!' buildconnect | gzip > "$BACKUP_DIR/db_backup_$DATE.sql.gz"
find $BACKUP_DIR -type f -name "*.sql.gz" -mtime +30 -delete
```

---

## 9. Final Deployment Verification Checklist

- [x] Web server running on PHP 8.1+ with required extensions enabled.
- [x] MySQL database created and `schema.sql` imported cleanly.
- [x] `.env` created with production database credentials and `APP_DEBUG=false`.
- [x] `.env` excluded from version control via `.gitignore`.
- [x] Upload directory permissions configured (775) with script execution disabled.
- [x] SSL/TLS certificate installed and HTTP-to-HTTPS redirect active.
- [x] PHP `display_errors` disabled in production.
- [x] All 14 phases verified functional and secure.
- [x] Test accounts documented in `DEMO_ACCOUNTS.md`.
