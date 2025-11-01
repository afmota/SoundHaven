<?php
// Arquivo: login.php (Processa o Login)

session_start();
// Alterado de 'includes/db.php' para 'conexao.php', que é o arquivo do nosso projeto
require_once 'db/conexao.php'; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Usando 'login' como nome do campo de email, como no seu formulário
    $email = filter_input(INPUT_POST, 'login', FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'];

    if (!empty($email) && !empty($senha)) {

        // Verifica se o usuário existe e está ativo
        $sql = "SELECT id, nome, senha, tipo FROM usuarios WHERE email = :email AND ativo = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            // Login bem-sucedido
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_tipo'] = $usuario['tipo'];

            // Redireciona para o dashboard, que está no mesmo nível
            header('Location: dashboard.php'); 
            exit();
        } else {
            // Credenciais inválidas (e-mail não encontrado ou senha incorreta)
            $_SESSION['erro'] = 'E-mail ou senha inválidos!';
            header('Location: index.php');
            exit();
        }
    } else {
        // Campos vazios
        $_SESSION['erro'] = 'Preencha todos os campos!';
        header('Location: index.php');
        exit();
    }
} else {
    // Tentativa de acesso direto a login.php
    $_SESSION['erro'] = 'Acesso negado. Por favor, utilize o formulário de login.';
    header('Location: index.php');
    exit();
}