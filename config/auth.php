<?php
/**
 * Configuração de Autenticação e Sessão
 */

class Auth {
    private static $instance = null;
    
    private function __construct() {
        $this->initSession();
    }
    
    private function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            $secret = getenv('SESSION_SECRET') ?: 'default_secret_change_me';
            $appEnv = getenv('APP_ENV') ?: 'production';

            // Configurações mais flexíveis para desenvolvimento
            if ($appEnv === 'development') {
                ini_set('session.cookie_httponly', 1);
                ini_set('session.use_only_cookies', 1);
                ini_set('session.cookie_secure', 0); // Permite HTTP em desenvolvimento
                ini_set('session.cookie_samesite', 'Lax');
            } else {
                ini_set('session.cookie_httponly', 1);
                ini_set('session.use_only_cookies', 1);
                ini_set('session.cookie_secure', 1); // Apenas HTTPS em produção
                ini_set('session.cookie_samesite', 'Lax'); // Lax permite redirecionamentos internos
            }

            session_name('COINUPSESS');
            session_start();
        }
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Autenticar usuário
     */
    public function login(string $email, string $password): array {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT id, name, email, password_hash, role, status FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'E-mail ou senha inválidos'];
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'E-mail ou senha inválidos'];
        }
        
        // Regenerar ID da sessão para segurança
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        
        return ['success' => true, 'user' => $user];
    }
    
    /**
     * Logout
     */
    public function logout(): void {
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        session_destroy();
    }
    
    /**
     * Verificar se está logado
     */
    public function isLoggedIn(): bool {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Verificar se é admin
     */
    public function isAdmin(): bool {
        return $this->isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
    
    /**
     * Verificar se é cliente
     */
    public function isClient(): bool {
        return $this->isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'client';
    }
    
    /**
     * Obter usuário atual
     */
    public function getCurrentUser(): ?array {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['user_role']
        ];
    }
    
    /**
     * Obter ID do usuário atual
     */
    public function getCurrentUserId(): ?int {
        return $_SESSION['user_id'] ?? null;
    }
    
    // Previne clone
    private function __clone() {}
    
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
