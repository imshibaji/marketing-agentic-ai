<?php $activeTab = 'contacts'; ?>
<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<!-- Tab 2.5: Contacts Directory -->
<div id="tab-contacts" class="workspace-panel" style="overflow:hidden; display:flex; flex-direction:column; gap:20px; height:100%;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <div>
            <h2 style="font-size:22px; margin:0; display:flex; align-items:center; gap:8px;">
                <i class="fas fa-address-book" style="color:var(--accent-primary);"></i> Contacts Directory
            </h2>
            <p style="font-size:12px; color:var(--text-secondary); margin:4px 0 0;">
                View and inspect qualification reports and custom outreach drafts for all your contacts.
            </p>
        </div>
        <!-- Search & Filter Controls -->
        <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            <div class="search-box" style="position:relative; width:220px;">
                <i class="fas fa-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:12px;"></i>
                <input type="text" id="contacts-search-input" placeholder="Search contacts..." style="width:100%; padding:8px 10px 8px 30px; font-size:12px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-primary); color:var(--text-primary); outline:none;">
            </div>
            <select id="contacts-filter-score" style="background:var(--bg-primary); border:1px solid var(--border-color); padding:8px 10px; border-radius:6px; font-family:var(--font-body); font-size:12px; color:var(--text-primary);">
                <option value="ALL">All Scores</option>
                <option value="HIGH">High Fit</option>
                <option value="MEDIUM">Medium Fit</option>
                <option value="LOW">Low Fit</option>
            </select>
            <select id="contacts-filter-status" style="background:var(--bg-primary); border:1px solid var(--border-color); padding:8px 10px; border-radius:6px; font-family:var(--font-body); font-size:12px; color:var(--text-primary);">
                <option value="ALL">All Statuses</option>
                <option value="GENERATED">Generated</option>
                <option value="QUALIFIED">Qualified</option>
                <option value="OUTREACHED">Outreached</option>
                <option value="CLOSED">Closed Leads</option>
            </select>
            <button id="contacts-add-btn" class="btn-primary" style="font-size:12px; padding:8px 14px; margin:0; height:auto;">
                <i class="fas fa-user-plus"></i> Add Contact
            </button>
        </div>
    </div>

    <!-- Contacts Table Card -->
    <div class="panel-section card-box" style="flex-grow:1; display:flex; flex-direction:column; overflow:hidden; padding:20px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:var(--border-radius-md); box-shadow:var(--shadow-sm); height:calc(100% - 80px);">
        <div style="overflow-y:auto; flex-grow:1;">
            <table class="custom-table" id="contacts-table" style="width:100%; border-collapse:collapse; text-align:left;">
                <thead>
                    <tr style="border-bottom:2px solid var(--border-color); color:var(--text-secondary); font-size:11px; text-transform:uppercase; letter-spacing:0.5px;">
                        <th style="padding:12px 10px;">Contact</th>
                        <th style="padding:12px 10px;">Company</th>
                        <th style="padding:12px 10px;">Industry</th>
                        <th style="padding:12px 10px;">Email</th>
                        <th style="padding:12px 10px;">WhatsApp</th>
                        <th style="padding:12px 10px;">Mobile</th>
                        <th style="padding:12px 10px;">Fit Score</th>
                        <th style="padding:12px 10px;">Status</th>
                        <th style="padding:12px 10px;">Source</th>
                        <th style="padding:12px 10px;">Campaign</th>
                        <th style="padding:12px 10px;">Owner</th>
                        <th style="padding:12px 10px; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="contacts-tbody" style="font-size:12px;">
                    <!-- Loaded dynamically via JS -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Contact Details Modal (Slide Drawer style) -->
<div class="modal-overlay" id="contact-details-modal" style="z-index:10090;">
    <div class="modal-content" style="max-width:700px; width:95%; padding:24px; max-height:90vh; display:flex; flex-direction:column; gap:16px; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
            <div>
                <h3 id="contact-modal-company" style="font-size:22px; margin:0; font-family:var(--font-heading);">Company Name</h3>
                <span id="contact-modal-score" class="lead-score-badge" style="display:inline-block; margin-top:6px;">HIGH FIT</span>
            </div>
            <button class="modal-close" id="contact-modal-close"><i class="fas fa-times"></i></button>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; background:var(--bg-primary); padding:16px; border-radius:8px; border:1px solid var(--border-color); font-size:13px;">
            <div><strong><i class="fas fa-user-tie"></i> Contact:</strong> <span id="contact-modal-name">Name</span></div>
            <div><strong><i class="fas fa-briefcase"></i> Industry:</strong> <span id="contact-modal-industry">Industry</span></div>
            <div><strong><i class="fas fa-envelope"></i> Email:</strong> <span id="contact-modal-email">Email</span></div>
            <div><strong><i class="fab fa-whatsapp"></i> WhatsApp:</strong> <span id="contact-modal-whatsapp">WhatsApp</span></div>
            <div><strong><i class="fas fa-phone"></i> Mobile:</strong> <span id="contact-modal-mobile">Mobile</span></div>
            <div><strong><i class="fas fa-database"></i> Data Source:</strong> <span id="contact-modal-source">Source</span></div>
            <div><strong><i class="fas fa-bullhorn"></i> Campaign Context:</strong> <span id="contact-modal-campaign">Campaign</span></div>
            <div><strong><i class="fas fa-user-circle"></i> Owner:</strong> <span id="contact-modal-owner">Owner</span></div>
            <div style="grid-column: span 2;"><strong><i class="fas fa-map-marker-alt"></i> Postal Address:</strong> <span id="contact-modal-postal-address">Postal Address</span></div>
        </div>

        <div id="contact-modal-desc-container" style="display:flex; flex-direction:column; gap:6px;">
            <label style="font-weight:600; font-size:13px;">Description / Requirements / Notes:</label>
            <div id="contact-modal-description" style="background:var(--bg-primary); padding:12px; border-radius:6px; border:1px solid var(--border-color); font-size:12px; line-height:1.5; max-height:120px; overflow-y:auto;">
            </div>
        </div>

        <div style="display:flex; flex-direction:column; gap:6px;">
            <label style="font-weight:600; font-size:13px;">SDR AI Qualification Reasoning:</label>
            <div id="contact-modal-reasoning" style="background:var(--bg-primary); padding:12px; border-radius:6px; border:1px solid var(--border-color); font-size:12px; line-height:1.5; max-height:120px; overflow-y:auto;">
                Reasoning details...
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:8px;">
            <button class="btn-primary" id="contact-modal-close-btn" style="padding:8px 16px; margin-top:0;">Close</button>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
