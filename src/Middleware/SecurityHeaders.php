<?php
namespace Middleware;

use Config\AppConfig;

require_once __DIR__ . '/../Config/AppConfig.php';

/**
 * SecurityHeaders
 * 
 * Classe responsável por gerenciar cabeçalhos de segurança HTTP 
 * e utilitários de validação anti-CSRF e sanitização de dados.
 */
class SecurityHeaders {

    /**
     * Aplica os cabeçalhos de segurança essenciais para todas as respostas HTTP
     */
    public static function applyHeaders() {
        // Impede que a página seja renderizada dentro de frame ou iframe externo (Anti-Clickjacking)
        header("X-Frame-Options: SAMEORIGIN");
        
        // Ativa proteção nativa contra XSS do navegador
        header("X-XSS-Protection: 1; mode=block");
        
        // Impede sniffing de tipo MIME (evita que o navegador execute arquivos não designados como script/style)
        header("X-Content-Type-Options: nosniff");
        
        // Protege contra vazamento de cabeçalho Referer
        header("Referrer-Policy: strict-origin-when-cross-origin");
    }

    /**
     * Gera um token anti-CSRF seguro e o armazena na sessão
     */
    public static function generateCSRFToken() {
        AppConfig::startSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Valida se o token CSRF fornecido coincide com o da sessão ativa
     */
    public static function validateCSRFToken($token) {
        AppConfig::startSession();
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Retorna a tag input hidden pronta para ser injetada em formulários
     */
    public static function getCSRFInputTag() {
        $token = self::generateCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    /**
     * Sanitiza dados de strings básicas
     */
    public static function sanitizeString($data) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitiza arrays de dados (como $_POST ou $_GET) recursivamente
     */
    public static function sanitizeArray(array $data) {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeArray($value);
            } else {
                $sanitized[$key] = self::sanitizeString($value);
            }
        }
        return $sanitized;
    }
}
