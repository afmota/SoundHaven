<?php 
// Arquivo: include/header.php
// A linha 'session_start()' DEVE vir antes de qualquer saída HTML!
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Variáveis de sessão para facilitar o uso no HTML
$logado = isset($_SESSION['user_id']); // AJUSTADO: Usando 'user_id' conforme o login.php refatorado
$usuario_nome = $logado ? $_SESSION['usuario_nome'] : 'Visitante';
$isAdmin = $logado && ($_SESSION['usuario_tipo'] == 1);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SoundHaven | Catálogo</title> 
    <link rel="icon" href="/public/imagens/SoundHaven.ico" type="image/x-icon">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="/public/css/styles.css">
</head>
<body>

<header>
    <div class="nav-content">
        <a href="/dashboard.php" class="header-logo-container">
            <img src="/public/imagens/SoundHaven.png" alt="Logo SoundHaven" class="header-logo-img">
            
            <div class="header-logo-text">
                <span class="logo-main-title">SoundHaven</span>
                <span class="logo-subtitle">Acervo Digital</span>
            </div>
        </a>
        
        <div class="header-right-menu">
            <?php if ($logado): ?>
                <a href="/colecao/adicionar_colecao.php" class="btn-adicionar-album">
                    <i class="fas fa-plus-circle"></i> Adicionar Álbum
                </a>

                <div class="profile-dropdown-container" id="profileDropdown">
                    <div class="profile-avatar-trigger" title="<?php echo htmlspecialchars($usuario_nome); ?>"> 
                        <img src="/public/imagens/default-avatar.png" alt="Perfil do Usuário" class="profile-avatar">
                    </div>

                    <nav class="dropdown-menu">
                        <ul>
                            <li><a href="/dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                            <li><a href="/colecao/colecao.php"><i class="fas fa-list-alt"></i> Minha Coleção</a></li>
                            <li><a href="/estatisticas.php"><i class="fas fa-chart-line"></i> Estatísticas</a></li>
                            <li><a href="/loja/store.php"><i class="fas fa-store"></i> Loja</a></li>
                            <?php if ($isAdmin): ?>
                                <li><a href="/usuarios.php"><i class="fas fa-users-cog"></i> Gerenciar Usuários</a></li>
                            <?php endif; ?>
                            <li class="separator"></li>
                            <li><a href="/perfil.php"><i class="fas fa-user-circle"></i> Meu Perfil</a></li>
                            <li><a href="/src/auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="/js/filtro.js"></script>
<script src="/js/scripts.js"></script>
<script src="/js/header_scripts.js"></script>