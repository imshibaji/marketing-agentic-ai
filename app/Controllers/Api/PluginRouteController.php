<?php

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;
use MarketingAgent\Plugin\HookManager;
use Exception;

class PluginRouteController extends BaseApiController
{
    /**
     * Dispatch API requests to active plugins.
     * Routes requests dynamically through HookManager actions.
     */
    public function dispatch(): ResponseInterface
    {
        try {
            $plugin = $this->request->getGet('plugin') ?? '';
            $action = $this->request->getGet('action') ?? '';

            if (empty($plugin) || empty($action)) {
                return $this->respondError('Missing plugin or action parameter.', 400);
            }

            // Sanitize plugin ID and action name
            $safePlugin = preg_replace('/[^a-zA-Z0-9_-]/', '', $plugin);
            $safeAction = preg_replace('/[^a-zA-Z0-9_-]/', '', $action);
            $hookName = "api_route_{$safePlugin}_{$safeAction}";

            if (!HookManager::hasAction($hookName)) {
                return $this->respondError(
                    "Action '{$action}' for plugin '{$plugin}' is not registered or plugin is deactivated.",
                    404
                );
            }

            // Parse request input data (merge GET parameters and POST/JSON body)
            $inputData = $this->request->getGet();
            if ($this->request->getMethod() === 'post') {
                $jsonBody = $this->getJsonInput();
                if (!empty($jsonBody)) {
                    $inputData = array_merge($inputData, $jsonBody);
                } else {
                    $inputData = array_merge($inputData, $this->request->getPost());
                }
            }

            // Start buffering to capture any direct output/echos from the plugin
            ob_start();
            
            // Execute plugin action handler
            HookManager::doAction($hookName, $inputData);
            
            $output = ob_get_clean();

            // If the plugin already echoed a response and exited, CodeIgniter won't reach here.
            // Otherwise, return any captured output or a successful json response.
            if (!empty($output)) {
                // If it looks like JSON, set the content type
                if (json_decode($output) !== null) {
                    return $this->response->setContentType('application/json')->setBody($output);
                }
                return $this->response->setBody($output);
            }

            return $this->respondSuccess([], 'Plugin action executed.');

        } catch (Exception $e) {
            return $this->respondError($e->getMessage(), 500);
        }
    }
}
