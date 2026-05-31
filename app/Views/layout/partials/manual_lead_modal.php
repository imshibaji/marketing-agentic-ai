<!-- MANUAL LEAD ADD MODAL -->
<div class="modal-overlay" id="manual-lead-modal" style="z-index:10080;">
    <div class="modal-content" style="max-width:600px;">
        <div class="modal-header">
            <h3 style="font-size:18px;"><i class="fas fa-user-plus"></i> Add Lead Manually</h3>
            <button class="modal-close" id="manual-lead-modal-close"><i class="fas fa-times"></i></button>
        </div>

        <form id="manual-lead-form" style="display:flex; flex-direction:column; gap:14px; margin-top:8px; max-height:84vh; overflow-y:auto; padding-right:4px;">
            <input type="hidden" id="manual-lead-id" value="">
            <div class="form-group">
                <label for="manual-lead-campaign"><i class="fas fa-bullhorn"></i> Associate with Campaign</label>
                <select id="manual-lead-campaign">
                    <option value="">None (General CRM Lead)</option>
                </select>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label for="manual-lead-company"><i class="fas fa-building"></i> Company Name *</label>
                    <input type="text" id="manual-lead-company" required placeholder="e.g. Acme Corp">
                </div>
                <div class="form-group">
                    <label for="manual-lead-contact"><i class="fas fa-user"></i> Contact Name</label>
                    <input type="text" id="manual-lead-contact" placeholder="e.g. John Doe">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label for="manual-lead-email"><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" id="manual-lead-email" placeholder="e.g. contact@acme.com">
                </div>
                <div class="form-group">
                    <label for="manual-lead-whatsapp"><i class="fab fa-whatsapp"></i> WhatsApp Number</label>
                    <input type="text" id="manual-lead-whatsapp" placeholder="e.g. +919876543210">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label for="manual-lead-mobile"><i class="fas fa-phone"></i> Mobile Number</label>
                    <input type="text" id="manual-lead-mobile" placeholder="e.g. +919876543210">
                </div>
                <div class="form-group">
                    <label for="manual-lead-source"><i class="fas fa-database"></i> Data Source</label>
                    <input type="text" id="manual-lead-source" placeholder="e.g. manual, linkedin, website" value="manual">
                </div>
            </div>

            <div class="form-group">
                <label for="manual-lead-postal-address"><i class="fas fa-map-marker-alt"></i> Postal Address</label>
                <input type="text" id="manual-lead-postal-address" placeholder="e.g. 123 Main St, Kolkata, WB 700001">
            </div>

            <div id="manual-lead-owner-group" class="form-group" style="display:none;">
                <label for="manual-lead-owner"><i class="fas fa-user-shield"></i> Contact Owner (Admin)</label>
                <select id="manual-lead-owner">
                    <option value="">— Keep Current Owner —</option>
                </select>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label for="manual-lead-industry"><i class="fas fa-industry"></i> Industry</label>
                    <input type="text" id="manual-lead-industry" placeholder="e.g. Tech, Retail">
                </div>
                <div class="form-group">
                    <label for="manual-lead-score"><i class="fas fa-star"></i> Fit Score</label>
                    <select id="manual-lead-score">
                        <option value="HIGH">High Fit</option>
                        <option value="MEDIUM" selected>Medium Fit</option>
                        <option value="LOW">Low Fit</option>
                    </select>
                </div>
                <div class="form-group" id="manual-lead-status-group">
                    <label for="manual-lead-status"><i class="fas fa-tag"></i> Lead Status</label>
                    <select id="manual-lead-status">
                        <option value="">— Keep Current —</option>
                        <option value="GENERATED">Generated</option>
                        <option value="QUALIFIED">Qualified</option>
                        <option value="OUTREACHED">Outreached</option>
                        <option value="CLOSED">Closed</option>
                        <option value="LOST">Lost</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="manual-lead-desc"><i class="fas fa-info-circle"></i> Description / Requirements</label>
                <textarea id="manual-lead-desc" placeholder="e.g. Looking for digital marketing services" style="height:60px; resize:none; padding:8px; border-radius:6px; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary); outline:none; font-family:var(--font-body); font-size:12px;"></textarea>
            </div>

            <div class="form-group">
                <label for="manual-lead-reasoning"><i class="fas fa-brain"></i> Qualification Reasoning</label>
                <input type="text" id="manual-lead-reasoning" value="Manually added" placeholder="Reasoning for fit score">
            </div>

            <div id="manual-lead-error" style="color:var(--accent-error); font-size:13px; display:none;"></div>

            <!-- Outreach Draft Samples Section -->
            <div style="border:1px solid var(--border-color); border-radius:8px; overflow:hidden;">
                <div id="manual-lead-drafts-toggle" style="background:var(--bg-secondary); padding:10px 14px; display:flex; align-items:center; justify-content:space-between; cursor:pointer; user-select:none;">
                    <span style="font-size:12px; font-weight:600; color:var(--text-primary);"><i class="fas fa-paper-plane" style="color:var(--accent-primary); margin-right:6px;"></i>Outreach Draft Samples</span>
                    <i class="fas fa-chevron-down" id="manual-lead-drafts-chevron" style="font-size:11px; color:var(--text-muted); transition:transform 0.2s;"></i>
                </div>
                <div id="manual-lead-drafts-body" style="display:none; padding:12px; flex-direction:column; gap:10px;">
                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                        <button type="button" class="draft-modal-tab active" data-tab="email" id="modal-draft-tab-email" style="padding:5px 12px; font-size:11px; font-weight:600; border-radius:4px; border:1px solid var(--accent-primary); background:rgba(99,102,241,0.15); color:var(--accent-primary); cursor:pointer;"><i class="fas fa-envelope"></i> Email</button>
                        <button type="button" class="draft-modal-tab" data-tab="whatsapp" id="modal-draft-tab-whatsapp" style="padding:5px 12px; font-size:11px; font-weight:600; border-radius:4px; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-secondary); cursor:pointer;"><i class="fab fa-whatsapp"></i> WhatsApp</button>
                        <button type="button" class="draft-modal-tab" data-tab="sms" id="modal-draft-tab-sms" style="padding:5px 12px; font-size:11px; font-weight:600; border-radius:4px; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-secondary); cursor:pointer;"><i class="fas fa-comment-alt"></i> SMS</button>
                        <button type="button" class="draft-modal-tab" data-tab="calls" id="modal-draft-tab-calls" style="padding:5px 12px; font-size:11px; font-weight:600; border-radius:4px; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-secondary); cursor:pointer;"><i class="fas fa-phone"></i> Call Script</button>
                    </div>
                    <textarea id="manual-lead-email-draft" placeholder="Email outreach draft..." style="width:100%; height:120px; resize:vertical; padding:10px; border-radius:6px; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary); outline:none; font-family:monospace; font-size:12px; line-height:1.5; box-sizing:border-box;"></textarea>
                    <textarea id="manual-lead-whatsapp-draft" placeholder="WhatsApp message draft..." style="display:none; width:100%; height:120px; resize:vertical; padding:10px; border-radius:6px; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary); outline:none; font-family:monospace; font-size:12px; line-height:1.5; box-sizing:border-box;"></textarea>
                    <textarea id="manual-lead-sms-draft" placeholder="SMS draft (max 160 chars)..." style="display:none; width:100%; height:80px; resize:vertical; padding:10px; border-radius:6px; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary); outline:none; font-family:monospace; font-size:12px; line-height:1.5; box-sizing:border-box;"></textarea>
                    <textarea id="manual-lead-calls-draft" placeholder="Phone call script..." style="display:none; width:100%; height:140px; resize:vertical; padding:10px; border-radius:6px; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary); outline:none; font-family:monospace; font-size:12px; line-height:1.5; box-sizing:border-box;"></textarea>
                </div>
            </div>

            <div style="display:flex; gap:10px;">
                <button type="submit" class="btn-primary" id="manual-lead-save-btn" style="flex:1; margin-top:0; padding:10px;">
                    <i class="fas fa-plus"></i> Add Lead
                </button>
                <button type="button" class="btn-secondary" id="manual-lead-cancel-btn" style="padding:10px 16px;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
