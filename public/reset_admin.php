<?php
use Config\Database;

require_once __DIR__ . '/../src/Config/Database.php';

try {
    $dbInstance = Database::getInstance();
    $db = $dbInstance->getConnection();

    $email = 'admin@cursosgt.com.br';
    $password = 'admin123';
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $name = 'Diretoria GT';

    // 1. Garante que a tabela de usuários exista e tenha o administrador correto
    // Verifica se já existe o administrador
    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user) {
        // Atualiza a senha e garante a role admin
        $update = $db->prepare("UPDATE users SET password_hash = :hash, role = 'admin', name = :name WHERE email = :email");
        $update->execute([
            ':hash' => $hash,
            ':name' => $name,
            ':email' => $email
        ]);
        echo "<div style='font-family: sans-serif; padding: 20px; background-color: #0E0E12; color: #F5F5F7; border: 1px solid rgba(242,201,76,0.2); border-radius: 8px; max-w: 600px; margin: 40px auto;'>";
        echo "<h2 style='color: #00E676;'>✓ Admin Sincronizado com Sucesso!</h2>";
        echo "<p>A senha da sua conta de administrador (<b>admin@cursosgt.com.br</b>) foi redefinida com criptografia compatível para: <b style='color: #F2C94C;'>admin123</b></p>";
        echo "<p><a href='login.php' style='color: #F2C94C; text-decoration: none; font-weight: bold; border-bottom: 1px solid #F2C94C;'>Clique aqui para ir para a tela de login</a></p>";
        echo "</div>";
    } else {
        // Insere o novo admin do zero
        $insert = $db->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :hash, 'admin')");
        $insert->execute([
            ':name' => $name,
            ':email' => $email,
            ':hash' => $hash
        ]);
        echo "<div style='font-family: sans-serif; padding: 20px; background-color: #0E0E12; color: #F5F5F7; border: 1px solid rgba(242,201,76,0.2); border-radius: 8px; max-w: 600px; margin: 40px auto;'>";
        echo "<h2 style='color: #00E676;'>✓ Administrador Criado com Sucesso!</h2>";
        echo "<p>A conta de administrador (<b>admin@cursosgt.com.br</b>) foi criada com a senha: <b style='color: #F2C94C;'>admin123</b></p>";
        echo "<p><a href='login.php' style='color: #F2C94C; text-decoration: none; font-weight: bold; border-bottom: 1px solid #F2C94C;'>Clique aqui para ir para a tela de login</a></p>";
        echo "</div>";
    }

} catch (\Exception $e) {
    echo "<div style='font-family: sans-serif; padding: 20px; background-color: #0E0E12; color: #F5F5F7; border: 1px solid #FF1744; border-radius: 8px; max-w: 600px; margin: 40px auto;'>";
    echo "<h2 style='color: #FF1744;'>✗ Erro ao redefinir administrador</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
