<?php
// Arquivo: login.php (Processa o Login - Controller)

// 1. Inicializa a Sessão
session_start();

// 2. Inclui o Model (que por sua vez já inclui o Database)
require_once 'src/Model/UserModel.php'; 

// 3. Verifica se o método de requisição é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Tentativa de acesso direto (melhor que usar a SESSION para erro)
    http_response_code(405); // Método não permitido
    header('Location: index.php');
    exit();
}

// 4. Sanitização e Validação dos Dados de Entrada
$email = filter_input(INPUT_POST, 'login', FILTER_SANITIZE_EMAIL);
// Senha não precisa de sanitização, pois será verificada pelo password_verify
$senha = $_POST['senha'] ?? ''; 

// 5. Instancia o Model e faz a validação
if (!empty($email) && !empty($senha)) {
    
    $userModel = new UserModel();
    $usuario = $userModel->getUserByEmail($email); // Busca o usuário

    // Verifica se o usuário existe E se a senha confere
    if ($usuario && password_verify($senha, $usuario['senha'])) {
        
        // Login bem-sucedido: Cria as variáveis de Sessão
        $_SESSION['user_id'] = $usuario['id']; // Usamos 'user_id' para consistência
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_tipo'] = $usuario['tipo'];

        // Redireciona
        header('Location: dashboard.php'); 
        exit();
        
    } else {
        // Credenciais inválidas
        $_SESSION['erro'] = 'E-mail ou senha inválidos!';
    }
} else {
    // Campos vazios
    $_SESSION['erro'] = 'Preencha todos os campos!';
}

// Redirecionamento de Falha (ocorre se entrar no else de credenciais ou campos vazios)
header('Location: index.php');
exit();