<?php
use Config\AppConfig;
require_once __DIR__ . '/../src/Config/AppConfig.php';

// Inicia sessão
AppConfig::startSession();

// Destrói todas as variáveis de sessão
$_SESSION = array();

// Deleta o cookie de sessão do navegador por segurança
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destrói a sessão
session_destroy();

// Redireciona para a landing page
header("Location: index.php");
exit;
?>
