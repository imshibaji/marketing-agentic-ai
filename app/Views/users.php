<?php $activeTab = 'users'; ?>
<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<!-- Tab: Users Management -->
<div id="tab-users" class="workspace-panel">
    <!-- User Management Panel -->
    <div class="panel-section card-box" style="display:flex; flex-direction:column; gap:16px; max-height:calc(100vh - 180px); overflow-y:auto; padding-right:4px; min-width:0;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:18px;"><i class="fas fa-users-cog" style="color:var(--accent-primary); margin-right:6px;"></i> User Management</h3>
            <button class="btn-primary" id="admin-add-user-btn" style="font-size:12px; padding:6px 12px; height:auto; width:auto; margin-top:0;">
                <i class="fas fa-plus"></i> Add New User
            </button>
        </div>
        <div style="width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch;">
            <table class="custom-table" id="admin-users-table" style="width:100%; border-collapse:collapse; text-align:left;">
                <thead>
                    <tr style="border-bottom:2px solid var(--border-color); color:var(--text-secondary); font-size:11px; text-transform:uppercase; letter-spacing:0.5px;">
                        <th style="padding:12px 10px;">Username</th>
                        <th style="padding:12px 10px;">Full Name</th>
                        <th style="padding:12px 10px;">Email</th>
                        <th style="padding:12px 10px;">Mobile / WhatsApp</th>
                        <th style="padding:12px 10px;">Role</th>
                        <th style="padding:12px 10px;">Plan</th>
                        <th style="padding:12px 10px;">Plan Expiry</th>
                        <th style="padding:12px 10px;">Campaigns Usage</th>
                        <th style="padding:12px 10px;">Leads Usage</th>
                        <th style="padding:12px 10px;">LLM Usage</th>
                        <th style="padding:12px 10px;">Emails Sent</th>
                        <th style="padding:12px 10px;">WhatsApp Sent</th>
                        <th style="padding:12px 10px;">SMS Sent</th>
                        <th style="padding:12px 10px; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="admin-users-tbody" style="font-size:12px;">
                    <!-- Loaded dynamically via JS -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit / Add User Modal -->
<div class="modal-overlay" id="user-modal" style="z-index:10100;">
    <div class="modal-content" style="max-width:460px;">
        <div class="modal-header">
            <h3 id="user-modal-title" style="font-size:18px;"><i class="fas fa-user-edit"></i> Edit User</h3>
            <button class="modal-close" id="user-modal-close"><i class="fas fa-times"></i></button>
        </div>

        <form id="user-edit-form" style="display:flex; flex-direction:column; gap:12px; margin-top:8px; max-height:80vh; overflow-y:auto; padding-right:4px;">
            <input type="hidden" id="edit-user-id" value="">

            <div class="form-group">
                <label for="edit-username"><i class="fas fa-user"></i> Username *</label>
                <input type="text" id="edit-username" placeholder="e.g. john_doe" required minlength="3">
            </div>

            <div class="form-group">
                <label for="edit-password"><i class="fas fa-lock"></i> Password
                    <span id="edit-password-hint" style="font-weight:400; font-size:11px; color:var(--text-muted);"> (leave blank to keep current)</span>
                </label>
                <input type="password" id="edit-password" placeholder="New password (min 6 chars)">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label for="edit-fullname"><i class="fas fa-id-card"></i> Full Name</label>
                    <input type="text" id="edit-fullname" placeholder="Enter full name">
                </div>
                <div class="form-group">
                    <label for="edit-email"><i class="fas fa-envelope"></i> Email ID</label>
                    <input type="email" id="edit-email" placeholder="Enter email">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label for="edit-mobile"><i class="fas fa-phone"></i> Mobile Number</label>
                    <input type="tel" id="edit-mobile" placeholder="Enter mobile">
                </div>
                <div class="form-group">
                    <label for="edit-whatsapp"><i class="fab fa-whatsapp"></i> WhatsApp Number</label>
                    <input type="tel" id="edit-whatsapp" placeholder="Enter WhatsApp">
                </div>
            </div>

            <div class="form-group">
                <label for="edit-role"><i class="fas fa-shield-alt"></i> Role</label>
                <select id="edit-role">
                    <option value="user">User — Own data only</option>
                    <option value="admin">Admin — Full access + settings</option>
                </select>
            </div>

            <div class="form-group">
                <label for="edit-user-plan"><i class="fas fa-tags"></i> Assigned Usage Plan</label>
                <select id="edit-user-plan">
                    <!-- Loaded dynamically via JS -->
                </select>
            </div>

            <div id="user-modal-error" style="color:var(--accent-error); font-size:13px; display:none;"></div>

            <div style="display:flex; gap:10px;">
                <button type="submit" class="btn-primary" id="user-modal-save-btn" style="flex:1; margin-top:0; padding:10px;">
                    <i class="fas fa-save"></i> Save User
                </button>
                <button type="button" class="btn-secondary" id="user-modal-cancel-btn" style="padding:10px 16px;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal-overlay" id="delete-user-modal" style="z-index:10200;">
    <div class="modal-content" style="max-width:360px; gap:16px;">
        <div class="modal-header">
            <h3 style="font-size:17px; color:var(--accent-error);"><i class="fas fa-exclamation-triangle"></i> Delete User</h3>
            <button class="modal-close" id="delete-user-modal-close"><i class="fas fa-times"></i></button>
        </div>
        <p style="font-size:14px; color:var(--text-secondary);">Are you sure you want to delete <strong id="delete-user-name"></strong>? This action cannot be undone. Their campaigns will become unassigned.</p>
        <div style="display:flex; gap:10px;">
            <button class="btn-primary" id="delete-user-confirm-btn" style="flex:1; margin-top:0; padding:10px; background:linear-gradient(135deg,var(--accent-error),#c0392b);">
                <i class="fas fa-trash"></i> Yes, Delete
            </button>
            <button class="btn-secondary" id="delete-user-cancel-btn" style="padding:10px 16px;">Cancel</button>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
