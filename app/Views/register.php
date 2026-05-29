<?= $this->extend('layout/auth') ?>

<?= $this->section('content') ?>
<div class="auth-tabs">
    <a href="/login" class="auth-tab" id="auth-tab-login">
        <i class="fas fa-sign-in-alt"></i> Sign In
    </a>
    <a href="/register" class="auth-tab active" id="auth-tab-register">
        <i class="fas fa-user-plus"></i> Register
    </a>
</div>

<div class="auth-body">
    <!-- Register Form -->
    <form class="auth-form" id="register-form" autocomplete="off">
        <div id="register-error" class="auth-error"></div>
        <div class="auth-field">
            <label for="reg-username"><i class="fas fa-user"></i> Username</label>
            <input type="text" id="reg-username" placeholder="Choose a username (min 3 chars)" required>
        </div>
        <div class="auth-field">
            <label for="reg-fullname"><i class="fas fa-id-card"></i> Full Name</label>
            <input type="text" id="reg-fullname" placeholder="Enter your full name" required>
        </div>
        <div class="auth-field">
            <label for="reg-email"><i class="fas fa-envelope"></i> Email ID</label>
            <input type="email" id="reg-email" placeholder="Enter email ID" required>
        </div>
        <div class="auth-field">
            <label for="reg-mobile"><i class="fas fa-phone"></i> Mobile / WhatsApp Number</label>
            <input type="tel" id="reg-mobile" placeholder="Enter mobile / WhatsApp number" required>
        </div>
        <div class="auth-field">
            <label for="reg-password"><i class="fas fa-lock"></i> Password</label>
            <input type="password" id="reg-password" placeholder="Choose a password (min 6 chars)" required>
        </div>
        <div class="auth-field">
            <label style="text-transform: uppercase; letter-spacing: 0.5px; font-size: 12px; font-weight: 600; color: var(--text-secondary);"><i class="fas fa-info-circle"></i> Account Role</label>
            <p style="font-size:12px; color:var(--text-muted); padding:8px 12px; background:var(--bg-primary); border:1px solid var(--border-color); border-radius:8px; margin:0; line-height:1.4;">
                <i class="fas fa-user" style="color:var(--accent-primary);"></i>
                New accounts start as <strong>User</strong> role. Admins can promote accounts later via Settings.
            </p>
            <input type="hidden" id="reg-role" value="user">
        </div>
        <button type="submit" class="auth-submit-btn" id="register-submit-btn">
            <i class="fas fa-user-plus"></i> Create Account
        </button>
    </form>
</div>
<?= $this->endSection() ?>
