<?= $this->extend('layout/auth') ?>

<?= $this->section('content') ?>
<div class="auth-tabs">
    <a href="/login" class="auth-tab active" id="auth-tab-login">
        <i class="fas fa-sign-in-alt"></i> Sign In
    </a>
    <a href="/register" class="auth-tab" id="auth-tab-register">
        <i class="fas fa-user-plus"></i> Register
    </a>
</div>

<div class="auth-body">
    <!-- Login Form -->
    <form class="auth-form" id="login-form" autocomplete="on">
        <div id="login-error" class="auth-error"></div>
        
        <div id="password-login-fields">
            <div class="auth-field">
                <label for="login-username"><i class="fas fa-user"></i> Username</label>
                <input type="text" id="login-username" placeholder="Enter your username" autocomplete="username">
            </div>
            <div class="auth-field">
                <label for="login-password"><i class="fas fa-lock"></i> Password</label>
                <input type="password" id="login-password" placeholder="Enter your password" autocomplete="current-password">
            </div>
        </div>

        <div id="otp-login-fields" class="hidden">
            <div id="otp-dev-banner" class="hidden" style="margin-bottom:12px; font-size:11px; padding:10px 14px; background:rgba(var(--accent-primary-rgb), 0.1); border:1px solid var(--accent-primary); border-radius:8px; color:var(--accent-primary); line-height:1.4;"></div>
            <div class="auth-field">
                <label for="login-email"><i class="fas fa-envelope"></i> Email Address</label>
                <div style="display:flex; gap:8px;">
                    <input type="email" id="login-email" placeholder="Enter your registered email ID" style="flex:1;">
                    <button type="button" class="btn-primary" id="send-otp-btn" style="margin-top:0; font-size:11px; padding:8px 12px; width:auto; white-space:nowrap; height:auto; border-radius:6px; line-height:1;">Send OTP</button>
                </div>
            </div>
            <div class="auth-field hidden" id="otp-code-group">
                <label for="login-otp"><i class="fas fa-key"></i> One-Time Password (OTP)</label>
                <input type="text" id="login-otp" placeholder="Enter 6-digit OTP code">
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end; margin-bottom:16px;">
            <a href="#" id="toggle-otp-login" style="font-size:11px; color:var(--accent-primary); text-decoration:none; font-weight:600;">Log in with Email OTP instead</a>
        </div>

        <button type="submit" class="auth-submit-btn" id="login-submit-btn">
            <i class="fas fa-sign-in-alt"></i> Sign In
        </button>
    </form>
</div>
<?= $this->endSection() ?>
