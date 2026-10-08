# BuildConnect — Demo Backup Plan & Emergency Fallback Guide

This document details step-by-step contingency procedures for handling technical glitches during a live demonstration or presentation.

---

## 1. Overview of Fallback Philosophy
BuildConnect has been engineered with native local fallbacks. If an external service (Internet, Google Maps, AI API) becomes unavailable during a presentation, the application smoothly transitions to offline/local functionality without crashing or displaying raw errors.

---

## 2. Emergency Contingency Matrix

| Potential Issue | Root Cause | Fallback Action & Workaround |
| :--- | :--- | :--- |
| **Database Connection Failure** | MySQL service stopped in XAMPP / Control Panel. | 1. Open XAMPP Control Panel or terminal.<br>2. Start MySQL service (`net start MySQL` or click Start).<br>3. Refresh browser page (`Ctrl + F5`). |
| **Internet Unavailable** | Venue Wi-Fi dropped or disconnected. | BuildConnect runs 100% locally on `localhost:8000`. Leaflet map fallback tiles and local AI matching continue to work offline without internet. |
| **Google Maps API Fails to Load** | Missing API key, quota limit, or offline network. | Leaflet.js automatically renders using fallback local tiles and pre-configured coordinates (`23.0225, 72.5714`). Map container remains fully functional. |
| **AI API Times Out or Fails** | Unconfigured `AI_API_KEY` or remote timeout. | BuildConnect automatically invokes its local rule-based heuristic matching engine in `includes/ai.php`. Match scores (0–100%) render instantly without external calls. |
| **Demo Account Login Fails** | Password mistyped or user status altered. | Re-import seed data cleanly: `mysql -u root -p buildconnect < database/seed.sql`. All demo passwords reset to `password123`. |
| **QR Feature / Camera Access Issue** | Browser permission blocked or webcam missing. | Use the manual token entry field on `/worker/attendance.php` and input token `BC-PROJ-AHMEDABAD-X892` to log check-in instantly. |
| **Browser Cache / Stale Session** | Session cookie conflict from previous role. | 1. Use Chrome Incognito / Private Window (`Ctrl + Shift + N`).<br>2. Or click **Logout** in top right navbar before switching roles. |
| **Unexpected PHP Error / 500 Page** | File permission or temporary script issue. | Custom error handler captures exception and renders clean `500.php` page. Review log in `scratch/` or restart local PHP server: `php -S localhost:8000`. |

---

## 3. Fast System Recovery Commands

If a catastrophic database or server glitch occurs, execute these 3 quick commands in terminal:

```powershell
# 1. Restart Local PHP Development Server
php -S localhost:8000

# 2. Re-import Clean Seed Data (Resets all demo accounts & records)
& "D:\xampp app\mysql\bin\mysql.exe" -u root buildconnect < e:\BuildConnect\database\seed.sql

# 3. Clear Browser Session Cookies
Open Incognito Window (Ctrl + Shift + N) -> Navigate to http://localhost:8000
```

---

## 4. Safe Demonstration Guidelines
- Always verify MySQL and PHP server are active 5 minutes before presentation.
- Keep `DEMO_ACCOUNTS.md` open on a reference cheat-sheet.
- Log out cleanly between switching roles (Contractor → Worker → Client → Admin).
