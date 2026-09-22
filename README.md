# UniMarket - Verified Campus Student Marketplace

UniMarket is a full-stack campus student marketplace built with **Laravel 13**, **Livewire 3**, **Alpine.js**, **Tailwind CSS**, and **Filament v3**. It enables students to buy, sell, message, and track transactions through a secure 4-stage escrow lifecycle with AI-assisted dispute moderation.

---

## Design System & Typography

- **Typography**: Google Sans (`400`–`700`), configured once in `resources/css/app.css` (`--font-sans`).
- **Colour tokens** (Tailwind v4 `@theme` in `resources/css/app.css`, used as `bg-brand-700`, `text-accent-700`, ...):
  - **Neutral base**: `surface` `#F8FAFC` background, `ink` `#0F172A` body text, slate borders.
  - **Brand** (deep indigo, `brand-50`–`brand-950`; `brand-900` is `#312E81`): primary buttons (`brand-700`), links, active states and focus rings.
  - **Accent** (emerald, `accent-*`): prices, verified badges, success states.
  - **Warn** (amber, `warn-*`): reserved / inspection / disputed. **Danger** (red, `danger-*`): errors and destructive actions. **Info** (sky, `info-*`): neutral notices.
  - Text colours use the `-700`/`-800` steps so they keep at least 4.5:1 contrast on white.
- **Shared classes** (`@layer components`): `.btn` (+ `-primary`, `-secondary`, `-success`, `-warning`, `-danger`), `.form-input`, `.form-label`, `.form-error`, `.alert-*`, `.badge-*`, `.chip`, `.card`. Prefer these, and the Blade components in `resources/views/components/`, over hard-coded hex colours.
- **Responsive & accessible by default**: mobile-first layouts checked from 320px to 1920px, 44px touch targets on buttons, visible focus rings, labelled form fields, and status conveyed with text and icons as well as colour.

---

## Key Modules & Capabilities

### 1. Authentication & Student Verification
- Minimalist centered auth cards for sign-up, login, forgot-password and reset-password. Login is throttled and never reveals whether an email has an account.
- **Email verification**: new members receive a signed link that proves they own the email address they registered with. It does **not** by itself grant the "Verified Student" badge.
- **Student verification (admin-reviewed)**: once their email is confirmed, a member uploads a student ID document from `/account/student-verification`. An administrator compares it against the student ID entered at registration and approves, rejects, or asks for resubmission. Only approval sets the **"Verified Student"** badge shown across listings, chat and profiles. Documents are stored on the private disk and are never publicly reachable; only the owner and admins/governance staff can view them.
- **My account** page: update name and phone, change password (current password required), see verification status.
- Emails are sent with the configured mailer. Locally `MAIL_MAILER=log` writes verification and reset links to `storage/logs/laravel.log`; use a real mailer in production.

### 2. Search, Discovery & Marketplace Grid
- Livewire instant search filter with `300ms` debounce.
- Horizontal category chip selector pills with real-time active listing counts.
- Condition filter pills (`New`, `Like New`, `Good`, `Fair`) and price/date sorting.
- Responsive 3-column desktop / 1-column mobile card grid with eager-loaded relations (`with(['seller', 'category'])`) to prevent N+1 database queries.

### 3. Product Listings Management
- Multi-photo drag-and-drop uploader supporting up to 4 images (JPG/PNG/WebP, 3 MB each) with real-time thumbnail previews and instant validation.
- Sellers can create, edit and remove their own listings from **My listings**. Access is enforced by `ListingPolicy` (owner only; no edits while an item is reserved).
- Detailed single-item page featuring image gallery, seller card, item condition badge, and direct call-to-action buttons (*Reserve this item* & *Message seller*). A seller's student ID is never shown publicly.
- Security audit logging on listing publication, edits and removal.

### 4. Real-Time Secure Messaging Flow
- Split-view chat interface (conversation list on left, chat thread on right).
- Item context bar displaying thumbnail, title, price, and quick item reservation button.
- Real-time polling (`wire:poll.3s`) and unread badge count updates.

### 5. Escrow Transaction Tracker & Lifecycle Stepper
- 4-state visual progress tracker timeline:
  `Initiated` ➔ `Pending Meeting` ➔ `Completed` OR `Disputed`
- Action controls: "Confirm Completion" button releasing funds and marking listing as `sold`.
- "Raise Dispute" modal with reason submission. Integrates with Python AI NLP microservice (storing sentiment score, confidence rating, and suggested resolution).
- Automated security audit log entries on state transitions.

### 6. Filament Admin Moderation & Audit Logs
- **DisputeResource**: Moderation table to inspect open disputes, analyze Python AI sentiment scores (-1.0 to +1.0) and confidence metrics, and resolve disputes in favor of buyer or seller.
- **AuditLogResource**: Read-only audit trail table for security and compliance monitoring.

---

## Technology Stack

- **Core Framework**: Laravel 13 / PHP 8.3+
- **Frontend Architecture**: Livewire 3 + Alpine.js + Tailwind CSS v4
- **Admin Panel**: Filament v3
- **Database**: MySQL (via DBngin / TablePlus)
- **Local Dev Server**: Laravel Herd
- **AI/ML**: Python Flask (For Dispute Moderation)

---

## Prerequisites & Recommended Tools

Before setting up the project, make sure to install the following tools:

1. **[Laravel Herd](https://herd.laravel.com/)**: Fast, zero-config local development environment for Laravel on macOS (includes PHP, Nginx, and automatic local domain routing).
2. **[DBngin](https://dbngin.com/)**: Free, lightweight database manager to easily start local database engines (MySQL, PostgreSQL, Redis) with a single click.
3. **[TablePlus](https://tableplus.com/)**: Modern native GUI database management application for inspecting and querying your MySQL database.
4. **[Node.js & NPM](https://nodejs.org/)** (v18+ recommended): For Vite frontend compilation.
5. **[Composer](https://getcomposer.org/)**: Dependency manager for PHP.

---

## Installation & Setup Guide (with Laravel Herd & MySQL)

Follow these steps to set up and run the application locally:

### 1. Clone the Repository
Clone the repository into your Laravel Herd parked directory (by default `~/Herd`):

```bash
cd ~/Herd
git clone https://github.com/aaron28zulu/uni-market.git
cd uni-market
```

> **Note**: If you clone the repository in a directory outside of `~/Herd`, navigate into the project directory and run `herd link uni-market` (or add the folder in the Herd desktop app under **Sites**).

### 2. Start MySQL Service in DBngin
1. Open **[DBngin](https://dbngin.com/)**.
2. If you don't already have a MySQL service, click **+ New Server**, choose **MySQL** (version 8.0 or 5.7), and click **Create**.
3. Click **Start** next to the MySQL service. By default, it runs on port `3306`.

### 3. Create the Database in TablePlus
1. Open **[TablePlus](https://tableplus.com/)** (or click the arrow icon next to MySQL in DBngin to automatically open TablePlus).
2. Create a new MySQL connection:
   - **Host**: `127.0.0.1`
   - **Port**: `3306`
   - **User**: `root`
   - **Password**: *(leave empty)*
3. Connect and create a new database named:
   ```sql
   unimarket
   ```

### 4. Install Dependencies
Install PHP packages via Composer and frontend packages via NPM:

```bash
composer install
npm install
```

### 5. Configure Environment Variables
Copy the example `.env` file and generate an application key:

```bash
cp .env.example .env
php artisan key:generate
```

Ensure your `.env` file has the following database and URL configurations:

```dotenv
APP_NAME=UniMarket
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://uni-market.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=unimarket
DB_USERNAME=root
DB_PASSWORD=
```

### 6. Run Migrations & Seeders
Run the database migrations and populate the database with initial demo data (users, categories, listings, disputes, audit logs):

```bash
php artisan migrate:fresh --seed
```

### 7. Link Public Storage & Build Assets
Create the storage symlink for uploaded product images (without it listing photos show as missing; `composer setup` does this for you) and compile the frontend:

```bash
php artisan storage:link
npm run dev
```

> For a production build, run `npm run build`.

### 8. Access the Application via Herd
Because the site is hosted in Laravel Herd, you don't need to run `php artisan serve`. Herd automatically serves the site locally:

- **Storefront & Student Marketplace**: [http://uni-market.test](http://uni-market.test)
- **Filament Admin Moderation Panel**: [http://uni-market.test/admin](http://uni-market.test/admin)

*(Optional: To enable HTTPS with a trusted SSL certificate, run `herd secure uni-market` inside the project folder, which will make the site available at [https://uni-market.test](https://uni-market.test)).*

---

## Demo Login Credentials

- **Student Buyer**: `chileshe@student.zut.zm` / `password123`
- **Student Seller**: `mwamba@student.zut.zm` / `password123`
- **Admin Moderator**: `admin@zut.zm` / `password123` (Admin Panel: [http://uni-market.test/admin](http://uni-market.test/admin))

---

## Testing

Run the automated test suite with Pest:

```bash
php artisan test
```

The tests use an in-memory SQLite database, so PHP needs `pdo_sqlite` and `sqlite3` enabled. On Windows, uncomment `extension=pdo_sqlite` and `extension=sqlite3` in `php.ini`. The tests do not need the GD extension; leave `extension=gd` disabled, because on some Windows PHP builds it stops `php artisan serve` from booting.

### AI dispute analysis

Disputes are analysed by the Python NLP microservice configured with `AI_MODERATION_URL` (default `http://127.0.0.1:8000`, endpoint `POST /api/analyze-dispute`). The analysis (sentiment -1 to 1, confidence 0 to 1, suggested resolution, summary) is advisory: if the service is off, slow (`AI_MODERATION_TIMEOUT`) or returns invalid data, the dispute is still recorded and left for a human moderator. Set `AI_MODERATION_ENABLED=false` to turn it off.

### API

The `/api` routes serve the site's own pages and authenticate with the signed-in browser session (send the `X-CSRF-TOKEN` header from the page's `csrf-token` meta tag). Callers without a session receive a JSON `401`. There are no API tokens.

---

