<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title id="page-title">Marketing AI Agent - Multi-Agent AI Automation</title>
    
    <meta name="color-scheme" content="light dark">
    <script>
    // Prevent Flash of Unstyled Content (FOUC) by resolving theme immediately
    {
        const colorScheme = localStorage.getItem("color-scheme");
        if (colorScheme) {
            document.documentElement.setAttribute('data-theme', colorScheme);
        } else {
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-theme', systemPrefersDark ? 'dark' : 'light');
        }
    }
    </script>

    <link rel="stylesheet" href="/style.css">
    <!-- FontAwesome for Premium Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: radial-gradient(ellipse at 20% 50%, hsla(265,80%,15%,0.95) 0%, hsla(220,30%,8%,0.98) 60%);
            font-family: var(--font-body);
            color: var(--text-primary);
        }

        .auth-card {
            width: 100%;
            max-width: 440px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            box-shadow: 0 32px 80px hsla(265,80%,10%,0.6), 0 0 0 1px hsla(265,80%,50%,0.08);
            overflow: hidden;
            animation: auth-enter 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes auth-enter {
            from { opacity: 0; transform: translateY(24px) scale(0.95); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .auth-header {
            background: linear-gradient(135deg, hsla(265,80%,30%,0.5), hsla(220,60%,20%,0.3));
            border-bottom: 1px solid var(--border-color);
            padding: 32px 40px 28px;
            text-align: center;
        }

        .auth-logo {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 800;
            color: white;
            margin: 0 auto 16px;
            box-shadow: 0 8px 24px hsla(265,80%,50%,0.4);
        }

        .auth-app-name {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            letter-spacing: -0.3px;
        }

        .auth-tagline {
            font-size: 13px;
            color: var(--text-secondary);
            margin: 4px 0 0;
        }

        .auth-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
        }

        .auth-tab {
            flex: 1;
            padding: 16px;
            background: transparent;
            border: none;
            color: var(--text-secondary);
            font-family: var(--font-body);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border-bottom: 2px solid transparent;
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .auth-tab.active {
            color: var(--accent-primary);
            border-bottom-color: var(--accent-primary);
            background: hsla(265,80%,50%,0.06);
        }

        .auth-body {
            padding: 32px 40px 36px;
        }

        .auth-form { display: flex; flex-direction: column; gap: 18px; }
        .auth-form.hidden { display: none !important; }

        .auth-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .auth-field label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .auth-field input,
        .auth-field select {
            padding: 12px 16px;
            background: var(--bg-primary);
            border: 1.5px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-primary);
            font-family: var(--font-body);
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .auth-field input:focus,
        .auth-field select:focus {
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px hsla(265,80%,50%,0.12);
        }

        .auth-submit-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            border: none;
            border-radius: 10px;
            color: white;
            font-family: var(--font-body);
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 4px;
            box-shadow: 0 4px 16px hsla(265,80%,50%,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .auth-submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px hsla(265,80%,50%,0.5);
        }

        .auth-submit-btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
            transform: none;
        }

        .auth-error {
            background: hsla(0,80%,50%,0.1);
            border: 1px solid hsla(0,80%,50%,0.3);
            border-radius: 8px;
            color: var(--accent-error);
            font-size: 13px;
            padding: 10px 14px;
            display: none;
        }

        .auth-error.visible { display: block; }
    </style>
</head>
<body>

    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-logo" id="auth-logo-letter">A</div>
            <h1 class="auth-app-name" id="auth-app-name">Marketing AI Agent</h1>
            <p class="auth-tagline">Multi-Agent AI Marketing Automation</p>
        </div>

        <?= $this->renderSection('content') ?>
    </div>

    <!-- Pass Auth Context to JavaScript -->
    <script>
        window.currentUser = null;
        window.currentPageTab = 'auth';
    </script>
    <script src="/app.js"></script>

</body>
</html>
