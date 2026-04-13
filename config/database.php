<?php
/**
 * Configuração do Banco de Dados
 * 
 * Carrega variáveis de ambiente e estabelece conexão PDO
 */

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        $this->loadEnv();
        $this->connect();
    }
    
    private function loadEnv() {
        $envFile = dirname(__DIR__) . '/.env';
        if (!file_exists($envFile)) {
            throw new Exception('Arquivo .env não encontrado. Copie .env.example para .env e configure.');
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') === false) continue;

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, '"\'');

            // Compatível com CLI e Web
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    private function connect() {
        // Compatível com CLI: usar $_ENV como fallback
        $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
        $dbname = $_ENV['DB_NAME'] ?? getenv('DB_NAME');
        $user = $_ENV['DB_USER'] ?? getenv('DB_USER');
        $pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS');
        
        if (!$dbname || !$user) {
            throw new Exception('Variáveis de banco de dados não configuradas no .env');
        }
        
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        
        try {
            $this->connection = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log("Erro de conexão com banco de dados: " . $e->getMessage());
            throw new Exception('Erro de conexão com banco de dados');
        }
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection(): PDO {
        return $this->connection;
    }
    
    // Previne clone
    private function __clone() {}
    
    // Previne unserialize
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
