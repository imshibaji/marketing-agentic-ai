<?php $activeTab = 'leads'; ?>
<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<!-- Tab 2: Leads CRM & Qualification -->
<div id="tab-leads" class="workspace-panel" style="overflow:hidden; height:100%; min-height:0;">
    
    <div class="crm-layout" style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:20px; height:100%; min-height:0; overflow:hidden;">
        <!-- Column 1: Contacts List & CRUD (Left) -->
        <div class="crm-sidebar" style="display:flex; flex-direction:column; gap:16px; border-right:1px solid var(--border-color); padding-right:20px; height:100%; max-height:100%; min-height:0; overflow:hidden;">
            <div class="crm-board-header" style="display:flex; flex-direction:column; gap:10px; align-items:flex-start; margin-bottom:0; width:100%;">
                <h3 id="crm-board-title" style="font-size:18px;">Scraped Prospects</h3>
                <select id="crm-campaign-filter" style="width:100%; background:var(--bg-primary); border:1px solid var(--border-color); padding:8px 10px; border-radius:6px; font-family:var(--font-body); font-size:12px; color:var(--text-primary); outline:none; margin-bottom:10px;">
                    <option value="">All Campaigns &amp; Manual Leads</option>
                    <option value="unsaved_scraper">Latest Scraper Results (Unsaved)</option>
                </select>
            </div>
            
            <div id="leads-vertical-list" style="display:flex; flex-direction:column; gap:12px; overflow-y:auto; flex-grow:1; padding-right:4px;">
                <!-- Lead Cards rendered here -->
            </div>
        </div>

        <!-- Column 2: Leads Scraper Panel (Middle) -->
        <div class="crm-scraper-column" style="display:flex; flex-direction:column; gap:16px; border-right:1px solid var(--border-color); padding-right:20px; overflow-y:auto; height:100%; max-height:100%;">
            <!-- SDR Scraper Panel -->
            <div class="sdr-panel" style="background:var(--bg-card); border:1px solid var(--border-color); padding:16px; border-radius:var(--border-radius-sm); display:flex; flex-direction:column; gap:12px; box-shadow:var(--shadow-sm);">
                <h4 style="font-size:14px; margin:0; display:flex; align-items:center; gap:6px;"><i class="fas fa-search-dollar" style="color:var(--accent-primary);"></i> Leads Scraper Settings</h4>
                <div class="form-group" style="margin-bottom:0; display:flex; flex-direction:column; gap:4px;">
                    <label for="crm-lead-source-type" style="font-size:10px; font-weight:600; color:var(--text-secondary);">Lead Scraping Source</label>
                    <select id="crm-lead-source-type" style="width:100%; background:var(--bg-primary); border:1px solid var(--border-color); padding:8px 10px; border-radius:6px; font-family:var(--font-body); font-size:12px; color:var(--text-primary); outline:none;">
                        <option value="maps_search">Google Maps Search</option>
                        <option value="maps_link">Custom Google Maps URL</option>
                        <option value="website">Custom Website URL</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:0; display:flex; flex-direction:column; gap:4px;">
                    <label for="scraper-llm-provider" style="font-size:10px; font-weight:600; color:var(--text-secondary);"><i class="fas fa-brain"></i> Select LLM Model (Only Active Can Be Selected)</label>
                    <select id="scraper-llm-provider" style="width:100%; background:var(--bg-primary); border:1px solid var(--border-color); padding:8px 10px; border-radius:6px; font-family:var(--font-body); font-size:12px; color:var(--text-primary); outline:none;">
                        <option value="">— Loading active models… —</option>
                    </select>
                </div>
                
                <!-- Dynamic custom inputs -->
                <div id="crm-source-target-container" class="hidden" style="display:flex; flex-direction:column; gap:4px;">
                    <label id="crm-source-target-label" style="font-size:10px; font-weight:600; color:var(--text-secondary);">Target Link / URL</label>
                    <input type="text" id="crm-source-target-input" placeholder="e.g. https://example.com" style="width:100%; font-size:12px; padding:8px 10px; background:var(--bg-primary); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); outline:none;">
                </div>
                <div id="crm-source-search-container" style="display:flex; flex-direction:column; gap:4px;">
                    <label style="font-size:10px; font-weight:600; color:var(--text-secondary);">Location &amp; Keywords</label>
                    <div style="display:flex; gap:8px; width:100%;">
                        <input type="text" id="crm-source-loc-input" placeholder="City, State" style="flex:1; font-size:12px; padding:8px 10px; background:var(--bg-primary); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); outline:none;">
                        <input type="text" id="crm-source-kw-input" placeholder="Keywords" style="flex:1; font-size:12px; padding:8px 10px; background:var(--bg-primary); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); outline:none;">
                    </div>
                </div>
                
                <button class="btn-primary" id="crm-generate-leads-btn" style="width: 100%; margin-top:4px; font-size:12px; padding:8px 12px; height:auto; line-height:1;">
                    <i class="fas fa-bolt"></i> Run Scraper
                </button>
            </div>

            <!-- Real-time SDR Scraper Console Logs -->
            <div class="crm-console-section" style="display:flex; flex-direction:column; gap:6px; flex-grow:1; min-height:180px;">
                <h4 style="font-size:11px; font-weight:600; color:var(--text-secondary); margin:0; display:flex; align-items:center; gap:6px;"><i class="fas fa-terminal"></i> Scraper Agent Run Logs</h4>
                <div class="console-log" id="crm-console-log" style="flex-grow:1; min-height:160px; font-size:11px; padding:10px; background:var(--bg-primary); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); font-family:monospace; overflow-y:auto;">
                    <!-- Logs append here -->
                </div>
            </div>
        </div>

        <!-- Column 3: Lead Details Panel (Right) -->
        <div class="lead-detail-panel" style="border-left:none; padding-left:0; overflow-y:auto; height:100%; max-height:100%;">
            <div id="lead-detail-empty" class="lead-detail-empty">
                <i class="fas fa-address-card" style="font-size:32px;"></i>
                <p>Select a scraped prospect card from the list to view contact details and save them to contacts.</p>
            </div>
            <div id="lead-detail-content" class="hidden">
                <!-- Rendered dynamically via JS -->
            </div>
        </div>
    </div>

</div>
<?= $this->endSection() ?>
