<?php
namespace MarketingAgent\Plugin;

use MarketingAgent\Service\DatabaseService;
use Exception;

/**
 * PluginManager scans the plugins directory and loads activated plugins.
 */
class PluginManager {
    private static ?DatabaseService $db = null;
    private static array $loadedPlugins = [];
    private static string $pluginsDir;

    /**
     * Set up PluginManager, ensure directory exists, and load active plugins.
     */
    public static function initialize(DatabaseService $db): void {
        self::$db = $db;
        self::$pluginsDir = dirname(__DIR__, 2) . '/plugins';

        if (!is_dir(self::$pluginsDir)) {
            mkdir(self::$pluginsDir, 0777, true);
        }

        self::loadActivePlugins();
    }

    /**
     * Retrieve current plugin directory path.
     */
    public static function getPluginsDir(): string {
        return self::$pluginsDir;
    }

    /**
     * Retrieve active database service instance.
     */
    public static function getDatabaseService(): ?DatabaseService {
        return self::$db;
    }

    /**
     * Fetches active plugin folders from database settings.
     */
    public static function getActivePlugins(): array {
        if (!self::$db) {
            return [];
        }
        
        try {
            $settings = self::$db->getSettings();
            $activeJson = $settings['active_plugins'] ?? '[]';
            $active = json_decode($activeJson, true);
            return is_array($active) ? $active : [];
        } catch (\Throwable $e) {
            // DB schema might not be initialized yet, fallback safely
            return [];
        }
    }

    /**
     * Saves active plugin folders list to settings.
     */
    public static function saveActivePlugins(array $active): void {
        if (!self::$db) {
            return;
        }
        self::$db->saveSettings(['active_plugins' => json_encode(array_values(array_unique($active)))]);
    }

    /**
     * Dispatches requirements/requires for all enabled plugins.
     */
    private static function loadActivePlugins(): void {
        $active = self::getActivePlugins();
        foreach ($active as $pluginDirName) {
            // Sanitize folder name
            $pluginDirName = preg_replace('/[^a-zA-Z0-9_-]/', '', $pluginDirName);
            $pluginFile = self::$pluginsDir . '/' . $pluginDirName . '/' . $pluginDirName . '.php';
            
            if (file_exists($pluginFile)) {
                try {
                    require_once $pluginFile;
                    self::$loadedPlugins[$pluginDirName] = true;
                } catch (\Throwable $e) {
                    error_log("Failed to load plugin '{$pluginDirName}': " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Scans plugins folder and returns all plugins metadata.
     */
    public static function getInstalledPlugins(): array {
        $plugins = [];
        if (!is_dir(self::$pluginsDir)) {
            return [];
        }

        $active = self::getActivePlugins();
        $dirs = array_filter(glob(self::$pluginsDir . '/*'), 'is_dir');

        foreach ($dirs as $dir) {
            $folderName = basename($dir);
            $jsonFile = $dir . '/plugin.json';
            $mainPhp = $dir . '/' . $folderName . '.php';

            if (file_exists($mainPhp)) {
                $pluginData = [
                    'id' => $folderName,
                    'name' => ucwords(str_replace('-', ' ', $folderName)),
                    'version' => '1.0.0',
                    'description' => 'No description provided.',
                    'author' => 'Unknown',
                    'active' => in_array($folderName, $active),
                    'has_js' => file_exists($dir . '/' . $folderName . '.js'),
                    'has_css' => file_exists($dir . '/' . $folderName . '.css'),
                ];

                if (file_exists($jsonFile)) {
                    $jsonRaw = file_get_contents($jsonFile);
                    $jsonData = json_decode($jsonRaw, true);
                    if (is_array($jsonData)) {
                        $pluginData['name'] = $jsonData['name'] ?? $pluginData['name'];
                        $pluginData['version'] = $jsonData['version'] ?? $pluginData['version'];
                        $pluginData['description'] = $jsonData['description'] ?? $pluginData['description'];
                        $pluginData['author'] = $jsonData['author'] ?? $pluginData['author'];
                    }
                }

                $plugins[] = $pluginData;
            }
        }

        return $plugins;
    }

    /**
     * Activates a plugin.
     */
    public static function activatePlugin(string $id): bool {
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
        $plugins = self::getInstalledPlugins();
        $exists = false;
        foreach ($plugins as $p) {
            if ($p['id'] === $id) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            return false;
        }

        $active = self::getActivePlugins();
        if (!in_array($id, $active)) {
            $active[] = $id;
            self::saveActivePlugins($active);
        }
        return true;
    }

    /**
     * Deactivates a plugin.
     */
    public static function deactivatePlugin(string $id): bool {
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
        $active = self::getActivePlugins();
        if (($key = array_search($id, $active)) !== false) {
            unset($active[$key]);
            self::saveActivePlugins($active);
            return true;
        }
        return false;
    }
}
