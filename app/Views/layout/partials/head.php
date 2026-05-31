<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title id="page-title">Marketing AI Agentic Automation</title>
    
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
        .tab-button { text-decoration: none; }
    </style>

    <!-- Load Active Plugins CSS -->
    <?php
    $plugins = \MarketingAgent\Plugin\PluginManager::getInstalledPlugins();
    foreach ($plugins as $plugin) {
        if ($plugin['active'] && $plugin['has_css']) {
            echo '    <link rel="stylesheet" href="/plugin-asset.php?plugin=' . htmlspecialchars($plugin['id']) . '&type=css&v=' . htmlspecialchars($plugin['version']) . '">' . "\n";
        }
    }
    ?>
    <style>
        .settings-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 20px;
            gap: 0;
        }

        .settings-tab {
            flex: 1;
            padding: 10px 8px;
            background: transparent;
            border: none;
            color: var(--text-secondary);
            font-family: var(--font-body);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border-bottom: 2px solid transparent;
        }

        .settings-tab.active {
            color: var(--accent-primary);
            border-bottom-color: var(--accent-primary);
        }

        .settings-panel { display: none; }
        .settings-panel.active { display: block; }
    </style>
</head>
