<!-- Global JS Hook Manager for Frontend Plugins -->
<script>
window.AppHooks = {
    actions: {},
    filters: {},
    addAction: function(hook, callback, priority = 10) {
        if (!this.actions[hook]) this.actions[hook] = [];
        this.actions[hook].push({ callback, priority });
        this.actions[hook].sort((a, b) => a.priority - b.priority);
    },
    addFilter: function(hook, callback, priority = 10) {
        if (!this.filters[hook]) this.filters[hook] = [];
        this.filters[hook].push({ callback, priority });
        this.filters[hook].sort((a, b) => a.priority - b.priority);
    },
    doAction: function(hook, ...args) {
        if (!this.actions[hook]) return;
        this.actions[hook].forEach(item => {
            try {
                item.callback(...args);
            } catch (e) {
                console.error("Error in action hook '" + hook + "':", e);
            }
        });
    },
    applyFilters: function(hook, value, ...args) {
        if (!this.filters[hook]) return value;
        let val = value;
        this.filters[hook].forEach(item => {
            try {
                val = item.callback(val, ...args);
            } catch (e) {
                console.error("Error in filter hook '" + hook + "':", e);
            }
        });
        return val;
    }
};
</script>

<!-- Pass Auth and Page Context to JavaScript -->
<script>
    window.currentUser = <?= json_encode(session()->get('user')) ?>;
    window.currentPageTab = <?= json_encode($activeTab) ?>;
</script>

<!-- Load Active Plugins JS -->
<?php
$plugins = \MarketingAgent\Plugin\PluginManager::getInstalledPlugins();
foreach ($plugins as $plugin) {
    if ($plugin['active'] && $plugin['has_js']) {
        echo '    <script src="/plugin-asset.php?plugin=' . htmlspecialchars($plugin['id']) . '&type=js&v=' . htmlspecialchars($plugin['version']) . '"></script>' . "\n";
    }
}
?>

<script src="/app.js"></script>
