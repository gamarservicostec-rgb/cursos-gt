<?php
namespace Config;

use PDO;
use PDOException;

require_once __DIR__ . '/AppConfig.php';

/**
 * Classe Database
 * 
 * Gerenciador Singleton de conexões PDO com o MySQL.
 * Evita a abertura de múltiplas conexões síncronas na mesma requisição.
 */
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        $dsn = "mysql:host=" . AppConfig::$DB_HOST . ";dbname=" . AppConfig::$DB_NAME . ";charset=" . AppConfig::$DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // Desabilita emulação para evitar SQL injection secundário
        ];

        try {
            $this->connection = new PDO($dsn, AppConfig::$DB_USER, AppConfig::$DB_PASS, $options);
        } catch (PDOException $e) {
            // Em modo desenvolvimento exibe o erro, em produção oculta
            if (AppConfig::$DEV_MODE) {
                die("Erro na conexão com o Banco de Dados: " . $e->getMessage());
            } else {
                die("Erro interno. Não foi possível conectar ao servidor de dados.");
            }
        }
    }

    /**
     * Retorna a instância única da classe Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retorna a conexão PDO ativa
     */
    public function getConnection() {
        return $this->connection;
    }
}
