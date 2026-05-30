<?php
namespace Middleware;

use Config\AppConfig;

require_once __DIR__ . '/../Config/AppConfig.php';

/**
 * AuthMiddleware
 * 
 * Intercepta e valida sessões ativas e níveis de privilégios de usuários.
 */
class AuthMiddleware {

    /**
     * Valida se existe um aluno logado na sessão ativa.
     * Caso contrário, redireciona para a página de login.
     */
    public static function requireStudent() {
        AppConfig::startSession();

        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
            // Se for requisição AJAX/API, responde em JSON
            if (self::isAjaxRequest()) {
                http_response_code(419);
                echo json_encode(['error' => 'Sessão expirada ou usuário não autenticado.']);
                exit;
            }
            // Senão, redireciona para a página de login raiz
            header("Location: " . AppConfig::$APP_URL . "/login.php");
            exit;
        }
    }

    /**
     * Valida se existe um administrador logado na sessão ativa.
     * Caso contrário, bloqueia o acesso com HTTP 403 Forbidden ou redireciona.
     */
    public static function requireAdmin() {
        AppConfig::startSession();

        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            if (self::isAjaxRequest()) {
                http_response_code(403);
                echo json_encode(['error' => 'Acesso negado. Privilégios insuficientes.']);
                exit;
            }
            // Emite resposta amigável de erro
            http_response_code(403);
            die("<h1>403 Forbidden</h1><p>Acesso restrito apenas a administradores da plataforma GT Cursos.</p>");
            exit;
        }
    }

    /**
     * Helper para verificar se a requisição atual é assíncrona (AJAX/Fetch)
     */
    private static function isAjaxRequest() {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') 
            || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    }
}
