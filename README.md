# UniMarket - Verified Campus Student Marketplace

UniMarket is a full-stack campus student marketplace built with **Laravel 13**, **Livewire 3**, **Alpine.js**, **Tailwind CSS v4**, and **Filament v3**. Students discover, message and reserve items, then complete the sale through a secure, multi-stage escrow handover with AI-assisted dispute moderation, followed by two-sided star ratings.

---

## Design System & Typography

- **Typography**: Google Sans (`400`–`700`), configured once in `resources/css/app.css` (`--font-sans`).
- **Colour tokens** (Tailwind v4 `@theme` in `resources/css/app.css`, used as `bg-brand-700`, `text-accent-700`, ...):
  - **Neutral base**: `surface` background and `ink` body text — both flip automatically in dark mode, along with the whole `slate` scale.
  - **Brand** (deep indigo, `brand-50`–`brand-950`): primary buttons (`brand-700`), links, active states and focus rings.
  - **Accent** (emerald, `accent-*`): verified badges, success states, escrow "protected handover" callouts.
  - **Warn** (amber, `warn-*`): reserved / inspection / disputed. **Danger** (red, `danger-*`): errors and destructive actions. **Info** (sky, `info-*`): neutral notices.
  - Text colours use the `-700`/`-800` steps (with light `-300`/`-400` dark-mode companions) so they keep at least 4.5:1 contrast on their background in both themes.
  - **Prices are always `text-ink`** (near-black in light mode, light grey in dark mode) rather than a tinted colour — this is deliberate so a price never gets mistaken for a status/verification colour.
- **Shared classes** (`@layer components`): `.btn` (+ `-primary`, `-secondary`, `-success`, `-warning`, `-danger`, `-danger-outline`), `.form-input`, `.form-label`, `.form-error`, `.alert-*`, `.badge-*`, `.chip`, `.card`, `.nav-link`. Prefer these, and the Blade components in `resources/views/components/`, over hard-coded hex colours.
- **Light/dark theme**: a toggle in the header (and mobile menu) flips a `dark` class on `<html>` and remembers the choice in `localStorage`; an inline script in `<head>` applies it before first paint so there's no flash of the wrong theme. Dark mode is implemented by inverting the neutral `slate` scale plus the `ink`/`surface` tokens globally, and by adding explicit `dark:` companions wherever a literal white surface (cards, forms, dropdowns, modals) needs one — the named brand/accent/warn/danger/info scales and `white`/`black` themselves are left untouched so that fixed pairs like `bg-brand-700 text-white` never break.
- **Responsive & accessible by default**: mobile-first layouts checked from 320px to 1920px, 44px touch targets on buttons, visible focus rings, labelled form fields, and status conveyed with text and icons as well as colour.

---

## Key Modules & Capabilities

### 1. Authentication & Two-Stage Verification
- Minimalist centered auth cards for sign-up, login, forgot-password and reset-password. Login is throttled and never reveals whether an email has an account.
- Registration requires a **digits-only student ID of at least 10 digits** (`digits_between:10,20`), validated identically on the client and the server, plus an optional phone number in `+260971234567`-style format. Names and bios have no arbitrary minimum length — short but genuine values like "Sam" are accepted.
- **Stage 1 — Email verification**: new members receive a signed link proving they own the email they registered with. This alone does **not** grant the "Verified Student" badge.
- **Stage 2 — Student verification (admin-reviewed)**: once email is confirmed, a member uploads a student ID document from `/account/student-verification`. An administrator compares it against the registered student ID and approves, rejects, or asks for resubmission, with a full audit trail and a notification back to the student. Only approval sets the **"Verified Student"** badge shown across listings, chat and profiles. Documents live on the private disk and are never publicly reachable — only the owner and admin/governance staff can view them (`/account/verification-documents/{document}`).
- **My account** page: update name, phone, bio, programme and business type; upload/replace/remove a profile photo (JPG/PNG/WebP, validated, with a default initial-letter avatar when none is set); change password (current password required); see verification status.
- Emails are sent with the configured mailer. Locally `MAIL_MAILER=log` writes verification and reset links to `storage/logs/laravel.log`; use a real mailer in production.

### 2. Search, Discovery & Marketplace Grid
- Livewire instant search filter with `300ms` debounce.
- Horizontal category chip selector pills with real-time active listing counts.
- Condition filter pills (`New`, `Like New`, `Good`, `Fair`) and price/date sorting.
- Responsive 3-column desktop / 1-column mobile card grid with eager-loaded relations to prevent N+1 database queries. Each card shows an active-reservation count when a listing has interested buyers.
- Public profiles (`/students/{user}`) list a student's active listings, average rating, and recent reviews — never their private contact details or verification documents.

### 3. Product Listings Management
- Multi-photo drag-and-drop uploader supporting up to 4 images (JPG/PNG/WebP, 3 MB each) with real-time thumbnail previews and instant validation.
- Sellers can create, edit and remove their own listings from **My listings**. Access is enforced by `ListingPolicy` (owner only).
- Detailed single-item page featuring image gallery, seller card, item condition badge, and call-to-action buttons. A seller's student ID is never shown publicly.
- Security audit logging on listing publication, edits and removal.

### 4. Reservations — the Seller Picks a Buyer
- Reserving an item **does not** hide or remove the listing. It adds the buyer to a visible waitlist (an "N reservations" count shown on the card and item page) and notifies the seller.
- The seller sees everyone who reserved and picks one buyer to start the handover; that choice is what creates the `Transaction` and kicks off escrow — the reservation step itself never touches escrow.
- `ReservationService` enforces every rule server-side (no double-reserving, no reserving your own listing, only the seller can select a buyer, selection is locked with a row lock to prevent a race between two simultaneous picks) rather than relying on hidden buttons.

### 5. Two-Way, Platform-Wide Messaging
- Split-view chat interface (conversation list on the left, thread on the right) with `wire:poll` live updates and unread badge counts.
- **Discoverable**: a search box in the inbox finds any student by name so a conversation can be started with anyone, not just an existing buyer or seller — covering buyer↔seller, seller↔any reserver, and general user↔user messaging.
- Opening a conversation about a listing shows an item context bar (thumbnail, title, price) with a link straight back to the listing.
- Messages are escaped before rendering (no HTML injection) and a conversation only ever shows the two participants' own messages.
- Once a shared transaction between the two people completes, the inbox surfaces a "Would you like to rate your experience?" prompt inline in that conversation.

### 6. Escrow Transaction Tracker & Lifecycle
- Visual progress stepper: `Reserved` ➔ `Meet-up` ➔ `Inspection` ➔ `Completed` (or `Disputed`).
- The buyer receives a one-time handover code to give the seller in person; the seller enters it to move the transaction into the inspection window. The code is never shown to the seller ahead of time.
- "Confirm Completion" (buyer only) releases the item and marks the listing `sold`. "Raise Dispute" opens a modal with a reason, which is sent to a Python AI NLP microservice for sentiment/confidence analysis — advisory only: if the service is off, slow, or returns invalid data the dispute is still recorded for a human moderator.
- Automated security audit log entries on every state transition.
- Once a transaction reaches `COMPLETED`, both sides can rate each other directly from the tracker.

### 7. Two-Sided Star Ratings
- Buyer rates seller, seller rates buyer — one 1–5 star rating (with an optional comment) per person per transaction.
- `RatingService` enforces every eligibility rule server-side, not just by hiding the button: the rater must be a genuine participant, the transaction must be `COMPLETED`, there must be no open/under-review dispute, and the rated user is always computed as "the other participant" so it can never be swapped by a crafted request.
- Ratings can be given from the transaction tracker or from the inbox once a shared transaction is complete.
- Public profiles show the average rating, review count, a five-bar star breakdown, and recent reviews (rater, stars, comment, date) — never which listing or transaction a review came from.

### 8. Filament Admin Moderation & Audit Logs
- **StudentVerificationDocumentResource**: review queue for pending student ID documents with approve / reject / request-resubmission actions and a full audit trail.
- **DisputeResource**: inspect open disputes, review the AI sentiment score (-1.0 to +1.0) and confidence metrics, and resolve in favour of buyer or seller.
- **AuditLogResource**: read-only audit trail for security and compliance monitoring.
- The panel is locked down: only accounts with the `admin` or `governance_committee` role can sign in (`User::canAccessPanel()`) — Filament otherwise allows any authenticated user in by default.

### 9. Appeals, Governance & Data Portability
- Users can appeal a moderation decision (`/appeals`); a `governance_committee` role reviews and decides appeals independently of the primary admin, with the outcome (`UPHELD` / `OVERTURNED`) recorded.
- Public, unauthenticated transparency endpoints under `/api/v1/governance/*` and `/api/v1/transparency/metrics` publish aggregate moderation/audit statistics — no personal data.
- Authenticated users can export their own reputation and activity data as a cryptographically signed JSON package (`/reputation/export`) and independently verify a given export's signature (`/reputation/verify`).

---

## Technology Stack

- **Core Framework**: Laravel 13 / PHP 8.3+
- **Frontend Architecture**: Livewire 3 + Alpine.js + Tailwind CSS v4
- **Admin Panel**: Filament v3
- **Database**: MySQL (via DBngin / TablePlus, or any MySQL 8/5.7 server)
- **Local Dev Server**: Laravel Herd, or plain `php artisan serve` on any OS
- **AI/ML**: Python Flask microservice for advisory dispute sentiment analysis

---

## Prerequisites & Recommended Tools

Before setting up the project, make sure to install the following tools:

1. **[Laravel Herd](https://herd.laravel.com/)** (macOS/Windows): zero-config local dev environment (PHP, Nginx, automatic local domain routing). Optional — `php artisan serve` works everywhere.
2. **[DBngin](https://dbngin.com/)**: free, lightweight database manager to start local database engines (MySQL, PostgreSQL, Redis) with a single click.
3. **[TablePlus](https://tableplus.com/)**: modern native GUI database management application for inspecting and querying your MySQL database.
4. **[Node.js & NPM](https://nodejs.org/)** (v18+ recommended): for Vite frontend compilation.
5. **[Composer](https://getcomposer.org/)**: dependency manager for PHP.

---

## Installation & Setup Guide (with Laravel Herd & MySQL)

Follow these steps to set up and run the application locally:

### 1. Clone the Repository
Clone the repository into your Laravel Herd parked directory (by default `~/Herd`):

```bash
cd ~/Herd
git clone https://github.com/PERSISPELEKELO/uni-market.git
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
Create the storage symlink for uploaded product images and profile photos (without it, listing/profile photos show as missing) and compile the frontend:

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

> **Without Herd (any OS, including plain Windows)**: after steps 4–7 above, run `php artisan serve` and open the URL it prints (default `http://127.0.0.1:8000`). On Windows, make sure `pdo_mysql` is enabled in `php.ini` for the database connection to work.

---

## Demo Login Credentials

- **Student Buyer**: `chileshe@student.zut.zm` / `password123`
- **Student Seller**: `mwamba@student.zut.zm` / `password123`
- **Student (third account, for reservation/messaging scenarios)**: `kabwe@student.zut.zm` / `password123`
- **Admin Moderator**: `admin@zut.zm` / `password123` (Admin Panel: [http://uni-market.test/admin](http://uni-market.test/admin))

---

## Testing

Run the automated test suite with Pest:

```bash
php artisan test
```

The tests use an in-memory SQLite database, so PHP needs `pdo_sqlite` and `sqlite3` enabled. On Windows, uncomment `extension=pdo_sqlite` and `extension=sqlite3` in `php.ini`. The tests do not need the GD extension; leave `extension=gd` disabled, because on some Windows PHP builds it stops `php artisan serve` from booting — image-upload tests use a small pre-built PNG fixture (`fakePhoto()` in `tests/Pest.php`) instead of GD-generated images.

### AI dispute analysis

Disputes are analysed by the Python NLP microservice configured with `AI_MODERATION_URL` (default `http://127.0.0.1:8000`, endpoint `POST /api/analyze-dispute`). The analysis (sentiment -1 to 1, confidence 0 to 1, suggested resolution, summary) is advisory: if the service is off, slow (`AI_MODERATION_TIMEOUT`) or returns invalid data, the dispute is still recorded and left for a human moderator. Set `AI_MODERATION_ENABLED=false` to turn it off.

### API

Most `/api` routes serve the site's own pages and authenticate with the signed-in browser session (send the `X-CSRF-TOKEN` header from the page's `csrf-token` meta tag); callers without a session receive a JSON `401`. This covers transaction handover/dispute actions, appeals, and reputation data export/verification. There are no API tokens. A small number of governance/transparency endpoints (`/api/v1/transparency/metrics`, `/api/v1/governance/transparency-summary`, `/api/v1/governance/audit-feed`) are intentionally public and return only aggregate statistics, never personal data.

---
