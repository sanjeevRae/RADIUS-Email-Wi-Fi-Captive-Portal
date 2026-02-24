# RADIUS-Email-Wi-Fi-Captive-Portal

A complete, production-ready captive portal solution that combines FreeRADIUS authentication with Email OTP verification, enabling secure Wi-Fi access through MikroTik routers. Users authenticate using their email address, receive a one-time password (OTP) via email through **EmailJS**, and gain temporary internet access.

---

## ✨ Features

- 📧 **Email OTP Authentication** – Users log in with their email address and receive a one-time password via EmailJS.
- 🔐 **FreeRADIUS Integration** – Robust RADIUS server on Ubuntu for authentication, authorization, and accounting.
- 🖧 **MikroTik Hotspot Gateway** – Industry-standard router handles captive portal redirection and user sessions.
- 📊 **Session Management** – Temporary user accounts with configurable session timeouts and bandwidth limits.
- 🌐 **Walled Garden** – Allows unauthenticated access to captive portal pages, EmailJS CDN, and API endpoints.
- 🔄 **PAP Authentication** – Secure communication between MikroTik and FreeRADIUS over LAN.
- 🛡️ **Rate Limiting** – Maximum 5 OTP verification attempts per session before lockout.
- ⏱️ **OTP Expiry** – OTPs expire after 5 minutes for security.

---

## 🏗 Architecture

```
┌─────────────┐      ┌───────────────┐      ┌─────────────────┐
│ User Device │ <──> │ MikroTik      │ <──> │ FreeRADIUS      │
│ (Wi-Fi)     │      │ Hotspot       │      │ (Ubuntu Server) │
└─────────────┘      └───────────────┘      └─────────────────┘
                             │                         │
                             │ (RADIUS)                │ (MySQL)
                             ▼                         ▼
                      ┌───────────────┐      ┌─────────────────┐
                      │ EmailJS       │      │ MySQL Database  │
                      │ (api.emailjs  │      │ (Users, OTPs)   │
                      │  .com)        │      │                 │
                      └───────────────┘      └─────────────────┘
                             ▲                         ▲
                             │                         │
                             └──────────┬──────────────┘
                                        │
                              ┌─────────▼─────────┐
                              │ Captive Portal    │
                              │ Web Server        │
                              │ (Apache/PHP)      │
                              └───────────────────┘
```

---

## 🔧 Technology Stack

| Component     | Technology                         | Purpose                                                      |
|---------------|------------------------------------|--------------------------------------------------------------|
| RADIUS Server | FreeRADIUS 3.x on Ubuntu 22.04 LTS | Core authentication engine                                   |
| Router/AP     | MikroTik (RouterOS v7+)            | Hotspot gateway and captive portal                           |
| Email Gateway | EmailJS (api.emailjs.com)          | OTP delivery to users via email                              |
| Database      | MariaDB (MySQL compatible)         | Store users, OTPs, sessions, RADIUS data, audit log          |
| Web Server    | Apache/Nginx + PHP                 | Host captive portal pages                                    |
| Backend       | PHP + JavaScript (EmailJS SDK)     | OTP generation, email delivery & validation logic            |

---

## 📋 Prerequisites

- **Ubuntu 22.04 Server** – Static IP (e.g., `172.16.20.40`)
- **MikroTik Router** – With Hotspot feature and two Ethernet ports
- **EmailJS Account** – Free or paid account at [emailjs.com](https://www.emailjs.com) with:
  - A configured **Email Service** (e.g., Gmail)
  - An **Email Template** with `to_email`, `otp_code`, and `message` variables
  - Your **Public Key**
- Basic knowledge of Linux, MySQL, and RouterOS

---

## 🔌 Installation & Configuration

### 1. FreeRADIUS Server (Ubuntu)

Install packages and configure MariaDB:

```bash
sudo apt update && sudo apt install -y freeradius freeradius-mysql mariadb-server
sudo mysql_secure_installation
```

Create database, user, and import the complete schema (FreeRADIUS tables + OTP audit log) using the included [`db_setup.sql`](db_setup.sql):

```bash
sudo mysql -u root -p < db_setup.sql
```

Or run the statements manually:

```sql
CREATE DATABASE radius CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'radius'@'localhost' IDENTIFIED BY 'YourPassword';
GRANT ALL PRIVILEGES ON radius.* TO 'radius'@'localhost';
FLUSH PRIVILEGES;
USE radius;
SOURCE db_setup.sql;
```

Enable SQL module (symlink) and configure `/etc/freeradius/3.0/clients.conf` with your MikroTik IP and secret:

```bash
sudo ln -s /etc/freeradius/3.0/mods-available/sql \
           /etc/freeradius/3.0/mods-enabled/sql
```

In `/etc/freeradius/3.0/mods-enabled/sql`, set:

```
dialect = "mysql"
server  = "localhost"
port    = 3306
login   = "radius"
password = "YourPassword"
radius_db = "radius"
```

---

### 2. EmailJS Setup

1. Sign up at [emailjs.com](https://www.emailjs.com)
2. Go to **Email Services** → Add a new service (e.g., Gmail) → get your **Service ID**
3. Go to **Email Templates** → Create a template using these variables:
   - `{{to_email}}` – recipient's email address
   - `{{otp_code}}` – the 4-digit OTP
   - `{{message}}` – fallback message text
4. Note your **Template ID** and **Public Key** from the dashboard

Update these values at the top of `emailotp.php`:

```php
$emailjs_service_id  = "your_service_id";
$emailjs_template_id = "your_template_id";
$emailjs_public_key  = "your_public_key";
```

---

### 3. MikroTik Router Setup

Assign WAN IP on `ether1`, create LAN bridge with IP `192.168.1.1/24`.

Enable Hotspot on the bridge:

```
/ip hotspot profile add name=hsprof1 hotspot-address=192.168.1.1
/ip hotspot add interface=bridge_lan profile=hsprof1
```

Add RADIUS client pointing to your Ubuntu server:

```
/radius add address=172.16.20.40 secret=testing123 service=hotspot
/ip hotspot profile set [find] use-radius=yes login-by=http-pap
```

---

### 4. Walled Garden Configuration

**Critical:** EmailJS requires access to its CDN and API before the user is authenticated. Add both to the walled garden:

```
/ip hotspot walled-garden ip add dst-host=cdn.jsdelivr.net action=allow
/ip hotspot walled-garden ip add dst-host=api.emailjs.com action=allow
/ip hotspot walled-garden ip add dst-host=*.emailjs.com action=allow
/ip hotspot walled-garden ip add dst-host=172.16.20.40 action=allow
/ip hotspot walled-garden ip add dst-host=8.8.8.8 action=allow
```

> **Note:** If `cdn.jsdelivr.net` or `api.emailjs.com` are not in the walled garden, the portal will display a clear error instructing the admin to add them.

---

### 5. Captive Portal Web Pages

Place the PHP file in `/var/www/html/` on your Ubuntu server:

- `emailotp.php` – Collects email address, generates OTP, sends via EmailJS, validates OTP, and submits login to MikroTik

Replace the `login.html` in your **MikroTik Files** section:

- `login.html` – Immediately redirects users to `emailotp.php` on the Ubuntu server (`172.16.20.40`)

To upload `login.html` to MikroTik:

```
/tool fetch url="http://172.16.20.40/login.html" dst-path=hotspot/login.html
```

---

## 🔄 Authentication Flow

The sequence diagram below illustrates the complete authentication process from user connection to internet access:

![forgit](https://github.com/sanjeevRae/RADIUS-Email-Wi-Fi-Captive-Portal/blob/main/Sequence%20Diagram.png)

**Flow Steps:**

1. User connects to Wi-Fi and is redirected to `login.html` on MikroTik
2. `login.html` immediately redirects to `emailotp.php` on the Ubuntu server
3. User enters their email address → 4-digit OTP generated and stored in session
4. EmailJS SDK (loaded from `cdn.jsdelivr.net`) sends the OTP to the user's email via `api.emailjs.com`
5. User submits OTP → validated against session (5-minute TTL, max 5 attempts)
6. On success: temporary RADIUS user created; login form auto-submitted to MikroTik
7. MikroTik sends RADIUS Access-Request (PAP) to FreeRADIUS
8. FreeRADIUS authenticates and returns Access-Accept with session attributes
9. User gains internet access for the session duration

---

## 📌 Key Configuration Concepts

### EmailJS Client-Side Sending

- OTP is generated in PHP and stored in the session
- The EmailJS JavaScript SDK (`cdn.jsdelivr.net`) handles actual email delivery
- PHP injects the Service ID, Template ID, Public Key, email, and OTP into the page
- No server-side SMTP configuration needed — EmailJS handles delivery

### PAP Authentication

- MikroTik uses PAP to send credentials to FreeRADIUS
- FreeRADIUS expects `Cleartext-Password` attribute in the `radcheck` table
- The user's email is the username; the OTP is the password
- Enable `login-by=http-pap` in MikroTik hotspot profile

### RADIUS Server Profile on MikroTik

- Defined under `/radius` with server IP and shared secret
- Hotspot profile must have `use-radius=yes`

### IP Profile & Bandwidth Management

Control session duration and bandwidth via RADIUS attributes:

```sql
INSERT INTO radreply (username, attribute, op, value) VALUES
('user@example.com', 'Session-Timeout',     ':=', '3600'),
('user@example.com', 'MikroTik-Rate-Limit', ':=', '2M/2M');
```

---

## 🗄️ MariaDB Schema

All tables are defined and ready to import via [`db_setup.sql`](db_setup.sql).

### FreeRADIUS Tables

| Table              | Purpose                                                                    |
|--------------------|----------------------------------------------------------------------------|
| `radcheck`         | Per-user authentication checks (OTP stored as `Cleartext-Password`)        |
| `radreply`         | Per-user reply attributes returned after auth                              |
| `radgroupcheck` / `radgroupreply` | Group-level checks and replies                          |
| `radusergroup`     | User ↔ group mappings                                                      |
| `radacct`          | Full accounting records (session start/stop, bytes)                        |
| `radpostauth`      | Post-auth log – Accept or Reject per attempt                               |
| `nas`              | Registered NAS devices (MikroTik routers)                                  |

### Custom OTP Audit Table (`otp_log`)

Every OTP lifecycle event is automatically written by `emailotp.php`:

| `event` value | Triggered when                                                          |
|---------------|-------------------------------------------------------------------------|
| `requested`   | User submits email, OTP generated & EmailJS send initiated              |
| `verified`    | Correct OTP entered; MikroTik login form submitted                      |
| `failed`      | Wrong OTP entered (each attempt logged separately)                      |
| `expired`     | OTP TTL (5 min) exceeded before the correct OTP was entered             |

Useful audit query:

```sql
-- OTP activity for the last 24 hours
SELECT email, event, ip_address, mikrotik_ip, created_at
FROM   otp_log
WHERE  created_at >= NOW() - INTERVAL 1 DAY
ORDER  BY created_at DESC;
```

---

## 🧪 Testing

```bash
# Test RADIUS locally on Ubuntu
radtest testuser testpass 127.0.0.1 0 testing123

# Test from MikroTik CLI
/tool radius simulate hotspot user=testuser password=testpass address=192.168.1.100

# Run FreeRADIUS in debug mode to see live auth attempts
sudo systemctl stop freeradius
sudo freeradius -X
```

**Full flow test:**
1. Connect a client device to the MikroTik Wi-Fi
2. Open a browser — you should be redirected to the portal
3. Enter a valid email address and click **Send OTP**
4. Check your inbox for the 4-digit OTP (check spam if not received)
5. Enter the OTP and verify you gain internet access

---

## 🐞 Troubleshooting

| Issue                          | Solution                                                                 |
|-------------------------------|--------------------------------------------------------------------------|
| RADIUS no response             | `systemctl status freeradius` – check process and secrets match          |
| OTP email not received         | Verify EmailJS credentials; check spam folder                            |
| "EmailJS library failed to load" | Add `cdn.jsdelivr.net` to MikroTik walled garden                      |
| "Connection blocked by firewall" | Add `api.emailjs.com` and `*.emailjs.com` to walled garden            |
| "Invalid grant" error          | Gmail OAuth expired – reconnect Gmail in EmailJS dashboard               |
| 412 error from EmailJS         | Check Service ID, Template ID, and Public Key are correct                |
| Cannot reach portal            | Review walled garden rules; ensure `172.16.20.40` is allowed            |
| Authentication fails           | Ensure `Cleartext-Password` exists in `radcheck` for the email user      |
| OTP expired message            | OTP TTL is 5 minutes – user must request a new OTP                       |
| Too many attempts lockout      | Session locked after 5 wrong attempts – user must restart from email step |

---

## 🔒 Security Notes

- Change the default RADIUS secret (`testing123`) to a strong random string
- Change default database passwords
- Use HTTPS for the captive portal (Let's Encrypt with Certbot)
- OTPs expire after **5 minutes** automatically
- Maximum **5 failed OTP attempts** per session before lockout
- Restrict FreeRADIUS access to MikroTik IP only via UFW:
  ```bash
  sudo ufw allow from <MikroTik_IP> to any port 1812 proto udp
  sudo ufw allow from <MikroTik_IP> to any port 1813 proto udp
  ```
- EmailJS public key is exposed client-side — this is by design (EmailJS is a client-side service). Use EmailJS domain restrictions in the dashboard to limit which origins can use your key.
- Regularly rotate EmailJS API keys and reconnect email services

---

## 📁 File Structure

```
/var/www/html/
└── emailotp.php          ← Main captive portal (email input + OTP send/verify)

MikroTik Files (hotspot/):
└── login.html            ← Redirect page (auto-redirects to emailotp.php)
```

---

## 📄 License

This project is open-source under the [MIT License](LICENSE).
