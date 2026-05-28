<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use MarketingAgent\Service\DatabaseService;
use CodeIgniter\HTTP\ResponseInterface;

abstract class BaseApiController extends BaseController
{
    protected DatabaseService $db;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->db = new DatabaseService();
    }

    protected function getJsonInput(): array
    {
        $raw = $this->request->getBody();
        if (empty($raw)) {
            return [];
        }
        return json_decode($raw, true) ?? [];
    }

    protected function getCurrentUser(): ?array
    {
        return session()->get('user');
    }

    protected function respondSuccess(array $data = [], string $message = ''): ResponseInterface
    {
        $response = ['success' => true];
        if (!empty($message)) {
            $response['message'] = $message;
        }
        $response = array_merge($response, $data);
        return $this->response->setJSON($response);
    }

    protected function respondError(string $error, int $statusCode = 400): ResponseInterface
    {
        return $this->response
            ->setStatusCode($statusCode)
            ->setJSON(['success' => false, 'error' => $error]);
    }
}
