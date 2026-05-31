<?php $activeTab = 'campaigns'; ?>
<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<!-- Tab 1: Campaigns & Agent Output -->
<div id="tab-campaigns" class="workspace-panel">
    
    <!-- View A: Campaign Creator Form -->
    <div id="campaign-creator-panel" class="creator-container">
        <h2>Build Marketing Campaigns</h2>
        <p>Set parameters and let Researcher, Copywriter, and Editor agents build SEO reports and channel copy automatically.</p>
        
        <form id="campaign-creator-form">
            <div class="form-group">
                <label for="campaign_title">Campaign / Product Title</label>
                <input type="text" id="campaign_title" placeholder="e.g. Acme DevFlow Planner" required>
            </div>
            
            <div class="form-group">
                <label for="product_description">Product / Service Description</label>
                <textarea id="product_description" rows="4" placeholder="Describe the core features, value proposition, and problems it solves..." required></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="target_audience">Target Audience</label>
                    <input type="text" id="target_audience" placeholder="e.g. B2B Software Developers, CTOs" required>
                </div>
                <div class="form-group">
                    <label for="channel">Marketing Channel</label>
                    <select id="channel" required>
                        <option value="email">Email Outreach Campaign</option>
                        <option value="social">Social Media Posts (LinkedIn, X)</option>
                        <option value="blog">SEO Blog Post &amp; Outline</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="campaign_crawl_type">Research &amp; Scraping Source</label>
                    <select id="campaign_crawl_type" required>
                        <option value="none">None (AI Generated Search Metrics)</option>
                        <option value="website">Website Link (Analyze target website)</option>
                        <option value="maps_link">Google Maps URL (Analyze business list from URL)</option>
                        <option value="maps_search">Google Maps Search (Location &amp; Keywords)</option>
                    </select>
                </div>
                <div class="form-group hidden" id="campaign-crawl-target-group">
                    <label id="campaign-crawl-target-label" for="campaign_crawl_target">Crawl Link / URL</label>
                    <input type="text" id="campaign_crawl_target" placeholder="e.g. https://example.com">
                </div>
                <div class="form-group hidden" id="campaign-crawl-search-group">
                    <label>Location &amp; Keywords</label>
                    <div style="display:flex; gap:10px;">
                        <input type="text" id="campaign_crawl_loc" placeholder="Location (e.g. Dallas, TX)" style="flex:1;">
                        <input type="text" id="campaign_crawl_kw" placeholder="Keywords (e.g. Gyms)" style="flex:1;">
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="position:relative;">
                    <label for="campaign-language-search"><i class="fas fa-globe"></i> Campaign Target Language</label>
                    <div id="campaign-language-picker" style="position:relative;">
                        <div id="campaign-language-display" style="width:100%; background:var(--bg-primary); border:1px solid var(--border-color); padding:10px 32px 10px 12px; border-radius:6px; font-family:var(--font-body); font-size:13px; color:var(--text-primary); cursor:pointer; display:flex; align-items:center; justify-content:space-between; user-select:none; box-sizing:border-box;">
                            <span id="campaign-language-label"><i class="fas fa-flag" style="margin-right:5px; opacity:0.6;"></i>English</span>
                            <i class="fas fa-chevron-down" id="campaign-language-chevron" style="font-size:10px; color:var(--text-muted); transition:transform 0.2s; pointer-events:none;"></i>
                        </div>
                        <input type="hidden" id="campaign_language" value="English">
                        <!-- Dropdown panel -->
                        <div id="campaign-language-dropdown" style="display:none; position:absolute; top:calc(100% + 4px); left:0; right:0; background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:8px; z-index:9999; box-shadow:0 8px 24px rgba(0,0,0,0.3); overflow:hidden; max-height:280px; flex-direction:column;">
                            <div style="padding:8px; border-bottom:1px solid var(--border-color);">
                                <input type="text" id="campaign-language-search" placeholder="&#128269; Search language..." autocomplete="off" style="width:100%; padding:7px 10px; border-radius:5px; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary); font-size:12px; font-family:var(--font-body); outline:none; box-sizing:border-box;">
                            </div>
                            <div id="campaign-language-list" style="overflow-y:auto; max-height:210px; padding:4px;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-primary" style="width: 100%;">
                <i class="fas fa-circle-plus"></i> Save Campaign Details
            </button>
        </form>
    </div>

    <!-- View B: Pipeline View (Visible when Campaign is Selected) -->
    <div id="campaign-workspace-panel" class="pipeline-layout hidden">
        
        <!-- Left Console (Agent states & Logs) -->
        <div class="pipeline-left">
            <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:16px; border-bottom:1px solid var(--border-color); padding-bottom:14px;">
                <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                    <h3 style="font-size:16px; margin:0;">Agent Execution Pipeline</h3>
                    <button class="btn-primary" id="run-campaign-btn" style="font-size:12px; padding: 8px 12px; margin:0;">
                        <i class="fas fa-play"></i> Run Agents
                    </button>
                </div>

                <!-- LLM Selector -->
                <div style="display:flex; align-items:center; gap:8px; width:100%;">
                    <label for="campaign-llm-provider" style="font-size:11px; color:var(--text-secondary); white-space:nowrap; font-weight:600;"><i class="fas fa-brain"></i> SELECT LLM MODEL:</label>
                    <select id="campaign-llm-provider" class="form-control" style="font-size:12px; padding:4px 8px; height:auto; margin:0; flex-grow:1; background:var(--bg-card); color:var(--text-primary); border:1px solid var(--border-color); border-radius:4px;">
                        <option value="">— Loading active models… —</option>
                    </select>
                </div>
            </div>
            
            <div class="agent-stages">
                <!-- Stage 1 -->
                <div class="agent-stage-card" id="stage-researcher">
                    <div class="agent-icon"><i class="fas fa-magnifying-glass"></i></div>
                    <div class="agent-details">
                        <h4>Researcher Agent</h4>
                        <span>Queries SEO metrics &amp; competitors</span>
                    </div>
                </div>
                <!-- Stage 2 -->
                <div class="agent-stage-card" id="stage-copywriter">
                    <div class="agent-icon"><i class="fas fa-pen-nib"></i></div>
                    <div class="agent-details">
                        <h4>Creative Copywriter</h4>
                        <span>Drafts channel copy based on research</span>
                    </div>
                </div>
                <!-- Stage 3 -->
                <div class="agent-stage-card" id="stage-editor">
                    <div class="agent-icon"><i class="fas fa-check-double"></i></div>
                    <div class="agent-details">
                        <h4>Editorial Specialist</h4>
                        <span>Refines tone, readability &amp; formats</span>
                    </div>
                </div>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px;">
                <h4 style="font-size:13px; color:var(--text-secondary);"><i class="fas fa-terminal"></i> Agent Console Logs</h4>
            </div>
            <div class="console-log" id="console-log">
                <!-- Logs append here -->
            </div>
            
            <div style="display:flex; flex-direction:column; gap:6px; margin-top:15px; margin-bottom:12px; width:100%;">
                <label for="lead-source-input" style="font-size:11px; font-weight:600; color:var(--text-secondary);"><i class="fas fa-search-location"></i> Lead Source URL / Google Maps Query</label>
                <input type="text" id="lead-source-input" placeholder="Google Maps (Default) or Directory URL" style="font-size:12px; padding:10px 12px; background:var(--bg-primary); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); outline:none;">
            </div>

            <button class="btn-secondary" id="generate-leads-btn" style="width: 100%; border-color:var(--accent-primary); color:var(--accent-primary); font-weight:600; margin-top:0;">
                <i class="fas fa-bolt"></i> Generate &amp; Qualify Leads
            </button>
        </div>

        <!-- Right Output (Final Result) -->
        <div class="pipeline-right">
            <div class="output-header">
                <div>
                    <h3 id="workspace-title">Campaign Name</h3>
                    <span id="workspace-meta" style="font-size:12px; color:var(--text-muted);">Metadata...</span>
                </div>
                <div class="output-actions" style="display:flex; gap:8px; align-items:center;">
                    <span class="campaign-badge" id="workspace-badge">Status</span>
                    <button class="btn-secondary hidden" id="share-campaign-btn" style="padding:8px 12px; font-size:12px;"><i class="fas fa-share-nodes"></i> Share</button>
                    <button class="btn-secondary hidden" id="copy-copy-btn"><i class="fas fa-copy"></i> Copy</button>
                    <button class="btn-secondary hidden" id="edit-copy-btn"><i class="fas fa-edit"></i> Edit</button>
                    <button class="btn-primary hidden" id="save-copy-btn" style="padding: 8px 12px; margin-top:0;"><i class="fas fa-save"></i> Save</button>
                    <button class="btn-secondary hidden" id="cancel-edit-btn" style="padding: 8px 12px;"><i class="fas fa-times"></i> Cancel</button>
                </div>
            </div>
            <div class="result-content" style="position:relative; display:flex; flex-direction:column; padding:0;">
                <div class="markdown-body" id="result-output" style="padding: 24px; flex-grow:1; overflow-y:auto; width:100%; height:100%;">
                    <!-- Markdown content loaded here -->
                </div>
                <textarea id="result-editor" class="hidden" style="width:100%; height:100%; flex-grow:1; min-height:400px; padding:24px; background:var(--bg-primary); color:var(--text-primary); border:none; font-family:monospace; font-size:14px; outline:none; resize:none; line-height:1.5;"></textarea>
            </div>
        </div>

    </div>

</div>
<?= $this->endSection() ?>
