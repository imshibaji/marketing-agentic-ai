<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App Already Installed</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: radial-gradient(ellipse at 20% 50%, hsla(265,80%,15%,0.95) 0%, hsla(220,30%,8%,0.98) 60%);
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #f1f2f6;
        }
        .lock-card {
            background: rgba(22, 28, 45, 0.45);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 40px;
            max-width: 460px;
            text-align: center;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.4);
        }
    </style>
</head>
<body>
    <div class="lock-card">
        <i class="fas fa-lock" style="font-size: 48px; color: var(--accent-primary, #9b51e0); margin-bottom: 20px;"></i>
        <h2 style="margin: 0 0 10px 0;">Installation Locked</h2>
        <p style="color: #a4b0be; font-size: 14px; line-height: 1.5; margin-bottom: 24px;">
            The application is already installed and configured. To rerun the installation wizard, you must delete the lock file located at:
            <code style="display:block; background:rgba(0,0,0,0.3); padding:10px; border-radius:6px; margin:10px 0; font-family:monospace; color:#ff7675; font-size:12px;">database/install.lock</code>
        </p>
        <a href="/" style="display:inline-block; background:linear-gradient(135deg, #8e44ad, #2980b9); color:white; text-decoration:none; padding:12px 24px; border-radius:10px; font-weight:600; font-size:14px; box-shadow: 0 8px 16px rgba(142,68,173,0.3);">
            Go to Application Log In
        </a>
    </div>
</body>
</html>
