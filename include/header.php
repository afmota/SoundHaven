<?php 
// Arquivo: header.php
// A linha 'session_start()' DEVE vir antes de qualquer saída HTML!
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Variáveis de sessão para facilitar o uso no HTML
$logado = isset($_SESSION['usuario_id']);
$usuario_nome = $logado ? $_SESSION['usuario_nome'] : 'Visitante';
$isAdmin = $logado && ($_SESSION['usuario_tipo'] == 1);

// Redirecionamento de segurança: Mantenha esta checagem no topo
// de CADA arquivo que requer login (store.php, dashboard.php, etc.)
/*
$pagina_atual = basename($_SERVER['PHP_SELF']);
if (!$logado && $pagina_atual != 'index.php' && $pagina_atual != 'login.php') {
    header('Location: index.php');
    exit();
}
// OBS: Removi o bloco acima, pois é melhor você ter a checagem no topo de CADA arquivo.
*/
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SoundHaven | Catálogo</title> 
    <link rel="icon" href="../imagens/SoundHaven.ico" type="image/x-icon">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../css/estilos.css">
    <link rel="stylesheet" href="../css/colecao.css">
    <link rel="stylesheet" href="../css/loja.css">
</head>
<body>

<header>
    <div class="nav-content">
        <a href="/dashboard.php" class="header-logo-container">
            <img src="../imagens/SoundHaven.png" alt="Logo SoundHaven" class="header-logo-img">
            
            <div class="header-logo-text">
                <span class="logo-main-title">SoundHaven</span>
                <span class="logo-subtitle">Acervo Digital</span>
            </div>
        </a>
        
        <div class="header-right-menu">
            <?php if ($logado): ?>
                <a href="colecao/adicionar_colecao.php" class="btn-adicionar-album">
                    <i class="fas fa-plus-circle"></i> Adicionar Álbum
                </a>

                <div class="profile-dropdown-container" id="profileDropdown">
                                        <div class="profile-avatar-trigger" title="<?php echo htmlspecialchars($usuario_nome); ?>"> 
                        <img src="../imagens/default-avatar.png" alt="Perfil do Usuário" class="profile-avatar">
                                        </div>

                    <nav class="dropdown-menu">
                        <ul>
                            <li><a href="/dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                            <li><a href="/colecao/colecao.php"><i class="fas fa-list-alt"></i> Minha Coleção</a></li>
                            <li><a href="/estatisticas.php"><i class="fas fa-chart-line"></i> Estatísticas</a></li>
                            <li><a href="/loja/store.php"><i class="fas fa-store"></i> Catálogo</a></li>
                            <?php if ($isAdmin): ?>
                                <li><a href="/usuarios.php"><i class="fas fa-users-cog"></i> Gerenciar Usuários</a></li>
                            <?php endif; ?>
                            <li class="separator"></li>
                            <li><a href="/perfil.php"><i class="fas fa-user-circle"></i> Meu Perfil</a></li>
                            <li><a href="/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
                        </ul>
                    </nav>
                </div>
            <?php else: ?>
                <a href="index.php" class="btn-adicionar-album">
                    <i class="fas fa-sign-in-alt"></i> Entrar
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const dropdownContainer = document.getElementById('profileDropdown');

        // Garante que só tente acessar elementos se o dropdown estiver no DOM (usuário logado)
        if (!dropdownContainer) return;

        const avatarTrigger = dropdownContainer.querySelector('.profile-avatar-trigger');

        // Função para alternar a visibilidade do menu
        avatarTrigger.addEventListener('click', function(event) {
            event.stopPropagation(); 
            dropdownContainer.classList.toggle('menu-aberto');
        });

        // Função para fechar o menu se o usuário clicar fora dele
        document.addEventListener('click', function(event) {
            // Se o clique não foi dentro do container do dropdown
            if (!dropdownContainer.contains(event.target)) {
                dropdownContainer.classList.remove('menu-aberto');
            }
        });
    });
</script>