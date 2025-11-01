<?php
   // Arquivo: index.php (Página de Login)
   session_start();
   
   // Verifica se há uma mensagem de erro na sessão para exibir
   $erro = '';
   if (isset($_SESSION['erro'])) {
   $erro = $_SESSION['erro'];
   unset($_SESSION['erro']); // Limpa a mensagem após exibir
   }
   ?>
<!DOCTYPE html>
<html lang="pt-br">
   <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Login - Gerenciador de Coleção</title>
      <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
      <link rel="stylesheet" href="css/login.css">
      <link rel="stylesheet" href="css/media-query.css">
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
               <form action="login.php" method="post" autocomplete="on">
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