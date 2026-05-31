<?php
namespace Config;

/**
 * AppConfig — Configurações Gerais do Sistema GT Cursos
 * 
 * ATENÇÃO: As chaves secretas listadas aqui por padrão são mocks para desenvolvimento.
 * Em produção (HostGator), as credenciais devem ser injetadas por variáveis de ambiente
 * ou inseridas de forma segura neste arquivo protegido.
 */
class AppConfig {
    
    // Configurações do Banco de Dados MySQL
    public static $DB_HOST = 'localhost';
    public static $DB_NAME = 'gt_cursos';
    public static $DB_USER = 'root';
    public static $DB_PASS = '';
    public static $DB_CHARSET = 'utf8mb4';

    // Credenciais de API - Mercado Pago (Checkout Transparente)
    public static $MERCADO_PAGO_PUBLIC_KEY = 'TEST-a6ef53bf-mock-public-key-1234';
    public static $MERCADO_PAGO_ACCESS_TOKEN = 'TEST-5730248593457221-mock-access-token-9988';
    public static $MERCADO_PAGO_WEBHOOK_SECRET = ''; // Segredo do Webhook para validar assinatura HMAC-SHA256
    
    // Credenciais de API - Gmail (PHPMailer SMTP)
    public static $SMTP_HOST = 'smtp.gmail.com';
    public static $SMTP_PORT = 587;
    public static $SMTP_USER = 'suporte.cursosgt@gmail.com';
    public static $SMTP_PASS = 'mock_gmail_app_password_here'; // App Password segura do Gmail
    
    // Credenciais de API - Discloud (WhatsApp API)
    public static $WHATSAPP_API_URL = 'https://api.discloud.bot/v1/whatsapp/send';
    public static $WHATSAPP_API_TOKEN = 'mock-discloud-whatsapp-token-abcd-1234';

    // Configurações de Bunny.net Video Stream
    public static $BUNNY_STREAM_BASE_URL = 'https://iframe.mediadelivery.net/embed/';
    
    // Ambiente Geral
    public static $APP_URL = 'http://localhost/gt-cursos';
    public static $DEV_MODE = true; // Set to false in production to suppress detailed errors

    private static $envLoaded = false;

    /**
     * Inicializa a leitura dinâmica de arquivos .env para suporte a HostGator/Produção
     */
    public static function init() {
        if (self::$envLoaded) return;
        
        $envFile = __DIR__ . '/../../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) continue;
                
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $name = trim($parts[0]);
                    $value = trim($parts[1]);
                    $value = trim($value, "\"'");
                    
                    if (property_exists(self::class, $name)) {
                        if ($value === 'true') {
                            $value = true;
                        } elseif ($value === 'false') {
                            $value = false;
                        } elseif (is_numeric($value)) {
                            if (strpos($value, '.') !== false) {
                                $value = (float)$value;
                            } else {
                                $value = (int)$value;
                            }
                        }
                        self::$$name = $value;
                    }
                }
            }
        }
        self::$envLoaded = true;
    }


    /**
     * Retorna se a sessão já foi iniciada
     */
    public static function startSession() {
        if (session_status() == PHP_SESSION_NONE) {
            // Diretrizes de segurança robustas para cookies de sessão
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                ini_set('session.cookie_secure', 1);
            }
            
            session_start();
        }
    }
}
AppConfig::init();
