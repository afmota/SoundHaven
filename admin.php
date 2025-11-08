<?php
require_once 'db/conexao.php';

// 1. Senha que você usará para o login
$senha_limpa = 'usU@r1O@dm1n'; 

// 2. Cria o hash seguro da senha
$senha_hash = password_hash($senha_limpa, PASSWORD_DEFAULT);

// 3. Dados do usuário
$nome = 'Administrador Geral';
$email = 'admin@colecao.com';
$tipo = 1; // Definindo como tipo 1 (Admin)

// 4. Query de inserção
$sql = "INSERT INTO usuarios (nome, email, senha, tipo, ativo) 
        VALUES (:nome, :email, :senha, :tipo, 1)";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':nome', $nome);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':senha', $senha_hash);
    $stmt->bindParam(':tipo', $tipo);
    $stmt->execute();
    
    echo "Usuário de teste cadastrado com sucesso! E-mail: $email | Senha (limpa): $senha_limpa";
    
} catch (PDOException $e) {
    // Isso ocorrerá se o e-mail já existir (UNIQUE constraint)
    echo "Erro ao cadastrar usuário: " . $e->getMessage();
}
?>