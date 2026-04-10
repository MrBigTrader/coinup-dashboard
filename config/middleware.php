<?php
/**
 * Middleware de Proteção de Rotas
 */

class Middleware {
    
    /**
     * Requer autenticação
     */
    public static function requireAuth(): void {
        $auth = Auth::getInstance();
        
        if (!$auth->isLoggedIn()) {
            header('Location: /main/public/login.php');
            exit;
        }
    }
    
    /**
     * Requer que seja admin
     */
    public static function requireAdmin(): void {
        $auth = Auth::getInstance();
        
        if (!$auth->isAdmin()) {
            header('Location: /main/public/dashboard.php');
            exit;
        }
    }
    
    /**
     * Requer que seja cliente
     */
    public static function requireClient(): void {
        $auth = Auth::getInstance();
        
        if (!$auth->isClient()) {
            header('Location: /main/public/admin.php');
            exit;
        }
    }
    
    /**
     * Redirecionar se já estiver logado
     */
    public static function redirectIfLoggedIn(): void {
        $auth = Auth::getInstance();
        
        if ($auth->isLoggedIn()) {
            if ($auth->isAdmin()) {
                header('Location: /main/public/admin.php');
            } else {
                header('Location: /main/public/dashboard.php');
            }
            exit;
        }
    }
    
    /**
     * Proteger rota AJAX
     */
    public static function requireAjax(): void {
        if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso não permitido']);
            exit;
        }
    }
}
