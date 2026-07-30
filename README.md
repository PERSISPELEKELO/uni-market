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
- **Frontend Architecture**: Livewire 3 + Alpine.js + Tailwind CSS
- **Admin Panel**: Filament v3
- **Database**: MySQL
- **AI/ML** :Python Flask (For Dispute Moderation)
---

## Installation & Setup

1. **Clone the repository**:
   ```bash
   git clone https://github.com/aaron28zulu/uni-market.git
   cd uni-market
   ```

2. **Install Composer dependencies**:
   ```bash
   composer install
   ```

3. **Configure Environment File**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Run Migrations & Seeders**:
   ```bash
   php artisan migrate:fresh --seed
   ```

5. **Link Storage**:
   ```bash
   php artisan storage:link
   ```

6. **Start Local Development Server**:
   ```bash
   php artisan serve
   ```

Visit `http-[#]127.0.0.1:8000` in your browser.

---

## Demo Login Credentials

- **Student Buyer**: `chileshe@student.zut.zm` / `password123`
- **Student Seller**: `mwamba@student.zut.zm` / `password123`
- **Admin Moderator**: `admin@zut.zm` / `password123` (Admin Panel: `http-[#]127.0.0.1:8000/admin`)

---

## Testing

Run the automated test suite with Pest / PHPUnit:

```bash
php artisan test
```

---

