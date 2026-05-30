<?php
namespace Security;

use Config\Database;
use Config\AppConfig;

require_once __DIR__ . '/../Config/AppConfig.php';
require_once __DIR__ . '/../Config/Database.php';

/**
 * SessionManager
 * 
 * Classe responsável por assegurar a autenticidade das sessões dos usuários (Fingerprinting)
 * e implementar mitigação de ataques por brute-force com bloqueio temporário (Rate Limiting).
 */
class SessionManager {

    /**
     * Gera um hash identificador único baseado no navegador e subrede do IP
     */
    public static function generateFingerprint() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown_ua';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        // Extrai apenas os primeiros dois segmentos do IP (ex: 192.168.x.x)
        // Isso evita deslogar o aluno caso o celular dele alterne rapidamente de rede de dados
        $ipParts = explode('.', $ip);
        $ipSubnet = (count($ipParts) >= 2) ? $ipParts[0] . '.' . $ipParts[1] : $ip;
        
        return hash('sha256', $userAgent . $ipSubnet);
    }

    /**
     * Vincula o fingerprint gerado na sessão ativa pós login
     */
    public static function bindSession() {
        AppConfig::startSession();
        $_SESSION['fingerprint'] = self::generateFingerprint();
    }

    /**
     * Valida se a sessão atual é autêntica.
     * Caso o fingerprint seja incompatível, destrói e desconecta.
     */
    public static function validateSession() {
        AppConfig::startSession();
        
        // Se houver ID logado mas não houver fingerprint, força logout
        if (isset($_SESSION['user_id'])) {
            if (!isset($_SESSION['fingerprint']) || $_SESSION['fingerprint'] !== self::generateFingerprint()) {
                self::forceDestroy();
                return false;
            }
        }
        return true;
    }

    /**
     * Incrementa tentativas de login mal sucedidas para o IP
     */
    public static function registerLoginFailure($email) {
        $db = Database::getInstance()->getConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        try {
            // Registra no banco na tabela audit_logs
            $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (NULL, 'login_falha', :email, :ip)");
            $stmt->execute([
                ':email' => "E-mail: " . $email,
                ':ip' => $ip
            ]);
        } catch (\PDOException $e) {
            // Silencia para não estourar erro na API
        }
    }

    /**
     * Verifica se o IP atual está temporariamente bloqueado.
     * Bloqueia se houver mais de 5 tentativas falhas nos últimos 15 minutos.
     */
    public static function isIpBlocked() {
        $db = Database::getInstance()->getConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) as failures 
                FROM audit_logs 
                WHERE action = 'login_falha' 
                  AND ip_address = :ip 
                  AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ");
            $stmt->execute([':ip' => $ip]);
            $res = $stmt->fetch();
            
            return ($res && $res['failures'] >= 5);
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Força a destruição total da sessão
     */
    public static function forceDestroy() {
        AppConfig::startSession();
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}
