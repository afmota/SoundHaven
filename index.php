<?php
// Arquivo: index.php (Na Raiz do Projeto)

// 1. Inicialização do Ambiente
// Requer o arquivo que carrega as variáveis de ambiente (.env)
require_once 'src/config/config.php'; 
// Requer a classe de conexão, preparando o ambiente para o login.php
require_once 'src/Database.php'; 

// 2. Inicia a Sessão
session_start();

// 3. Lógica de Tratamento de Erro (Mantida por enquanto)
// A lógica aqui é simples e serve apenas para transferir o erro da sessão para a variável local
$erro = '';
if (isset($_SESSION['erro'])) {
    $erro = $_SESSION['erro'];
    unset($_SESSION['erro']); // Limpa a mensagem após exibir
}

// 4. Se o usuário JÁ estiver logado, redireciona.
// (Assume que 'user_id' ou 'logged_in' será definido após o sucesso do login)
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php'); // Ou para a página principal
    exit;
}

?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Login - Gerenciador de Coleção</title>
      <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
      <link rel="stylesheet" href="public/css/login.css">
      <link rel="stylesheet" href="public/css/media-query.css">
    </head>
    <body>
      <main>
          <section id="login">
             <div id="imagem"></div>
             <div id="formulario">
                <h1>Login</h1>
                <p>Acesse sua conta para gerenciar sua coleção.</p>
                <?php if (!empty($erro)): ?>
                <p style='color: #dc3545; text-align: center; border: 1px solid #dc3545; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>
                   <?php echo $erro; ?>
                </p>
                <?php endif; ?>
                <form action="src/auth/login.php" method="post" autocomplete="on">
                   <div class="campo">
                      <span class="material-symbols-outlined">person</span>
                      <input type="email" name="login" id="ilogin" placeholder="seu e-mail" autocomplete="email" required maxlength="50">
                      <label for="ilogin">E-mail</label>
                   </div>
                   <div class="campo">
                      <span class="material-symbols-outlined">vpn_key</span>
                      <input type="password" name="senha" id="isenha" placeholder="sua senha" autocomplete="current-password" required minlength="8" maxlength="20">
                      <label for="isenha">Senha</label>
                   </div>
                   <input type="submit" value="Entrar">
                   <a href="esqueci.html" class="botao">Esqueci a senha <span class="material-symbols-outlined">mail</span></a>
                </form>
             </div>
          </section>
      </main>
    </body>
</html>