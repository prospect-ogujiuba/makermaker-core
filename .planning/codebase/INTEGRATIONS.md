# External Integrations

**Analysis Date:** 2026-01-07

## APIs & External Services

**No External Service Integrations:**
- No third-party APIs detected
- No payment processors (Stripe, PayPal)
- No email services (SendGrid, Mailgun)
- No analytics platforms (Google Analytics, Segment)
- No external mapping services (Google Maps)
- No cloud storage (AWS S3, Azure)
- No authentication providers (OAuth, Auth0)

## Data Storage

**Databases:**
- MySQL/MariaDB - Primary data store (InnoDB engine)
  - Connection: via WordPress `$wpdb` global
  - Table prefix: `srvc_` for custom tables
  - Migrations: `database/migrations/*.sql` (50+ migration files)
  - Key tables:
    - `srvc_services` - Core service catalog
    - `srvc_service_prices` - Pricing data
    - `srvc_contact_submissions` - Customer inquiries
    - `srvc_teams` - Team members
    - Plus 40+ additional tables for catalog management

**File Storage:**
- WordPress Media Library - Featured images for services
  - Stored as WordPress attachment IDs
  - Referenced in `featured_image_id` columns

**Caching:**
- Not detected - No Redis, Memcached, or dedicated caching layer

## Authentication & Identity

**Auth Provider:**
- WordPress Core Authentication - wp_users table
  - User management via WordPress admin
  - Capabilities checked via `current_user_can()`
  - Audit trail: `created_by`, `updated_by` reference wp_users.ID

**Authorization:**
- Policy-Based Access Control - `app/Auth/*Policy.php`
  - Custom capabilities: `manage_services`, etc.
  - Enforced via `AuthorizationHelper::authorize()`

## Monitoring & Observability

**Error Tracking:**
- None detected - No Sentry, Bugsnag, or error tracking services

**Analytics:**
- Not detected - No product analytics integration

**Logs:**
- WordPress debug.log (standard WordPress logging)
- No external log aggregation services detected

## CI/CD & Deployment

**Hosting:**
- Self-hosted WordPress environment
- Deployment method: not specified (manual file upload or custom deployment)

**CI Pipeline:**
- Not detected - No GitHub Actions, GitLab CI, or Jenkins configurations

## Environment Configuration

**Development:**
- Required: WordPress installation, MySQL database
- Configuration: wp-config.php (WordPress standard)
- Assets: `npm run dev` or `npm run watch` for development builds

**Production:**
- Required: Same as development + PHP 8.2+
- Assets: `npm run prod` for production builds with versioning
- Migrations: Auto-run on plugin activation via `MakermakerTypeRocketPlugin::activate()`

## Webhooks & Callbacks

**Incoming:**
- None detected

**Outgoing:**
- None detected

## WordPress Integration

**Core Hooks:**
- `typerocket_loaded` (priority 9) - Main initialization - `makermaker.php:54`
- `register_activation_hook` - Database migrations - `makermaker.php:52`
- `delete_plugin` - Cleanup on uninstall - `makermaker.php:53`
- `wp_enqueue_scripts` - Frontend assets - `app/MakermakerTypeRocketPlugin.php`
- `admin_enqueue_scripts` - Admin assets - `app/MakermakerTypeRocketPlugin.php`

**Admin Post Actions:**
- `admin_post_update_contact_submission_status` - Contact status updates
- `admin_post_update_contact_submission_notes` - Contact notes updates
- `admin_post_delete_contact_submission` - Contact deletion

**CDN Assets:**
- Bootstrap Icons v1.11.3 - `https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css`
  - Used for admin UI icons

## REST API

**ReflectiveRestWrapper:**
- Custom REST API implementation - `vendor/mxcro/makermaker-core/src/Rest/ReflectiveRestWrapper.php`
- Features:
  - Full-text search via `?search=term`
  - Field filtering via `?field=value`
  - Sorting via `?sort=-created_at`
  - Pagination via `?per_page=50&page=2`
  - Custom actions via POST `/actions/{action}`
- Endpoint pattern: `/tr-api/rest/{resource}/{id?}/actions/{action?}`
- Initialized in: `app/MakermakerTypeRocketPlugin.php:153-170`

## Email Notifications

**WordPress wp_mail():**
- Used for contact form admin notifications
- Location: `app/Controllers/ContactSubmissionController.php:1116`
- No external SMTP service detected

---

*Integration audit: 2026-01-07*
*Update when adding/removing external services*
