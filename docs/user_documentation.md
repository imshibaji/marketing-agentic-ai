# Antigravity Marketing AI Agent - User Documentation

Welcome to the **Antigravity Marketing AI Agent Suite**! This guide details how to install the application, navigate the platform, leverage the AI-driven sales development representative (SDR) pipeline, qualify leads, and close deals through the sales confirmation process.

---

## 1. Application Installation Wizard

Before running the application, you must complete the initial configuration using the built-in Installation Wizard.

### Accessing the Installer
If the application is not yet configured, accessing the root URL (`http://127.0.0.1:8000/`) will automatically redirect you to the installer at:
`http://127.0.0.1:8000/install`

### Step-by-Step Installation Process

1. **Prerequisite System Checks**:
   The installer verifies system compatibility. All check indicators must be green to proceed:
   - **PHP Version**: Must be PHP 8.0.0 or higher.
   - **PDO Extensions**: Verifies that at least one PDO driver database extension is active (SQLite, MySQL, Postgres, SQL Server, or Oracle).
   - **File Permissions**: Verifies that the root folder and the `database/` directory are writable for configuration saving.

2. **Configure Database Connection**:
   Select your preferred SQL driver and enter connection parameters:
   - **SQLite**: (Recommended for simple setups) Specify the file path (default is `database/database.sqlite`).
   - **MySQL / MariaDB / PostgreSQL / Microsoft SQL Server**: Enter host, port, database name, username, and password.
   - **Oracle Database**: Enter host, port, credentials, and optional Oracle Service Name (SID).
   - *Test Connection*: Click **Test Connection** to verify settings before continuing.

3. **Create Administrator Account**:
   Set up your primary system administrator credentials. Enter a username (minimum 3 characters), secure password (minimum 6 characters), full name, and email.

4. **Global Configuration**:
   - **Application Name**: Set the title displayed across login headers.
   - **Google Gemini API Key**: Enter your Gemini API key (starts with `AIzaSy...`) obtained from Google AI Studio. This can be left blank and configured later.

5. **Execute Installation**:
   Click **Run Installer**. The installer will dynamically:
   - Write settings to the `.env` file.
   - Connect to the target database and execute migrations to create all 11 core tables.
   - Seed the default "Premium Plan" (unlimited campaigns/leads).
   - Register the admin account.
   - Save default settings and generate the lock file `database/install.lock` to prevent future unauthorized database resets.

---

## 2. Access Control & User Roles

Once installed, the application supports secure registration and two user roles:
* **Administrator (admin)**: Full control over global system settings, user quotas, usage plans, SMTP configurations, database backups, reset/demo triggers, and system activity logs.
* **User (user)**: Access to owned or shared campaigns, leads directories, contacts CRM, and the outreach workstation.

### Default Login Credentials (After Setup / Reset)
* **Admin Login**: `admin` / `admin` (or `testadmin` / `admin123`)
* **Standard User**: `user` / `user` (or `charlie` / `charlie`)

---

## 3. Campaigns Management

Campaigns define the target value proposition and target audience for the AI SDR agents.

### Creating a Campaign
1. Go to the **Campaigns** tab.
2. Click **New Campaign** or **Create First Campaign**.
3. Fill in:
   - **Campaign Title**: Product/service name.
   - **Value Proposition/Description**: Detailed description of what you are selling.
   - **Target Audience Profile**: Describe ideal customers (e.g. "CTOs of healthcare startups").
   - **Outreach Channel**: Target platform (Email, WhatsApp, or SMS).
   - **SDR Lead Scraper Settings**: Specify scraper targets, query strings, and language.
4. Click **Create Campaign**.

### Sharing Campaigns
If you own a campaign, click the **Share** button to grant read/write access to other team members.

---

## 4. Lead Qualification

The AI SDR Agent crawls online directories and qualifies prospects based on the value proposition defined in your campaign.

```
       [ Scraped / Manual Lead ] (Status: GENERATED)
                   │
                   ▼
       [ AI Qualification Evaluation ]
       ├── Computes Fit Score (0-100)
       └── Writes Detailed Qualification Reasoning
                   │
                   ▼
       [ Lead Status -> QUALIFIED ]
```

### The Qualification Process
Leads can be qualification-assessed through two channels:
1. **AI-Scraped Qualification**: When running the Lead Finder pipeline under a campaign, the AI Scraper gathers contact profiles (Company name, contact person, industry, description, and postal address). It automatically evaluates each prospect, assigning a **Fit Score** and a written **Qualification Reasoning**.
2. **Manual Addition/Edit**: You can click **Add Lead** in the Contacts/Leads tab to add a lead manually. Here, you can define your own **Fit Score** (HIGH, MEDIUM, LOW) and provide custom qualification notes.

### Fit Score and Reasoning
- **Fit Score (0-100)**: Reflects the strength of the match between the prospect's profile/needs and your campaign's target audience and value proposition.
- **Qualification Reasoning**: Explains why the lead matches or fails to match your campaign goals. This serves as critical context for writing outbound pitches.

---

## 5. The Outreach Workstation

The Outreach Workstation is where AI drafts personalized messages for individual leads.

### Generating Outreaches
1. Go to the **Outreach** tab.
2. Select a Campaign and choose your active **LLM Model**.
3. Select a contact from the sidebar list.
4. Click **Generate AI Outreach**.
5. The AI reads lead notes, qualification reasoning, and campaign value propositions to write 3 distinct drafts:
   - **Email Copy** (Subject + Body)
   - **WhatsApp Text**
   - **SMS Copy**

### Editing & Delivery
- **Interactive Editing**: Click inside the textareas to modify drafts. Progress is tracked in local state.
- **Save Draft**: Saves edited drafts to the database.
- **Copy Draft**: Copies text to the clipboard.
- **Send Outreach**: Simulates sending the draft message. Increments usage quotas and updates the prospect's CRM status to `OUTREACHED`.

---

## 6. The Sales Confirmation Process (Close Lead)

Once a lead has been contacted, the sales pipeline advances to confirmation:

```
    [ Status: QUALIFIED ]
               │
               ▼  (Send Outreach Action)
    [ Status: OUTREACHED ] (Outbound sent)
               │
               ▼  (Customer Responds positively & deal is verified)
    [ Status: CLOSED ] (Sales Confirmation)
```

1. **Marking as Outreached**:
   When you send a pitch to a prospect via Email, WhatsApp, or SMS, their status transitions to `OUTREACHED`. A progress badge on the sidebar lists and tables will show "outreached" in blue.

2. **Sales Confirmation / Closing**:
   When a prospect responds positively, book a call, or agree to a purchase deal:
   - Go to the **Outreach** tab.
   - Select the outreached lead from the contacts list.
   - Scroll to the bottom of the workstation panel.
   - Click the **Close Lead** button (represented by a handshake icon `fa-handshake`).
   - The lead's status transitions to `CLOSED` (completed), representing successful sales confirmation.

3. **Tracking Closed Deals**:
   - Filter leads in the Leads/Contacts tables by selecting **CLOSED** from the status filter dropdown.
   - The main **Dashboard** statistics dynamically increment the total closed CRM deals, allowing administrators to monitor conversions.

---

## 7. System Configuration (Administrators Only)

Click the cog icon on the top header to configure global options:

### LLM / AI Configuration
Toggle and configure API connections for active LLM providers:
* **Google Gemini API**: Enter your Cloud API Key and choose model versions (defaults to `gemini-1.5-flash`).
* **LM Studio / Ollama**: Set up local hosting connection URLs, models, and custom instructions.

### App Configuration
- **Application Name**: Change logo title text across header and login screens.
- **Public Chat Toggle**: Turn the dashboard chat room on/off.
- **System Notifications Toggle**: Turn the system alerts feed on/off.

### Gateways (SMTP / WhatsApp / SMS)
- **SMTP**: Add real SMTP credentials to enable actual email outreach dispatches. Leave as `mock` to simulate deliveries.
- **WhatsApp**: Configure Meta Cloud API bearer tokens and Phone ID.
- **SMS**: Select Twilio or input custom HTTP REST endpoint templates (using `{to}` and `{message}` placeholders).

### Database Management (Sub-tabs)
1. **Backup**: Click **Download Backup File** to fetch a complete timestamped copy of the `.sqlite` database or a driver-agnostic `.json` database file.
2. **Restore**: Select a `.sqlite`, `.db` or `.json` file and click **Restore Backup**. 
   > [!CAUTION]
   > Overwriting the database overwrites all user credentials, sessions, and configurations. You will be logged out upon success.
   > The system verifies file signature and connection integrity before overwriting. If the file is invalid, it rolls back to prevent data loss.
3. **Reset & Demo**:
   - **Load Demo Data**: Wipes campaigns/leads/logs/chats/notifications and seeds mock records (includes campaigns, qualified leads with drafts, welcome chats, and notifications) so the app is immediately testable. Keep current session active.
   - **Reset Application**: Deletes database file completely, rebuilds empty database schemas, and seeds default user credentials, logging the admin out.
