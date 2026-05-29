<?php $activeTab = 'plans'; ?>
<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<!-- Tab: Usage Plans Management -->
<div id="tab-plans" class="workspace-panel">
    <!-- Usage Plans Management Panel -->
    <div class="panel-section card-box" style="display:flex; flex-direction:column; gap:16px; max-height:calc(100vh - 180px); overflow-y:auto; padding-right:4px; min-width:0;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:18px;"><i class="fas fa-tags" style="color:var(--accent-primary); margin-right:6px;"></i> Usage Plans Management</h3>
            <button class="btn-primary" id="admin-add-plan-btn" style="font-size:12px; padding:6px 12px; height:auto; width:auto; margin-top:0;">
                <i class="fas fa-plus"></i> Create New Plan
            </button>
        </div>
        <div style="width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch;">
            <table class="custom-table" id="admin-plans-table" style="width:100%; border-collapse:collapse; text-align:left;">
                <thead>
                    <tr style="border-bottom:2px solid var(--border-color); color:var(--text-secondary); font-size:11px; text-transform:uppercase; letter-spacing:0.5px;">
                        <th style="padding:12px 10px;">Plan Name</th>
                        <th style="padding:12px 10px;">Campaigns</th>
                        <th style="padding:12px 10px;">Leads</th>
                        <th style="padding:12px 10px;">LLM Limit</th>
                        <th style="padding:12px 10px;">Email Limit</th>
                        <th style="padding:12px 10px;">WhatsApp Limit</th>
                        <th style="padding:12px 10px;">SMS Limit</th>
                        <th style="padding:12px 10px;">Active Users</th>
                        <th style="padding:12px 10px; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="admin-plans-tbody" style="font-size:12px;">
                    <!-- Loaded dynamically via JS -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Plan Modal Dialog -->
<div class="modal-overlay" id="plan-modal" style="z-index:10080;">
    <div class="modal-content" style="max-width:520px;">
        <div class="modal-header">
            <h3 id="plan-modal-title" style="font-size:18px;"><i class="fas fa-tags"></i> Create New Plan</h3>
            <button class="modal-close" id="plan-modal-close"><i class="fas fa-times"></i></button>
        </div>
        <form id="plan-modal-form" style="display:flex; flex-direction:column; gap:14px; margin-top:8px;">
            <input type="hidden" id="plan-modal-id" value="">
            
            <div class="form-group">
                <label for="plan-modal-name"><i class="fas fa-signature"></i> Plan Name *</label>
                <input type="text" id="plan-modal-name" required placeholder="e.g. Premium Tier">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label for="plan-modal-campaigns"><i class="fas fa-bullhorn"></i> Campaigns (-1 = unl)</label>
                    <input type="number" id="plan-modal-campaigns" value="10" required>
                </div>
                <div class="form-group">
                    <label for="plan-modal-leads"><i class="fas fa-users-rectangle"></i> Leads (-1 = unl)</label>
                    <input type="number" id="plan-modal-leads" value="50" required>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:10px;">
                <div class="form-group">
                    <label for="plan-modal-llm" class="label-compact"><i class="fas fa-brain"></i> LLM (-1 = unl)</label>
                    <input type="number" id="plan-modal-llm" value="100" required>
                </div>
                <div class="form-group">
                    <label for="plan-modal-email" class="label-compact"><i class="fas fa-envelope"></i> Email (-1 = unl)</label>
                    <input type="number" id="plan-modal-email" value="100" required>
                </div>
                <div class="form-group">
                    <label for="plan-modal-whatsapp" class="label-compact"><i class="fab fa-whatsapp"></i> WA (-1 = unl)</label>
                    <input type="number" id="plan-modal-whatsapp" value="100" required>
                </div>
                <div class="form-group">
                    <label for="plan-modal-sms" class="label-compact"><i class="fas fa-sms"></i> SMS (-1 = unl)</label>
                    <input type="number" id="plan-modal-sms" value="100" required>
                </div>
            </div>

            <div id="plan-modal-error" style="color:var(--accent-error); font-size:13px; display:none;"></div>

            <div style="display:flex; gap:10px;">
                <button type="submit" class="btn-primary" id="plan-modal-save-btn" style="flex:1; margin-top:0; padding:10px;">
                    <i class="fas fa-save"></i> Save Plan
                </button>
                <button type="button" class="btn-secondary" id="plan-modal-cancel-btn" style="padding:10px 16px;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
