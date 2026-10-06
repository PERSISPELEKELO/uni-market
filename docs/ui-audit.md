# UniMarket UI Audit (Phase 0)

Written before any Phase 1-4 work, per the brief: inspect everything first, then use these
findings to drive the navigation rework (Phase 3) and the UI rebuild (Phase 4).

## 1. Every storefront page

| Page (component) | Route | Purpose (one sentence) | Main action | Problems found |
|---|---|---|---|---|
| `ListingIndex` | `/` | Browse/search/filter every active listing. | Reserve or open an item. | No curated sections (trending, just listed, etc.) - just a filter bar + grid + pager. No skeleton loaders. Hot Deals only reachable via a filter chip, not a distinct "section" feel. Category chips have real counts (good) but no icons. |
| `ListingShow` | `/listings/{listing}` | View one item and act on it (reserve / message / manage). | Reserve (buyer) or choose a buyer (seller). | No "how buying works" steps shown inline (the escrow/direct explanation is a single paragraph at the very bottom, easy to miss). No view count, no "N students reserving" phrased as an insight, no "similar items" / "bought together" section. Primary action button doesn't visually change state as clearly as it could (reserved vs not reserved look like two different components, not one state machine). |
| `MyListings` | `/my-listings` | Manage items the signed-in user is selling. | Edit or remove a listing. | Uses its own inline card markup, not `x-listing-card` (reasonable - it's an owner view with Edit/Remove instead of Reserve - but it means "what a listing looks like" is defined in two places that must be kept in sync by hand). No per-listing views/reservations shown here, even though that data now exists. No grouping by status (Active / Reserved / Sold / Archived all shown in one flat list). |
| `CreateListing` / `EditListing` | `/listings-create`, `/listings/{listing}/edit` | Publish a new listing / edit an existing one. | Save/publish. | Share one partial (`listing-fields.blade.php`) - good, no duplication. No live preview card while filling the form. Drag-and-drop photo upload already exists (good). |
| `PublicProfile` | `/students/{user}` | Show a student's public reputation and active listings. | Message the seller, or (on your own profile) export your reputation. | The "Export reputation & history" action and the link to "Verify a reputation record" live only here and in the footer - there is no obvious account-settings path to either. |
| `MessageThread` | `/chat`, `/chat-thread/{receiver}/{listing?}` | Buyer/seller conversation about an item. | Send a message. | Two separate route names/URL shapes point at the same component (`chat.index` and `chat.thread`) - harmless but inconsistent. No link back to the listing's transaction tracker from an active conversation about a completed/ongoing transaction. |
| `Tracker` | `/transactions-tracker/{transaction?}` | Follow one purchase/sale through reservation -> handover -> inspection -> completion (or the direct-purchase equivalent). | Verify handover / confirm completion / raise a dispute / rate. | Nav calls this "My transactions"; the page title says "My transactions" too (consistent), but it is a *separate* destination from "My listings" even though both are "things I'm doing on the marketplace" from the user's point of view. The dispute status badge here is built with a raw `{{ ucfirst($dispute->status) }}` instead of a shared label helper, so a resolved dispute shows "Resolved_buyer" / "Resolved_seller" verbatim - a jargon leak. |
| `Account\Profile` | `/account` | Edit name/phone/bio/programme/avatar/password; see identity/verification status at a glance. | Save details / change password. | One long page mixing five concerns (photo, identity status, personal details, password, links out to verification) with no sub-navigation - a user has to scroll past everything to reach what they want. "Student verification" status is summarised here then fully handled on a *different* page (`/account/student-verification`); reputation export/verify isn't mentioned here at all despite being an account-level concern. |
| `Account\StudentVerification` | `/account/student-verification` | Upload a student ID document for approval. | Submit a document. | Fine on its own; the issue is only that it's disconconnected from `/account` in the navigation (no shared sub-nav), so it reads as a stray extra page rather than part of "my account". |
| `Ratings\VerifyReputation` | `/reputation/verify` | Let anyone (no login) check a previously exported reputation file's signature. | Upload a file. | Only discoverable via the footer link and the link next to the Export button on a profile - not from the account menu, even though it's conceptually an account/reputation feature too. |
| Appeals (`AppealController`) | `/appeals*` | Let a student contest a moderation decision and see its outcome. | Submit/view an appeal. | **No frontend page exists at all.** This is a pure JSON API controller (`index`/`store`/`show`/`review`/`decide` all return `JsonResponse`). A student cannot currently use this feature through the UI - there is no Blade view, no Livewire component, and no nav link, despite `AuditLoggerService`/`AppealWorkflowService` and the admin "Reports & Appeals" page already existing and working. |
| `Auth\Login`, `Auth\Register`, `Auth\ForgotPassword`, `Auth\ResetPassword`, `Auth\VerifyEmail` | `/login`, `/register`, `/forgot-password`, `/reset-password/{token}`, `/email/verify` | Standard auth flows. | Log in / create account / reset password / verify email. | Consistent card-centered layout already shared across all five (good). No year-of-study/school fields yet (Phase 1). |

## 2. Duplication and confusion

1. **"My listings" vs "My transactions" are two separate top-level nav destinations** for what a seller/buyer experiences as one activity: things I'm selling and things I'm buying. A seller juggling an active sale has to jump between two unrelated pages to see "is anyone interested" (My listings has no reservation info) and "where is this sale in the process" (that's only on Tracker).
2. **Two listing-card layouts**: the shared `x-listing-card` component (grid/carousel views) vs `MyListings`'s own hand-written row markup. Intentional (owner actions differ from buyer actions) but means visual changes to "what a listing looks like" must be made twice.
3. **Account-related pages are split three ways with no shared chrome**: `/account` (profile+password+identity summary), `/account/student-verification` (a fully separate page), and reputation export/verify (only reachable from the public profile page and the footer). A user has no single place that says "this is everything about my account."
4. **Two route names for the same chat component** (`chat.index`, `chat.thread`) - not a user-facing problem today, but worth collapsing when the nav is reworked so there is exactly one canonical URL shape.
5. **Appeals has backend+admin support but no frontend** - this is the starkest "half-built feature" in the audit: `AppealWorkflowService`, `AppealPolicy`, and the admin `AppealResource` ("Reports & Appeals") are all real and tested, but a student can only ever create an appeal by calling the JSON API directly. Phase 3's nav plan includes an "Appeals" link in the avatar menu - that link currently has nowhere to go.
6. **The dispute-resolution outcome is shown with raw enum text** (`resolved_buyer`, `resolved_seller`) via a one-off `ucfirst()` call in `tracker.blade.php`, instead of going through the shared `x-status-badge` component that already exists and already prettifies every other status in the app.

## 3. Inconsistent wording

| Concept | Labels currently in use | Chosen term (going forward) |
|---|---|---|
| The buyer/seller purchase record | "Tracker" (route name `transactions.tracker`), "My transactions" (nav link + page `<h1>`) | **"My Activity"** as the nav destination (Phase 3), with the existing step-by-step view kept as its "Buying"/"Selling" detail, still called a **transaction** in body copy. |
| Seller's own items for sale | "My listings" (nav + page title) | **"My Activity > Selling"** (Phase 3); keep "listing" as the noun for an individual item everywhere. |
| A listing waiting for the seller to pick a buyer | Status badge: "Awaiting meet-up" (for `INITIATED`/`RESERVED`/`PENDING_MEETING`); stepper label: "Reserved" (step 1) then "Meet-up" (step 2) | Keep **"Reserved"** for the buyer-side state (listing has at least one reservation) and **"Awaiting meet-up"** only once a specific buyer has been chosen and a handover code exists - these are genuinely two different states today and should read as two different states, not be merged. |
| Dispute resolution outcome | Raw `resolved_buyer` / `resolved_seller` shown via `ucfirst()` | **"Resolved in the buyer's favour"** / **"Resolved in the seller's favour"** - add these to `x-status-badge` and use it in `tracker.blade.php` instead of the inline `ucfirst()`. |
| Inspection window | "Inspection period" (admin), "Inspection" (buyer-facing stepper/badge), "ITEM_INSPECTION" (raw status, never shown but present in code comments and one or two admin tables) | **"Inspection period"** everywhere user-facing; raw enum stays internal only. |
| The AI-assisted dispute analysis | "AI dispute analysis" (tracker), "AI Dispute Assistance" (admin nav group) | Keep as-is - already consistent between the two places a student and an admin actually see it. |

## 4. Proposed navigation map

See the brief's own table (reproduced below) - it matches this audit's findings and is adopted as-is for Phase 3:

| Item | Contains | Where it comes from today |
|---|---|---|
| **Browse** | `ListingIndex` (marketplace home) | Already `listings.index`; no route change needed. |
| **Sell** | `CreateListing` | Already `listings.create`; stays a primary button. |
| **My Activity** | Tabs: **Buying** (purchases/reservations/transactions as buyer), **Selling** (`MyListings` content + reservations received), **Insights** (Phase 2 services) | Merges `listings.mine` and `transactions.tracker`. Both old routes redirect into the new hub's matching tab. |
| **Messages** | `MessageThread` | Already `chat.index`/`chat.thread`; collapse to one canonical route name during the nav rework. |
| **Notifications** | Existing bell | No change. |
| **Avatar menu** | Public profile, Account (Profile / Verification / Security / Reputation export sub-sections), **Appeals** (new frontend, backend already exists), Log out | Splits today's single `/account` page into sub-sections; gives Appeals its first-ever UI. |

This document is the baseline for Phase 3 (navigation) and Phase 4 (visual/component rebuild) - nothing described above has been changed yet.
