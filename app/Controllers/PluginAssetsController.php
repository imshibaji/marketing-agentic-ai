<?php

namespace App\Controllers;

use MarketingAgent\Plugin\PluginManager;
use CodeIgniter\HTTP\ResponseInterface;
use Exception;

class PluginAssetsController extends BaseController
{
    public function serve(): ResponseInterface
    {
        try {
            $pluginId = $this->request->getGet('plugin');
            $type = $this->request->getGet('type');

            if (empty($pluginId) || empty($type)) {
                // Also support route params if routed via /plugins/(:any)/(:any)
                // e.g. plugins/slack-notifier/slack-notifier.js
                // We will handle this by routing or path parsing
            }

            // Sanitize plugin ID
            $pluginId = preg_replace('/[^a-zA-Z0-9_-]/', '', $pluginId ?? '');
            $type = preg_replace('/[^a-z]/', '', $type ?? '');

            if (empty($pluginId) || !in_array($type, ['js', 'css'])) {
                return $this->response->setStatusCode(400)->setBody("Bad Request: Invalid plugin ID or asset type.");
            }

            // Check if plugin is active
            $activePlugins = PluginManager::getActivePlugins();
            if (!in_array($pluginId, $activePlugins)) {
                return $this->response->setStatusCode(403)->setBody("Forbidden: Plugin is not active.");
            }

            // Resolve file path
            $filePath = PluginManager::getPluginAssetPath($pluginId, $type);
            if (!$filePath || !file_exists($filePath)) {
                return $this->response->setStatusCode(404)->setBody("Not Found: Plugin asset file does not exist.");
            }

            // Set content type and cache headers
            $contentType = ($type === 'js') ? 'application/javascript; charset=UTF-8' : 'text/css; charset=UTF-8';
            
            return $this->response
                ->setContentType($contentType)
                ->setHeader('Cache-Control', 'public, max-age=86400')
                ->setBody(file_get_contents($filePath));

        } catch (Exception $e) {
            return $this->response->setStatusCode(500)->setBody("Internal Server Error: " . $e->getMessage());
        }
    }

    /**
     * Supports direct routes like /plugins/slack-notifier/slack-notifier.js or css
     */
    public function serveDirect(string $pluginId, string $filename): ResponseInterface
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if ($ext === 'js') {
            $type = 'js';
        } elseif ($ext === 'css') {
            $type = 'css';
        } else {
            return $this->response->setStatusCode(400)->setBody("Bad Request: Unsupported asset type.");
        }

        // Sanitize plugin ID
        $pluginId = preg_replace('/[^a-zA-Z0-9_-]/', '', $pluginId);

        // Check if plugin is active
        $activePlugins = PluginManager::getActivePlugins();
        if (!in_array($pluginId, $activePlugins)) {
            return $this->response->setStatusCode(403)->setBody("Forbidden: Plugin is not active.");
        }

        $filePath = PluginManager::getPluginAssetPath($pluginId, $type);
        if (!$filePath || !file_exists($filePath)) {
            // Try direct resolution inside the plugin dir if different filename was requested
            $dir = PluginManager::getPluginsDir() . '/' . $pluginId;
            $directPath = $dir . '/' . basename($filename);
            if (file_exists($directPath)) {
                $filePath = $directPath;
            } else {
                return $this->response->setStatusCode(404)->setBody("Not Found: Plugin asset file does not exist.");
            }
        }

        $contentType = ($type === 'js') ? 'application/javascript; charset=UTF-8' : 'text/css; charset=UTF-8';
        
        return $this->response
            ->setContentType($contentType)
            ->setHeader('Cache-Control', 'public, max-age=86400')
            ->setBody(file_get_contents($filePath));
    }
}
