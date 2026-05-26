# Marketing AI Agentic Automation Application

An autonomous marketing campaign planner, SEO analyzer, and B2B Lead Qualification CRM system built with raw, object-oriented PHP. It utilizes a collaborative multi-agent architecture (Researcher, Copywriter, Editor, Lead Agent, and Coordinator) that generates high-end campaigns and discovers, qualifies, and drafts personalized outreach (Email + WhatsApp) for target leads.

## Features

- **Day & Night UI**: Sleek, glassmorphic layout supporting system preferences and persistent manually toggled dark/light themes.
- **Local & Cloud Multi-LLM Support**: Supports Google Gemini (Cloud), Ollama (Local), and LM Studio (Local) models.
- **Multi-Agent Pipeline**: View the Coordinator orchestrating the Researcher $\rightarrow$ Copywriter $\rightarrow$ Editor agent loop in real-time.
- **SDR Lead Generation CRM**: Auto-generate target prospects, qualify their fit (High/Medium/Low) based on Ideal Customer Profile (ICP), and compose custom Email & WhatsApp drafts for direct copy or simulated API outreach.
- **SQLite Storage**: Fully local campaign logs, settings, and CRM contacts database.
- **Lightweight PSR-4 Autoloader**: Pure PHP code running without any Node or Composer dependencies!

---

## Folder Structure

- `database/`: Local SQLite files.
- `public/`: Web dashboard interface, stylesheets, and streaming API endpoints.
- `src/Agent/`: Agent core logic classes.
- `src/Service/`: Database connection and cURL LLM provider handlers.
- `src/Tool/`: Search engine and lead generation mock scraping tools.
- `scratch/`: CLI-level diagnostic test scripts.

---

## Setup & Running

### 1. Requirements
Ensure you have PHP 8.0+ installed with the following extensions active:
- `sqlite3` and `pdo_sqlite`
- `curl`

### 2. Start the Server
Start PHP's built-in web server from the project directory:
```bash
php -S localhost:8000 -t public
```

Open your browser and navigate to:
```
http://localhost:8000
```

### 3. Configure LLM Provider
Click the **Gear icon (Settings)** in the top right corner of the dashboard to configure:
- **Gemini API**: Add your API Key from Google AI Studio.
- **LM Studio**: Run your local LM Studio server (typically `http://localhost:1234/v1`).
- **Ollama**: Start Ollama on your system (`http://localhost:11434`).

For real website search results, configure a Bing Search API key in your PHP environment using `BING_SEARCH_API_KEY` so the research agent can call live search engine data for site-specific analysis.

---

## CLI Diagnostic Scripts

We have provided CLI scripts to test backend functions without using the browser dashboard.

### Test LLM Connection
Verify if your selected settings and model connection are working properly:
```bash
php scratch/test_llm.php
```

### Test SDR Lead Generation & Qualification
Run the lead generation and qualifying flow in CLI:
```bash
php scratch/test_leads.php
```
