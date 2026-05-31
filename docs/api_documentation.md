# Marketing AI Agent - API Documentation

This document describes all API endpoints exposed by the **Marketing AI Agent Suite** REST API.

All authenticated API routes require the user to be logged in (monitored by the `auth` route filter). Standard request body payloads are expected as JSON (`Content-Type: application/json`).

---

## 1. Authentication Endpoints (Public)

### POST `api/login.php`
Authenticate a user.
- **Request Body**:
  ```json
  {
    "username": "admin",
    "password": "password"
  }
  ```
- **Response (Success)**:
  ```json
  {
    "success": true,
    "user": {
      "id": 1,
      "username": "admin",
      "role": "admin",
      "full_name": "Administrator",
      "email": "admin@example.com"
    }
  }
  ```

### POST `api/logout.php`
Invalidates the current session.
- **Response (Success)**:
  ```json
  {
    "success": true,
    "message": "Logged out successfully"
  }
  ```

### GET `api/auth-status.php`
Retrieve active session status.
- **Response (Success)**:
  ```json
  {
    "success": true,
    "logged_in": true,
    "user": {
      "id": 1,
      "username": "admin",
      "role": "admin"
    }
  }
  ```

### POST `api/register.php`
Register a new account (or submit username/email for OTP generation).
- **Request Body**:
  ```json
  {
    "username": "newuser",
    "password": "password123",
    "email": "newuser@example.com",
    "full_name": "New User"
  }
  ```

### POST `api/otp.php`
Verify registration OTP.
- **Request Body**:
  ```json
  {
    "email": "newuser@example.com",
    "otp": "123456"
  }
  ```

---

## 2. Campaigns API (Authenticated)

### GET `api/campaigns.php`
Retrieve campaigns. If `id` query parameter is present, retrieves one campaign; otherwise, lists all campaigns the current user has access to.
- **Query Parameters**:
  - `id` (integer, optional)
- **Response (List Success)**:
  ```json
  {
    "success": true,
    "campaigns": [
      {
        "id": 1,
        "title": "Campaign Title",
        "product_description": "...",
        "target_audience": "...",
        "channel": "email",
        "status": "CREATED",
        "final_content": null,
        "language": "English",
        "created_at": "2026-05-31 12:00:00"
      }
    ]
  }
  ```

### POST `api/campaigns.php`
Create or update a campaign.
- **Request Body**:
  ```json
  {
    "id": 1, // optional, present for edits
    "title": "Campaign Title",
    "product_description": "Product Description Details",
    "target_audience": "Target Audience Details",
    "channel": "email", // email, whatsapp, sms, or multi
    "crawl_type": "none", // none, single_page, or sitemap
    "crawl_target": "", // optional URL
    "language": "English",
    "llm_provider": "gemini"
  }
  ```

### DELETE `api/campaigns.php`
Delete a campaign.
- **Query Parameters**:
  - `id` (integer, required)

### GET `api/run-campaign.php`
Executes campaign generation using Server-Sent Events (SSE). Establishes a stream to deliver progress logs and updates.
- **Query Parameters**:
  - `id` (integer, required)
- **SSE Stream Outputs**:
  - `event: log` (delivers progress logs payload)
  - `event: complete` (delivers final generated text)
  - `event: error` (details failures/expired plan restrictions)

---

## 3. Leads API (Authenticated)

### GET `api/leads.php`
Retrieve leads.
- **Query Parameters**:
  - `campaign_id` (integer, optional)
  - `id` (integer, optional)

### POST `api/leads.php`
Create, update, or edit leads and their outreach drafts.
- **Request Body (Manual Addition/Edit)**:
  ```json
  {
    "id": null, // Present with integer for edits
    "campaign_id": 1,
    "company_name": "Globex Corp",
    "contact_name": "Homer Simpson",
    "email": "homer@globex.com",
    "whatsapp": "+15550199",
    "mobile": "+15550199",
    "industry": "Energy",
    "description": "Reactor manager...",
    "score": "HIGH",
    "reasoning": "Fits target profile",
    "email_draft": "Hello Homer...",
    "whatsapp_draft": "Hi Homer...",
    "sms_draft": "Hi Homer...",
    "calls_draft": "Hello Homer...",
    "status": "QUALIFIED"
  }
  ```
- **Request Body (Update Outreach Drafts Only)**:
  ```json
  {
    "action": "update_drafts",
    "lead_id": 1,
    "email_draft": "Updated email...",
    "whatsapp_draft": "Updated whatsapp...",
    "sms_draft": "Updated sms...",
    "calls_draft": "Updated call script..."
  }
  ```

### DELETE `api/leads.php`
Delete a lead.
- **Query Parameters**:
  - `id` (integer, required)

### GET `api/run-leads.php`
Find and score campaign prospects using local/search tool queries. SSE streaming.
- **Query Parameters**:
  - `campaign_id` (integer, required)
  - `keywords` (string, required)
  - `location` (string, optional)

### GET `api/run-scraper.php`
Run standalone Google Maps scraper with LLM qualification. SSE streaming.
- **Query Parameters**:
  - `campaign_id` (integer, required)
  - `location` (string, required)
  - `keywords` (string, required)
  - `source_type` (string, defaults to `maps_search`)

---

## 4. Plans API (Admin Only)

### GET `api/plans.php`
Retrieve plans.
- **Query Parameters**:
  - `id` (integer, optional)

### POST `api/plans.php`
Create or update a plan.
- **Request Body**:
  ```json
  {
    "id": 1, // optional for edit
    "name": "Standard Plan",
    "campaign_limit": 10,
    "lead_limit": 50,
    "llm_limit": 100,
    "email_limit": 100,
    "whatsapp_limit": 100,
    "sms_limit": 100,
    "duration": "1 Month" // e.g., 30 Days, 1 Year, 23 Hours
  }
  ```

### DELETE `api/plans.php`
Delete a plan.
- **Query Parameters**:
  - `id` (integer, required)

---

## 5. Users API (Admin / Authenticated)

### GET `api/users.php` (Admin Only)
Retrieve all users list.

### POST `api/users.php` (Admin Only)
Create or edit a user.
- **Request Body**:
  ```json
  {
    "id": 1, // optional for edit
    "username": "john_doe",
    "password": "password123", // optional for edits
    "role": "user", // user, admin
    "full_name": "John Doe",
    "email": "john@example.com",
    "mobile": "+123456",
    "whatsapp_number": "+123456",
    "plan_id": 2
  }
  ```

### DELETE `api/users.php` (Admin Only)
Delete a user.
- **Query Parameters**:
  - `id` (integer, required)

### POST `api/reset-usage.php` (Admin Only)
Reset user's monthly limits back to 0.
- **Request Body**:
  ```json
  {
    "user_id": 1
  }
  ```

### POST `api/reset-plan.php` (Admin Only)
Recalculates and renews user's subscription expiration date based on their plan's duration.
- **Request Body**:
  ```json
  {
    "user_id": 1
  }
  ```

### GET `api/activity.php`
Get user activity log history.

### GET `api/stats.php`
Retrieve global stats (campaign count, lead counts, active plans, usage metrics).

### GET `api/usage.php`
Retrieve current user's limits and usage quotas.
- **Response**:
  ```json
  {
    "success": true,
    "plan_name": "Standard Plan",
    "plan_expires_at": "2026-06-30 12:00:00",
    "active_providers": ["gemini"],
    "usage": {
      "llm": { "used": 5, "limit": 100 },
      "email": { "used": 2, "limit": 100 },
      "whatsapp": { "used": 0, "limit": 100 },
      "sms": { "used": 1, "limit": 100 }
    }
  }
  ```

### GET / POST `api/profile.php`
Load or update the logged-in user's profile details.

---

## 6. Outreach API (Authenticated)

### POST `api/outreach.php`
Send outreach message (email, WhatsApp, SMS).
- **Request Body**:
  ```json
  {
    "lead_id": 1,
    "type": "email", // email, whatsapp, sms
    "destination": "homer@globex.com",
    "message": "Outreach text here",
    "subject": "Intro Mail" // Optional (required for email type)
  }
  ```

### POST `api/generate-outreach.php`
Generates channel-specific outreach drafts (Email, WhatsApp, SMS, and Call script) for a prospect.
- **Request Body**:
  ```json
  {
    "lead_id": 1,
    "campaign_id": 2
  }
  ```
- **Response**:
  ```json
  {
    "success": true,
    "email_draft": "...",
    "whatsapp_draft": "...",
    "sms_draft": "...",
    "calls_draft": "..."
  }
  ```

---

## 7. System Operations (Admin Only)

### GET `api/backup.php`
Initiates a database backup file download (either `.sqlite` or `.json` based on active driver).

### POST `api/restore.php`
Restore database from uploaded backup file. Expects `backup_file` in a standard multipart form post.

### POST `api/reset-app.php`
Drops all tables and runs migrations to return database to a clean, fresh seed state.

### POST `api/load-demo.php`
Wipes existing data and seeds rich demo campaigns, leads, public chat logs, and notifications.

### GET `api/plugins.php`
Lists all installed plugins and their active states.

### POST `api/plugins.php`
Activate/deactivate a plugin.
- **Request Body**:
  ```json
  {
    "action": "activate", // activate, deactivate
    "plugin": "slack-notifier"
  }
  ```

### GET / POST `api/plugin-route.php`
Forwards requests dynamically to plugins using a `route` parameter query.
