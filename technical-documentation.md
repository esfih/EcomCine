![EcomCiné Logo](./EcomCine.com-Logo1.png)

# Technical Documentation: "EcomCiné"

## 1. Project Overview & Objectives

"EcomCiné" is a productized suite of visual and functional customizations designed to turn any standard eCommerce website into a cinematic shopping experience. It is engineered to be portable and licensable, allowing it to be installed on a wide variety of websites.

The suite is platform-agnostic in its ambition. Phase 1 has been developed and proven on WordPress (using WooCommerce and Dokan), with future phases planned to cover Shopify, Wix, Square, Magento, PrestaShop, and other top-tier marketplace platforms.

The core of the project deviates from traditional e-commerce layouts, which often involve extensive clicking, reading, and multi-page navigation. Instead, it prioritizes a "lean-back" cinematic experience where users can browse and watch product or talent portfolios seamlessly. This is coupled with an equally streamlined process for administration, vendor/talent onboarding, and customer transactions.

---

### 1.1. The Cinematic Experience Funnel

EcomCiné fundamentally reinvents the eCommerce user journey by funneling all discovery paths towards a unique, cinematic "Showcase" mode. This approach complements, rather than replaces, traditional browsing methods. The goal is to transform passive browsing into an engaging, "lean-back" viewing experience.

There are three primary funnels to guide users to the Showcase:

#### Funnel 1: The Animated Card Gallery

This is the main discovery interface, found on the homepage and dedicated talent/product pages.

*   **Visual Grid:** Users are presented with an animated grid of vendor/product cards, paginated in groups of 10 (2 rows of 5).
*   **Dynamic Filtering:** A two-tier filtering system avoids overwhelming the user:
    *   **Generic Filters:** Apply to all items in the catalog.
    *   **Contextual Attributes:** A powerful feature where specific filter sets are dynamically loaded based on the selected category. This logic extends to the vendor dashboard, where vendors only see and fill out attributes relevant to their chosen categories.
*   **Call to Action:** After curating their list via filters, a prominent "SHOWCASE" button appears, allowing users to send their selection directly to the player.

#### Funnel 2: Category-Based Discovery

*   Users can navigate to specific category pages (e.g., "Models," "Photographers").
*   Each page lists all vendors within that category. At the bottom, a "SHOWCASE" button sends the entire category to the player.

#### Funnel 3: Geo-Visual World Map

*   A visually striking, dark-themed world map powered by Mapbox plots the location of all vendors.
*   A vertical panel of filters allows users to curate a list based on geographic criteria.
*   A "SHOWCASE" button sends the geo-curated list to the player.

### The Showcase Experience

The Showcase is the destination for all discovery funnels, creating an automated, "Web TV" like channel.

*   **Autoplay Sequence:** It automatically plays the first media file from each selected talent for a short duration (e.g., 12 seconds, or 30 for "featured" talent).
*   **Dynamic Overlay:** As a talent's media plays, their info card slides out from the left with key details, then collapses to return to a full-screen view.
*   **Endless Loop:** The player seamlessly transitions to the next talent in the list. Once it reaches the end, it loops back to the beginning, creating a continuous, engaging broadcast.
*   **User Interaction:** The user can pause the sequence at any time to take control, browse a specific talent's full media gallery, and view detailed analytics and information.

This "Web TV" concept also extends to the individual talent profiles. A dedicated version of the Showcase player on each talent's page plays only their media in a loop. All of their detailed information, analytics, and booking/purchasing actions are available as one-click sliding info panels. This transforms each talent's unique URL into a personal, e-commerce-enabled Web TV channel, incentivizing them to promote their profile on social media and drive organic traffic and brand recognition back to the EcomCiné platform.

A dozen high profile media companies are using EcomCiné to deliver a unique & high performance cinematic experience: CastingAgency.co, TopDoctorMagazine.com and multiple other TV channels

---

### **Development Principles**

The architecture and custom features are guided by five key principles:

1.  **Code Efficiency:** Implement modular, reusable code blocks. Critically, only load the minimum CSS, JavaScript, and PHP necessary for a given page to function, ensuring optimal performance.
2.  **Cinematic Buyer Experience:** The primary user journey for discovering products or talent should be akin to "watching TV"—a fluid, visual experience with minimal clicks and no unnecessary page loads.
3.  **Frontend Inline Editing:** For administrators and vendors (talents), provide a "What You See Is What You Edit" (WYSIWYE) experience. All profile modifications should happen directly on the frontend profile page, eliminating the need to navigate the backend and removing any learning curve.
4.  **Maximized Conversion:** Radically shorten the path to a transaction. The booking and checkout process is designed to be as quick as possible, asking for the absolute minimum information required from a buyer to create an account and process a payment.
5.  **Frictionless Vendor Onboarding:** Reduce the friction that causes vendors to abandon their profile setup. This is achieved via an admin-initiated, pre-filled profile that the talent can "claim" and finalize through a simple, secure link.

---

## 2. System Architecture

The platform is built on a standard, robust WordPress stack, which is then heavily extended by a custom-developed suite of themes and plugins.

### 2.1. Base Stack (Phase 1: WordPress)

*   **Core:** WordPress
*   **E-commerce Engine:** WooCommerce
*   **Marketplace Engine:** Dokan Pro
*   **Base Theme:** Astra

### 2.2. Customization Stack

This is the collection of custom components that deliver the unique functionality and experience.

*   **`astra-child` (Child Theme):** The central controller. It customizes the appearance, manages all custom assets (CSS/JS), and contains the core business logic that integrates the various plugins and overrides the base stack's behavior.
*   **`tm-media-player` (Plugin):** The heart of the cinematic experience. This plugin transforms the vendor store into a media portfolio, generates dynamic playlists, and provides the full-screen "Showcase" player.
*   **`tm-account-panel` (Plugin):** Handles all user account functions from a front-end modal. It provides the seamless login/registration and the admin-driven "Talent Onboarding" workflow.
*   **`tm-vendor-booking-modal` (Plugin):** Manages the entire frictionless booking and checkout process within a single, self-contained modal window.

 (Conceptual Diagram: Base Stack -> Custom Theme & Plugins -> Final User Experience)

---

## 3. Component Breakdown: `astra-child` Theme

The child theme is the orchestrator of the entire custom experience.

| File / Directory | Description |
| :--- | :--- |
| **`functions.php`** | **The Core Controller.** This file contains the bulk of the custom logic. <br/>- **Asset Optimization:** Aggressively dequeues (removes) default CSS/JS from other plugins on store pages and loads only the minimal assets required. <br/>- **Feature Integration:** Includes and initializes all custom modules from the `/includes/` directory (e.g., vendor attributes, social metrics, profile completeness). <br/>- **Dokan/WooCommerce Overrides:** Injects the "cinematic" header, adds vendor avatar badges to product listings, and customizes location display. <br/>- **Dynamic Forms:** Contains the JavaScript logic that dynamically shows/hides vendor profile fields based on the selected talent categories. |
| **`style.css`** | Contains minimal, high-level CSS for base branding, such as the gold "Featured" vendor label. The majority of styles are in the `assets/css/` directory. |
| **`page-platform.php`** | A custom page template that provides the layout for the main talent discovery page (the page containing the `[dokan-stores]` shortcode). |
| **`template-talent-showcase-full.php`** | A full-width, header/footer-less page template used by the `tm-media-player` plugin to create the immersive showcase experience. |
| **`/assets/`** | Contains the primary custom CSS and JavaScript files for the theme, including `vendor-store.css` (styles for the store page) and `vendor-store.js` (handles UI like the biography lightbox and inline editing). |
| **`/dokan/`** | **Dokan Template Overrides.** Contains customized versions of Dokan's template files. For example, `dokan/store-header.php` is heavily modified to include the media player and custom attribute displays. |
| **`/woocommerce/`** | **WooCommerce Template Overrides.** Contains customized versions of WooCommerce templates to control the appearance of shop and product elements. |
| **`/includes/`** | **Modular PHP Functions.** Organizes backend functionality into logical sub-directories. <br/>- `vendor-attributes/`: Manages the saving and display of custom talent data (e.g., physical attributes). <br/>- `social-metrics/`: The engine for fetching and displaying social media statistics. <br/>- `vendor-profile/`: The "Profile Completeness" engine and AJAX handlers for inline editing. <br/>- `admin/`: Tools for administrators, including vendor edit logs. |

---

## 4. Component Breakdown: Custom Plugins

### 4.1. `tm-media-player`

**Purpose:** To deliver the cinematic talent discovery experience (Principle #2).

| Feature | Description |
| :--- | :--- |
| **Automatic Playlist** | Scans the vendor's biography for WordPress `[gallery]` and `[playlist]` shortcodes and automatically builds a JSON playlist for the front-end player. |
| **Showcase Mode** | Activated by the `[tm_talent_showcase]` shortcode. It takes over the page, hides the site header/footer, and launches a full-screen media player. |
| **Dynamic Loading** | Provides a custom REST API endpoint (`/tm/v1/vendor-store-content`) that allows the showcase player to load the next talent's profile and media instantly, without a page refresh. |
| **Asset Management** | Contains its own `TM_Media_Player_Assets` class to precisely control the loading of `player.js` and `player.css` only when needed. |

### 4.2. `tm-account-panel`

**Purpose:** To handle all account management and streamline talent onboarding (Principles #3 & #5).

| Feature | Description |
| :--- | :--- |
| **Modal UI** | Adds a floating "Account" tab on store pages that opens a modal for all account functions. It shows login/register forms to guests and a dashboard to logged-in vendors. |
| **AJAX-Driven** | All actions (login, viewing orders, creating talent profiles) are handled via AJAX for a fast, seamless experience. |
| **Talent Onboarding** | The plugin's killer feature. An admin can create a "pre-onboarded" talent profile. The system generates a unique, expiring "claim link" (and QR code) that can be sent to the talent, allowing them to instantly take over their pre-filled profile by setting a password. |

### 4.3. `tm-vendor-booking-modal`

**Purpose:** To radically shorten the booking and checkout process (Principle #4).

| Feature | Description |
| :--- | :--- |
| **All-in-One Modal** | Manages the entire booking flow in a single modal: user selects a time, that selection is added to the cart, and a checkout form appears, all in the same window. |
| **Hardcoded Product** | The system is designed to work with a specific product for each vendor: a booking product categorized as "Half-Day". |
| **AJAX Workflow** | Uses a three-step AJAX process to fetch the booking form, add the selection to the cart, and then fetch the checkout form, all without a page reload. |
| **Simplified Checkout** | The checkout form inside the modal is heavily customized to remove all non-essential fields (e.g., shipping, coupons, order notes), asking only for the buyer's name and email to maximize conversion. |
