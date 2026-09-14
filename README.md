# UniMarket - Verified Campus Student Marketplace

UniMarket is a full-stack campus student marketplace built with **Laravel 13**, **Livewire 3**, **Alpine.js**, **Tailwind CSS**, and **Filament v3**. It enables students to buy, sell, message, and track transactions through a secure 4-stage escrow lifecycle with AI-assisted dispute moderation.

---

## Design System & Typography

- **Typography**: Google Sans (`400 Regular`, `500 Medium`, `600 SemiBold`).
- **3-Color Palette Standard**:
  - **Neutral Base**: Neutral Light (`#F8FAFC` background, `#0F172A` body text, `#E2E8F0` borders).
  - **Primary Brand**: Deep Slate Indigo (`#1E293B` / `#312E81`) for headers, primary action buttons, active states, and focus rings.
  - **Accent & Status**: Emerald Green (`#059669`) for verified badges, successful transactions, and price tags. Subtle Amber (`#D97706`) for pending/disputed states.
- **Aesthetics**: Minimalist, high-whitespace card layouts with `border border-slate-200`, `rounded-lg`, and soft micro-shadows (`shadow-sm`).

---

## Key Modules & Capabilities

### 1. Authentication & Student Verification
- Minimalist centered auth cards for sign-up and login.
- Email verification accepting student emails and general providers.
- Assigns **"Official Student"** verified badge displayed across listings and profile components.

### 2. Search, Discovery & Marketplace Grid
- Livewire instant search filter with `300ms` debounce.
- Horizontal category chip selector pills with real-time active listing counts.
- Condition filter pills (`New`, `Like New`, `Good`, `Fair`) and price/date sorting.
- Responsive 3-column desktop / 1-column mobile card grid with eager-loaded relations (`with(['seller', 'category'])`) to prevent N+1 database queries.

### 3. Product Listings Management
- Multi-photo drag-and-drop uploader supporting up to 4 images with real-time thumbnail previews.
- Detailed single-item page featuring image gallery carousel, seller profile card, item condition badge, and direct call-to-action buttons (*Reserve Item* & *Chat with Seller*).
- Security audit logging (`AuditLog::create()`) on listing publication.

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
Create the storage symlink for uploaded product images and compile the frontend:

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

---

