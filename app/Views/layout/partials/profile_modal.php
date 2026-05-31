<!-- MY PROFILE MODAL -->
<div class="modal-overlay" id="profile-modal" style="z-index:10050;">
    <div class="modal-content" style="max-width:420px;">
        <div class="modal-header">
            <h3 style="font-size:18px;"><i class="fas fa-user-circle"></i> My Profile</h3>
            <button class="modal-close" id="profile-modal-close"><i class="fas fa-times"></i></button>
        </div>

        <form id="profile-edit-form" style="display:flex; flex-direction:column; gap:16px; margin-top:8px;">
            <div class="form-group">
                <label for="profile-username"><i class="fas fa-user"></i> Username</label>
                <input type="text" id="profile-username" required minlength="3">
            </div>

            <div class="form-group">
                <label for="profile-fullname"><i class="fas fa-id-card"></i> Full Name</label>
                <input type="text" id="profile-fullname" placeholder="Enter your full name">
            </div>

            <div class="form-group">
                <label for="profile-email"><i class="fas fa-envelope"></i> Email ID</label>
                <input type="email" id="profile-email" placeholder="Enter your email ID">
            </div>

            <div class="form-group">
                <label for="profile-mobile"><i class="fas fa-phone"></i> Mobile Number</label>
                <input type="tel" id="profile-mobile" placeholder="Enter mobile number">
            </div>

            <div class="form-group">
                <label for="profile-whatsapp"><i class="fab fa-whatsapp"></i> WhatsApp Number</label>
                <input type="tel" id="profile-whatsapp" placeholder="Enter WhatsApp number">
            </div>

            <div class="form-group">
                <label for="profile-password"><i class="fas fa-lock"></i> Password
                    <span style="font-weight:400; font-size:11px; color:var(--text-muted);"> (leave blank to keep current)</span>
                </label>
                <input type="password" id="profile-password" placeholder="New password (min 6 chars)">
            </div>

            <div id="profile-modal-error" style="color:var(--accent-error); font-size:13px; display:none;"></div>

            <div style="display:flex; gap:10px;">
                <button type="submit" class="btn-primary" id="profile-modal-save-btn" style="flex:1; margin-top:0; padding:10px;">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <button type="button" class="btn-secondary" id="profile-modal-cancel-btn" style="padding:10px 16px;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
