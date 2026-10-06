# S-Five Inland Resort — Complete Booking System
## PHP + MySQL | Manual GCash Payment | Admin Panel

### Step 1 — Create the Database
- Open **phpMyAdmin**
- Import `sfive_resort_DB.sql`
- This creates the `sfive_resort` database + all tables + sample cottages

### Step 2 — Configure `includes/config.php`
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sfive_resort');
define('SITE_URL', 'http://localhost/sfive');

// Brevo (email) — get a free key at https://app.brevo.com/settings/keys/api
define('BREVO_API_KEY', 'your-brevo-api-key');
define('BREVO_SENDER_EMAIL', 'your-verified-sender@example.com');
```

### Step 3 — Place Files
Copy `sfive/` into your web root:
- **XAMPP**: `C:/xampp/htdocs/sfive/`

### Step 4 — Open in Browser
http://localhost/sfive/

**Admin Panel:** http://localhost/sfive/admin/login.php

---

## GCash Payment (Manual)

Guests pay via GCash by scanning a QR code and uploading proof of payment, which staff verify from the admin panel. Manage the QR code from **Admin > Settings > GCash QR Code**:
1. Upload a **JPEG (.jpg)** photo/screenshot of your GCash QR (max 5MB)
2. Set the **GCash Account Name** shown under it
3. Save — the booking page updates immediately, no code changes needed

This is stored in the `gcash_settings` table (included in `sfive_resort_DB.sql`).

---

## Cottage Types

| Type | Features |
|------|---------|
| Cottage - Kids Pool | Fan, veranda, garden |
| Cottage - Adult Pool | Fan, veranda, garden |
| Pavilion | Large group / event space, per-event pricing |
| Rooms | Aircon, good beds |

---

## Features

**Customer Side**
- Homepage with live availability checker
- Clickable cottage thumbnails → detail page with photo gallery + lightbox
- Reservation form with real-time price calculator
- Manual GCash payment (QR + proof upload) or Pay on Arrival
- Booking confirmation + track booking page

**Admin Panel**
- Dashboard with stats + monthly bookings chart
- Reservation management (approve/reject/cancel)
- Cottage management (add/edit/delete/hide)
- Guest records
- Monthly revenue reports (printable)
- GCash payment verification panel

---

## Requirements
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10+
- XAMPP / WAMP / any PHP server
- cURL (for the Brevo email API)