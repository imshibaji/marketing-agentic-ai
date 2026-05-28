<?php
namespace MarketingAgent\Plugin;

/**
 * HookManager provides event hook (Action) and data transformation (Filter) dispatch systems.
 */
class HookManager {
    private static array $actions = [];
    private static array $filters = [];

    /**
     * Registers a callback for an action hook.
     */
    public static function addAction(string $hook, callable $callback, int $priority = 10): void {
        self::$actions[$hook][$priority][] = $callback;
    }

    /**
     * Registers a callback for a filter hook.
     */
    public static function addFilter(string $hook, callable $callback, int $priority = 10): void {
        self::$filters[$hook][$priority][] = $callback;
    }

    /**
     * Executes action hook callbacks.
     */
    public static function doAction(string $hook, ...$args): void {
        if (!isset(self::$actions[$hook])) {
            return;
        }

        $priorities = self::$actions[$hook];
        ksort($priorities);

        foreach ($priorities as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                try {
                    call_user_func_array($callback, $args);
                } catch (\Throwable $e) {
                    error_log("Error executing action hook '{$hook}' at priority {$priority}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Applies filter hook callbacks to transform a value.
     */
    public static function applyFilters(string $hook, $value, ...$args) {
        if (!isset(self::$filters[$hook])) {
            return $value;
        }

        $priorities = self::$filters[$hook];
        ksort($priorities);

        foreach ($priorities as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                try {
                    $value = call_user_func_array($callback, array_merge([$value], $args));
                } catch (\Throwable $e) {
                    error_log("Error executing filter hook '{$hook}' at priority {$priority}: " . $e->getMessage());
                }
            }
        }

        return $value;
    }

    /**
     * Check if action callbacks are registered.
     */
    public static function hasAction(string $hook): bool {
        return isset(self::$actions[$hook]) && !empty(self::$actions[$hook]);
    }

    /**
     * Check if filter callbacks are registered.
     */
    public static function hasFilter(string $hook): bool {
        return isset(self::$filters[$hook]) && !empty(self::$filters[$hook]);
    }
}
