<?php $activeTab = 'outreach'; ?>
<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<!-- Outreach / Followup Panel -->
<div id="tab-outreach" class="workspace-panel" style="overflow:hidden; height:100%; min-height:0; display:flex; flex-direction:column; gap:16px;">
    <div class="pipeline-layout" style="display:grid; grid-template-columns: 320px 1fr; gap:20px; height:100%; max-height:100%; min-height:0; overflow:hidden;">
        
        <!-- Sidebar: Settings & Contacts List -->
        <div class="card-box" style="display:flex; flex-direction:column; gap:16px; height:100%; max-height:100%; overflow:hidden; padding:20px;">
            <h3 style="margin:0; font-size:16px;"><i class="fas fa-paper-plane" style="color:var(--accent-primary); margin-right:6px;"></i> Outreach Settings</h3>
            
            <div class="form-group" style="margin-bottom:0; display:flex; flex-direction:column; gap:6px;">
                <label for="outreach-campaign-select" style="font-size:11px; font-weight:600; color:var(--text-secondary);">Select Campaign</label>
                <select id="outreach-campaign-select" style="width:100%; background:var(--bg-primary); border:1px solid var(--border-color); padding:8px 10px; border-radius:6px; font-family:var(--font-body); font-size:12px; color:var(--text-primary); outline:none;">
                    <option value="">— Select Campaign —</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:0; display:flex; flex-direction:column; gap:6px;">
                <label for="outreach-llm-select" style="font-size:11px; font-weight:600; color:var(--text-secondary);"><i class="fas fa-brain"></i> Select LLM Model</label>
                <select id="outreach-llm-select" style="width:100%; background:var(--bg-primary); border:1px solid var(--border-color); padding:8px 10px; border-radius:6px; font-family:var(--font-body); font-size:12px; color:var(--text-primary); outline:none;">
                    <option value="">— Loading models… —</option>
                </select>
            </div>

            <hr style="border:0; border-top:1px solid var(--border-color); margin:4px 0;">

            <div style="display:flex; flex-direction:column; flex-grow:1; min-height:0; overflow:hidden;">
                <h4 style="font-size:12px; font-weight:600; color:var(--text-secondary); margin:0 0 10px 0; display:flex; align-items:center; justify-content:space-between;">
                    <span>Campaign Contacts</span>
                    <span id="outreach-contacts-count" style="background:var(--bg-primary); border:1px solid var(--border-color); padding:2px 6px; border-radius:10px; font-size:10px;">0</span>
                </h4>
                <div id="outreach-contacts-list" style="overflow-y:auto; flex-grow:1; display:flex; flex-direction:column; gap:8px; padding-right:4px;">
                    <div style="color:var(--text-muted); font-size:12px; text-align:center; padding:20px;">Choose a campaign above to load contacts.</div>
                </div>
            </div>
        </div>

        <!-- Main Workspace Area -->
        <div class="card-box" style="display:flex; flex-direction:column; height:100%; max-height:100%; overflow:hidden; padding:24px; position:relative;">
            
            <!-- Empty State -->
            <div id="outreach-empty-state" style="display:flex; flex-direction:column; justify-content:center; align-items:center; height:100%; text-align:center; gap:12px; color:var(--text-muted);">
                <i class="fas fa-envelope-open-text" style="font-size:48px; color:var(--border-color);"></i>
                <h4 style="margin:0; font-size:16px; color:var(--text-secondary);">Outreach Workstation</h4>
                <p style="margin:0; font-size:13px; max-width:400px; line-height:1.5;">Select a campaign and a contact card from the sidebar list to generate and manage outreach emails, WhatsApp messages, or SMS drafts.</p>
            </div>

            <!-- Workspace Content (hidden by default) -->
            <div id="outreach-workspace-content" class="hidden" style="display:flex; flex-direction:column; height:100%; max-height:100%; overflow:hidden; gap:16px;">
                <!-- Contact Details Card -->
                <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:8px; padding:16px; font-size:12px; display:flex; flex-direction:column; gap:8px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                        <div>
                            <h4 id="outreach-contact-company" style="font-size:18px; margin:0 0 4px 0; font-family:var(--font-heading);">Company Name</h4>
                            <span id="outreach-contact-score" class="lead-score-badge">HIGH FIT</span>
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-top:4px;">
                        <div><strong><i class="fas fa-user-tie"></i> Contact:</strong> <span id="outreach-contact-name">Name</span></div>
                        <div><strong><i class="fas fa-briefcase"></i> Industry:</strong> <span id="outreach-contact-industry">Industry</span></div>
                        <div><strong><i class="fas fa-envelope"></i> Email:</strong> <span id="outreach-contact-email">Email</span></div>
                        <div><strong><i class="fab fa-whatsapp"></i> WhatsApp:</strong> <span id="outreach-contact-whatsapp">WhatsApp</span></div>
                        <div><strong><i class="fas fa-phone"></i> Mobile:</strong> <span id="outreach-contact-mobile">Mobile</span></div>
                        <div><strong><i class="fas fa-database"></i> Source:</strong> <span id="outreach-contact-source">Source</span></div>
                        <div style="grid-column: span 3;"><strong><i class="fas fa-map-marker-alt"></i> Address:</strong> <span id="outreach-contact-address">Address</span></div>
                    </div>
                    
                    <div id="outreach-contact-desc-container" style="border-top:1px solid var(--border-color); padding-top:8px; margin-top:4px; display:none;">
                        <strong>Description / Requirements:</strong>
                        <div id="outreach-contact-desc" style="color:var(--text-secondary); line-height:1.4; margin-top:2px;"></div>
                    </div>
                </div>

                <!-- Channels Navigation & Draft Edit Pane -->
                <div style="display:flex; flex-direction:column; flex-grow:1; min-height:0; overflow:hidden; gap:10px;">
                    <div class="lead-outreach-tabs" style="margin-bottom:0;">
                        <button class="outreach-tab-btn active" id="outreach-pane-tab-email"><i class="fas fa-envelope"></i> Email Draft</button>
                        <button class="outreach-tab-btn" id="outreach-pane-tab-whatsapp"><i class="fab fa-whatsapp"></i> WhatsApp Draft</button>
                        <button class="outreach-tab-btn" id="outreach-pane-tab-sms"><i class="fas fa-comment-alt"></i> SMS Draft</button>
                    </div>

                    <textarea id="outreach-pane-textarea" style="width:100%; flex-grow:1; min-height:150px; padding:16px; font-family:monospace; font-size:13px; background:var(--bg-primary); color:var(--text-primary); border:1px solid var(--border-color); border-radius:6px; outline:none; resize:none; line-height:1.5;"></textarea>
                </div>

                <!-- Actions Row -->
                <div style="display:flex; gap:12px; align-items:center;">
                    <button class="btn-secondary" id="outreach-generate-btn" style="background:var(--bg-primary);"><i class="fas fa-bolt"></i> Generate Outreach (AI)</button>
                    <button class="btn-secondary" id="outreach-save-btn"><i class="fas fa-save"></i> Save Draft</button>
                    <button class="btn-secondary" id="outreach-copy-btn"><i class="fas fa-copy"></i> Copy Draft</button>
                    
                    <button class="btn-primary" id="outreach-send-btn" style="margin-left:auto; margin-top:0; border-color:var(--accent-success); background:linear-gradient(135deg, var(--accent-success), var(--accent-secondary));"><i class="fas fa-paper-plane"></i> Send Outreach</button>
                </div>
            </div>

        </div>
    </div>
</div>
<?= $this->endSection() ?>
