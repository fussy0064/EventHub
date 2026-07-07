# EventHub

EventHub is a PHP/MySQL event browsing and booking platform with Tanzanian Shillings (Tshs) currency support, built for deployment on AWS.

## Structure

- `config/` — Database connection wrapper and application helpers
- `classes/` — OOP models for users, events, and bookings
- `public/` — Web-facing pages and shared includes (document root)
- `assets/` — Custom CSS
- `.ebextensions/` — AWS Elastic Beanstalk configuration
- `.platform/` — AWS platform-specific Apache configuration

## Run Locally

1. Install **PHP 8.1+** and **MySQL 8.0+**.
2. Import the database schema:

```bash
mysql -u root -p eventhub < database/schema.sql
```

3. Copy `.env.example` to `.env` and configure your database credentials:

```env
APP_ENV=development
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=eventhub
DB_USER=root
DB_PASSWORD=""
ENCRYPTION_KEY=<your-64-char-hex-key>
```

4. Start the PHP development server:

```bash
php -S 127.0.0.1:8000 -t public
```

5. Open `http://127.0.0.1:8000` in your browser.

---

## Deploy to AWS (Elastic Beanstalk)

### Prerequisites

- An **AWS account** with IAM access
- **AWS CLI** installed and configured (`aws configure`)
- **EB CLI** installed (`pip install awsebcli`)

### Step 1: Create an RDS MySQL Instance

1. Go to **AWS Console → RDS → Create database**
2. Choose **MySQL 8.0** (Free Tier eligible)
3. Set the master username and password
4. Under **Connectivity**, enable **Public access** (for initial setup only) or keep it in the same VPC as your EB environment
5. Note down the **Endpoint**, **Port**, **Username**, **Password**, and **Database name**

### Step 2: Import the Schema to RDS

```bash
mysql -h <rds-endpoint> -u <username> -p <database-name> < database/schema.sql
```

### Step 3: Initialize Elastic Beanstalk

```bash
cd /path/to/EventHub
eb init

# Select your region (e.g., us-east-1 or af-south-1 for Africa)
# Platform: PHP 8.1
# Application name: EventHub
```

### Step 4: Set Environment Variables

```bash
eb setenv \
  APP_ENV=production \
  RDS_HOSTNAME=<your-rds-endpoint> \
  RDS_PORT=3306 \
  RDS_DB_NAME=eventhub \
  RDS_USERNAME=<your-db-username> \
  RDS_PASSWORD=<your-db-password> \
  ENCRYPTION_KEY=<your-64-char-hex-key>
```

### Step 5: Create and Deploy

```bash
eb create eventhub-production --single
```

Or deploy to an existing environment:

```bash
eb deploy
```

### Step 6: Open the Application

```bash
eb open
```

### Step 7: Set Up HTTPS (Recommended)

1. Go to **AWS Certificate Manager** → Request a certificate for your domain
2. In **EB Console → Configuration → Load Balancer**, add an HTTPS listener on port 443 with the certificate
3. The `.htaccess` will automatically redirect HTTP to HTTPS

---

## Environment Variables Reference

| Variable | Description | Example |
|---|---|---|
| `APP_ENV` | Application environment | `production` or `development` |
| `RDS_HOSTNAME` | AWS RDS endpoint | `mydb.abc123.us-east-1.rds.amazonaws.com` |
| `RDS_PORT` | Database port | `3306` |
| `RDS_DB_NAME` | Database name | `eventhub` |
| `RDS_USERNAME` | Database username | `admin` |
| `RDS_PASSWORD` | Database password | `your-secure-password` |
| `ENCRYPTION_KEY` | 64-char hex key for AES-256 | `e6ad1931eb...` |

For local development, use `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` in your `.env` file.

---

## Recent Fixes

- **Fixed:** Deleting a user with existing events/bookings used to fail (foreign key error). Delete now cleans up related events/bookings in one transaction.
- **Improved:** Admin Approve / Reject / Delete now update the table instantly (AJAX) — no more full page reload.
- **Added:** Admin can now create Events (previously organizer-only) and create Users directly (Attendee/Organizer, auto-approved, no approval queue).
- **Changed:** All prices now show as "Tshs" instead of "$".
- **Added:** Admin dashboard shows a full list of every event and who created it.
- **Added:** Attendees can print a ticket (Dashboard → Print Ticket) after booking.
- **Added:** Organizers can book a ticket for an attendee (by email) directly from the event listing page.
- **Added:** New bookings start as "Pending Payment" — Organizer must click "Confirm Payment" before the ticket becomes valid/printable.
- **Added:** Event description now shows to Attendees on the Browse Events page.
- **Added:** Sidebar menu now includes Logout.
- **Added:** Ticket ID shown everywhere as `EHI-{EventID}-{BookingID}`.

## Currency

All prices are displayed in **Tanzanian Shillings (Tshs)**.

## Notes

- The project uses **Bootstrap 5.3** for the UI
- Data at rest is encrypted with **AES-256-CBC**
- Sessions use **secure cookies** in production
- HTTPS is enforced via `.htaccess` when behind an AWS ELB
