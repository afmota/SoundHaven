<?php
// Arquivo: artistas.php
// Listagem dos Artistas (tabela 'artistas'). Com visualização em Modal.
// Baseado em colecao.php.

// Inclusões de arquivos de apoio
require_once "../db/conexao.php";
require_once "../funcoes.php";
require_once "../include/header.php"; // Incluído aqui para começar o HTML

// ----------------------------------------------------
// 1. CONFIGURAÇÃO DE PAGINAÇÃO E FILTRO DE STATUS
// ----------------------------------------------------

$limite_por_pagina = 25; 
$pagina_atual = isset($_GET['p']) ? (int)$_GET['p'] : 1; 
$offset = ($pagina_atual - 1) * $limite_por_pagina;

// view_status: 1 (Ativos - padrão), 0 (Lixeira/Excluídos)
$view_status = filter_input(INPUT_GET, 'view_status', FILTER_VALIDATE_INT) ?? 1;

// ----------------------------------------------------
// 2. LÓGICA DE FILTRAGEM
// ----------------------------------------------------
$filtro_titulo_extra = ""; 
// Removida a lógica de filtro de formato/outros filtros não aplicáveis a artistas.
// ----------------------------------------------------

// Definição da cláusula WHERE base (Ativo/Lixeira)
$where_ativo = "";
if ($view_status == 1) {
    $where_ativo = "a.ativo = 1";
} elseif ($view_status == 0) {
    $where_ativo = "a.ativo = 0";
}

// Variável para a barra de título
$page_title = ($view_status == 0) ? 'Lixeira de Artistas' : 'Artistas Cadastrados';
$page_title .= $filtro_titulo_extra; 


// ----------------------------------------------------
// 3. BUSCA DO TOTAL DE ITENS (Para a Paginação)
// ----------------------------------------------------

$total_itens = 0;
try {
    $sql_count_where = "WHERE $where_ativo";
    $sql_count = "
        SELECT COUNT(DISTINCT a.id)
        FROM artistas AS a
        INNER JOIN colecao_artista AS ca ON a.id = ca.artista_id 
        LEFT JOIN paises AS p ON a.pais_origem = p.id
        $sql_count_where
    ";
    $total_itens = $pdo->query($sql_count)->fetchColumn();
} catch (\PDOException $e) {
    // Tratamento de erro
}

$total_paginas = ceil($total_itens / $limite_por_pagina);


// ----------------------------------------------------
// 4. CARREGAR DADOS BÁSICOS DOS ARTISTAS COM PAGINAÇÃO
// ----------------------------------------------------

$artistas = [];

try {
    $sql_artistas = "
        SELECT 
            a.id, 
            a.nome, 
            a.imagem_url, 
            a.genero_principal,
            a.ativo, 
            p.nome AS nome_pais
        FROM artistas AS a
        LEFT JOIN paises AS p ON a.pais_origem = p.id
        INNER JOIN colecao_artista AS ca ON a.id = ca.artista_id
        WHERE $where_ativo
        GROUP BY a.id
    ";
    
    // Adiciona paginação e ordenação
    $sql_artistas .= " ORDER BY a.nome ASC
        LIMIT :limite OFFSET :offset"; 

    $stmt_artistas = $pdo->prepare($sql_artistas);
    $stmt_artistas->bindParam(':limite', $limite_por_pagina, PDO::PARAM_INT);
    $stmt_artistas->bindParam(':offset', $offset, PDO::PARAM_INT);
    
    $stmt_artistas->execute();
    $artistas = $stmt_artistas->fetchAll(PDO::FETCH_ASSOC);

} catch (\PDOException $e) {
    die("Erro ao carregar artistas: " . $e->getMessage());
}

/**
 * Função auxiliar para gerar o link da paginação, mantendo o view_status
 * @param int $pagina
 * @param int $view_status
 * @return string
 */
function getPaginationLink($pagina, $view_status) {
    $url = "artistas.php?p=$pagina";
    if ($view_status !== 1) { 
        $url .= "&view_status=$view_status";
    }
    return $url;
}
?>

<div class="container">
    <div class="main-layout">
        <div class="content-area">

            <div class="page-header-actions">
                <h1><?php echo $page_title; ?> (Total: <?php echo $total_itens; ?> artistas)</h1>
                <div class="view-switcher">
                    <a href="artistas.php?view_status=1" class="btn-action <?php echo ($view_status == 1) ? 'primary-action' : 'secondary-action'; ?>">
                        <i class="fas fa-user-check"></i> Artistas Ativos
                    </a>
                    <a href="artistas.php?view_status=0" class="btn-action btn-lixeira-toggle <?php echo ($view_status == 0) ? 'primary-action' : 'secondary-action'; ?>">
                        <i class="fas fa-trash"></i> Lixeira
                    </a>
                    <a href="adicionar_artista.php" class="btn-adicionar-album">
                        <i class="fas fa-plus"></i> Adicionar Artista
                    </a>
                </div>
            </div>
            
            <?php 
                // Exibição da mensagem de status (sucesso/erro)
                if (isset($_GET['status']) && !empty($_GET['msg'])) {
                    $status_class = ($_GET['status'] == 'sucesso') ? 'sucesso' : 'erro';
                    echo '<p class="' . $status_class . '" style="margin-bottom: 20px;">' . htmlspecialchars(urldecode($_GET['msg'])) . '</p>';
                }
            ?>
            
            <p style="margin-bottom: 20px; color: var(--cor-texto-secundario);">Clique na foto do artista para ver todos os detalhes e opções de edição.</p>
            
            <?php if ($total_paginas > 1): ?>
                <div class="pagination-controls" style="display: flex; justify-content: flex-end; align-items: center; gap: 10px; margin-bottom: 20px;">
                    <span style="color: var(--cor-texto-secundario);">Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?></span>
                    
                    <?php if ($pagina_atual > 1): ?>
                        <a href="<?php echo getPaginationLink($pagina_atual - 1, $view_status); ?>" class="btn-action secondary-action">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </a>
                    <?php else: ?>
                        <span class="btn-action secondary-action disabled" style="opacity: 0.5; cursor: not-allowed;">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </span>
                    <?php endif; ?>

                    <?php if ($pagina_atual < $total_paginas): ?>
                        <a href="<?php echo getPaginationLink($pagina_atual + 1, $view_status); ?>" class="btn-action secondary-action">
                            Próxima <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="btn-action secondary-action disabled" style="opacity: 0.5; cursor: not-allowed;">
                            Próxima <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="colecao-card-grid">
                <?php if (empty($artistas)): ?>
                    <div class="card" style="padding: 20px; text-align: center; grid-column: 1 / -1;">
                        <p style="margin: 0;">
                            <?php 
                                echo ($view_status == 0) 
                                    ? "A Lixeira de artistas está vazia." 
                                    : "Nenhum artista cadastrado na página atual."; 
                            ?>
                        </p>
                    </div>
                <?php else: ?>
                
                    <?php foreach ($artistas as $artista): ?>
                        
                        <div class="card colecao-item-card open-modal-artista" 
                            data-artista-id="<?php echo $artista['id']; ?>" 
                            data-ativo="<?php echo $artista['ativo']; ?>" 
                            style="cursor: pointer;">
                            
                            <div class="card-capa-wrapper">
                                <?php if (!empty($artista['imagem_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($artista['imagem_url']); ?>"
                                        alt="Foto de <?php echo htmlspecialchars($artista['nome']); ?>"
                                        class="colecao-capa-grande"
                                        loading="lazy">
                                <?php else: ?>
                                    <div class="colecao-capa-grande no-cover">S/ Foto</div>
                                <?php endif; ?>
                                
                                <?php if ($artista['ativo'] == 0): ?>
                                    <span class="trash-icon" style="position: absolute; top: 10px; right: 10px; color: #dc3545; font-size: 1.5em;"><i class="fas fa-trash-alt"></i></span>
                                <?php endif; ?>

                                <?php if (!empty($artista['genero_principal'])): ?>
                                    <span class="album-format-tag tag-generic">
                                        <?php echo htmlspecialchars($artista['genero_principal']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-details-main colecao-card-minimal-details">
                                <h3 class="card-titulo-minimal"><?php echo htmlspecialchars($artista["nome"]); ?></h3>
                                <p class="card-artista-minimal"><?php echo htmlspecialchars($artista['nome_pais'] ?? 'País não informado'); ?></p>
                                
                            </div>

                        </div>
                    <?php endforeach; ?>
                    
                <?php endif; ?>
            </div>
            
            <?php if ($total_paginas > 1): ?>
                <div class="pagination-controls" style="display: flex; justify-content: flex-end; align-items: center; gap: 10px; margin-top: 20px;">
                    <span style="color: var(--cor-texto-secundario);">Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?></span>
                    
                    <?php if ($pagina_atual > 1): ?>
                        <a href="<?php echo getPaginationLink($pagina_atual - 1, $view_status); ?>" class="btn-action secondary-action">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </a>
                    <?php else: ?>
                        <span class="btn-action secondary-action disabled" style="opacity: 0.5; cursor: not-allowed;">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </span>
                    <?php endif; ?>

                    <?php if ($pagina_atual < $total_paginas): ?>
                        <a href="<?php echo getPaginationLink($pagina_atual + 1, $view_status); ?>" class="btn-action secondary-action">
                            Próxima <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="btn-action secondary-action disabled" style="opacity: 0.5; cursor: not-allowed;">
                            Próxima <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="artistaModal" class="modal-padrao">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        
        <div id="modal-loader-artista" class="modal-loader" style="text-align: center; padding: 50px;">
            <i class="fas fa-spinner fa-spin" style="font-size: 3em; color: var(--cor-destaque);"></i>
            <p style="margin-top: 20px; color: var(--cor-texto-secundario);">Carregando detalhes do artista...</p>
        </div>

        <div id="modal-details-artista" style="display: none;">
            </div>

    </div>
</div>

<?php require_once "../include/footer.php"; // Fecha o HTML ?>