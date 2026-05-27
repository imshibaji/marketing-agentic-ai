# Marketing AI Agent - User Documentation

Welcome to the **Antigravity Marketing AI Agent Suite**! This guide details how to navigate the platform, leverage the AI-driven sales development representative (SDR) pipeline, manage leads, compose personalized outreach, and perform administrative operations.

---

## 1. Access Control & Roles

The application supports secure registration and two user roles:
* **Administrator (admin)**: Full control over global system settings, user quotas, usage plans, SMTP configurations, database backups, reset/demo triggers, and system activity logs.
* **User (user)**: Access to owned or shared campaigns, leads directories, contacts CRM, and the outreach workstation.

### Default Login Credentials (After Setup / Reset)
* **Admin Login**: `admin` / `admin` (or `testadmin` / `admin123`)
* **Standard User**: `user` / `user` (or `charlie` / `charlie`)

---

## 2. The Dashboard

The Dashboard provides a unified overview of system status and quick access controls:

### Resource Quotas & Balances
Shows dynamic cards monitoring your active usage limits:
- **LLM AI Runs**: Track AI generations used against plan limit.
- **Campaigns**: Number of campaigns created.
- **Leads CRM**: Size of CRM database.
- **Outreach Logs**: Tracks Email, WhatsApp, and SMS messages sent.

### Split Panel Sections (20:40:40)
The bottom half of the dashboard contains three columns aligned to match height precisely:
1. **Outreach Quick Actions (20% Width)**: Select a campaign to load qualified contacts. Displays company name, fit score, and delivery status badges. Includes a **Quick Send Email** action and an **Open in Workstation** redirect link.
2. **Public Chat Room (40% Width)**: A live shared message board. Supports user tagging with `@username` which automatically sends email alerts.
3. **System Notifications (40% Width)**: System-wide notifications feed. Administrators can publish announcements here. Admins also see trash-bin icons to delete historical alerts.

> [!NOTE]
> If the administrator hides the Public Chat or Notifications feeds from settings, the Outreach Mini Panel automatically expands to fill the remaining horizontal space.

---

## 3. Campaigns Management

Campaigns define the target value proposition and audience for the AI SDR agents.

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

## 4. Leads Scraper & Qualification CRM

The AI SDR Agent crawls online directories and qualify prospects according to the campaign requirements.

### Running the Scraper
1. From the Campaign Workspace, click **Run AI Scraper Pipeline**.
2. Select the **Active LLM Provider** model from settings.
3. The AI agent will scrape results, qualify them, and populate the table with:
   - **Company Name**
   - **Contact Name & Postal Address**
   - **Email Address & Mobile/WhatsApp Number**
   - **Industry & Description**
   - **Fit Score (0-100)**: AI-computed alignment score.
   - **Qualification Reasoning**: Explanation of why the lead fits.

### Managing Prospects
- **Search & Filter**: Search prospects dynamically by company name, industry, or contact details. Filter by Fit Score thresholds.
- **Edit Leads**: Double-click any row to edit fields manually.
- **Save to Contacts**: Click **Save to Contacts** to add the prospect to the shared Contacts Directory (keeps all address and telephone info intact).

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

## 6. System Configuration (Administrators Only)

Click the cog icon on the top header to configure global options. The database panel features three interactive sub-tabs:

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
1. **Backup**: Click **Download Backup File** to fetch a complete timestamped copy of the `.sqlite` database.
2. **Restore**: Select a `.sqlite` or `.db` file and click **Restore Backup**. 
   > [!CAUTION]
   > Overwriting the database overwrites all user credentials, sessions, and configurations. You will be logged out upon success.
   > The system verifies the SQLite file signature and connection integrity before overwriting. If the file is invalid, it rolls back to prevent data loss.
3. **Reset & Demo**:
   - **Load Demo Data**: Wipes campaigns/leads/logs/chats/notifications and seeds mock records (includes campaigns, qualified leads with drafts, welcome chats, and notifications) so the app is immediately testable. Keep current session active.
   - **Reset Application**: Deletes database file completely, rebuilds empty database schemas, and seeds default user credentials, logging the admin out.

### Plugins Management (Settings Tab)
Administrators can enable and disable dynamic features via the **Plugins** settings panel.
* **View Installed Plugins**: Displays the title, description, version, and author for each discovered plugin inside the `plugins/` directory.
* **Activation / Deactivation**: Toggle the **Activate** or **Deactivate** action buttons. The application automatically reloads to register or unregister the plugin's backend events, assets, and frontend components.

