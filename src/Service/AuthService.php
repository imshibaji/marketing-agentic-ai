<?php
namespace MarketingAgent\Service;

use Exception;

class AuthService {
    private DatabaseService $db;

    public function __construct(DatabaseService $db) {
        $this->db = $db;
    }

    public function register(string $username, string $password, string $role = 'user', ?string $fullName = null, ?string $email = null, ?string $mobile = null, ?string $whatsappNumber = null): array {
        $username = trim($username);
        if (strlen($username) < 3) {
            return ['success' => false, 'error' => 'Username must be at least 3 characters.'];
        }
        if (strlen($password) < 6) {
            return ['success' => false, 'error' => 'Password must be at least 6 characters.'];
        }

        $role = in_array(strtolower($role), ['admin', 'user']) ? strtolower($role) : 'user';
        
        $pdo = $this->db->getPdo();
        
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Username already exists.'];
        }

        // Hash password
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        try {
            $this->db->createUser($username, $passwordHash, $role, $fullName, $email, $mobile, $whatsappNumber, 10, 50);
            return ['success' => true, 'message' => 'Registration successful.'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function login(string $username, string $password): array {
        $username = trim($username);
        $pdo = $this->db->getPdo();

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Invalid username or password.'];
        }

        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        return [
            'success' => true,
            'message' => 'Login successful.',
            'user' => [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ]
        ];
    }

    public static function logout(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    public static function getCurrentUser(): ?array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user_id'])) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role' => $_SESSION['role']
            ];
        }
        return null;
    }
}
