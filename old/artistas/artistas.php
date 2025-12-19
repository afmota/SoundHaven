<?php
// Arquivo: artistas.php
require_once __DIR__ . "/../src/config/config.php";
require_once __DIR__ . "/../funcoes.php";

// 1. Parâmetros de Filtro e Paginação
$limite_por_pagina = 25; 
$pagina_atual = filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT) ?: 1; 
$offset = ($pagina_atual - 1) * $limite_por_pagina;
$view_status = filter_input(INPUT_GET, 'view_status', FILTER_VALIDATE_INT) ?? 1;

try {
    $where = ($view_status == 0) ? "a.ativo = 0" : "a.ativo = 1";

    // 1. Contagem Total (Apenas artistas com álbuns)
    $sql_count = "
        SELECT COUNT(DISTINCT a.id) 
        FROM artistas a 
        INNER JOIN colecao_artista ca ON a.id = ca.artista_id 
        WHERE $where";
    $total_itens = (int)$pdo->query($sql_count)->fetchColumn();
    
    $total_paginas = ceil($total_itens / $limite_por_pagina);

    // 2. Busca dos Artistas (Filtro: deve existir em colecao_artista)
    $sql = "
        SELECT DISTINCT
            a.id, a.nome, a.imagem_url, a.ativo,
            p.nome AS pais_nome,
            g.descricao AS genero_nome
        FROM artistas a
        INNER JOIN colecao_artista ca ON a.id = ca.artista_id
        LEFT JOIN paises p ON a.pais_origem = p.id
        LEFT JOIN generos g ON a.genero_principal = g.id
        WHERE $where
        ORDER BY a.nome ASC
        LIMIT :limite OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limite', $limite_por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $artistas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao carregar artistas: " . $e->getMessage());
}

require_once "../include/header.php";
?>

<div class="container" style="padding-top: 20px;">
    <main class="content-area">
        
        <div class="page-header-actions" style="margin-bottom: 30px;">
            <h1><i class="fas fa-users"></i> Artistas <small style="font-size: 0.6em; opacity: 0.7;">(<?php echo $total_itens; ?>)</small></h1>
            
            <div class="view-switcher">
                <a href="artistas.php?view_status=1" class="btn-action <?php echo ($view_status == 1) ? 'primary-action' : 'secondary-action'; ?>">Ativos</a>
                <a href="artistas.php?view_status=0" class="btn-action <?php echo ($view_status == 0) ? 'primary-action' : 'secondary-action'; ?>">Lixeira</a>
            </div>
        </div>

        <div class="colecao-card-grid">
            <?php foreach ($artistas as $artista): ?>
                <div class="card colecao-item-card js-modal-artista" data-artista-id="<?php echo $artista['id']; ?>">
                    
                    <div class="card-capa-wrapper">
                        <img src="<?php echo htmlspecialchars($artista['imagem_url'] ?: '../img/default-artist.png'); ?>" 
                             class="colecao-capa-grande" loading="lazy">
                    </div>
                    
                    <div class="card-details-main">
                        <h3 class="card-titulo-minimal"><?php echo htmlspecialchars($artista['nome']); ?></h3>
                        <p class="card-artista-minimal">
                            <i class="fas fa-music" style="font-size: 0.8em;"></i> 
                            <?php echo htmlspecialchars($artista['genero_nome'] ?? 'Gênero N/D'); ?>
                        </p>
                        <p class="card-ano-minimal">
                            <i class="fas fa-globe-americas" style="font-size: 0.8em;"></i> 
                            <?php echo htmlspecialchars($artista['pais_nome'] ?? 'País N/D'); ?>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php renderizar_paginacao($pagina_atual, $total_paginas, "artistas.php?view_status=$view_status"); ?>

    </main>
</div>

<div id="artistaModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <span class="modal-close">&times;</span>
        <div id="artista-modal-loader" style="text-align: center; padding: 50px; display:none;">
            <i class="fas fa-circle-notch fa-spin fa-3x"></i>
        </div>
        <div id="artista-modal-target"></div>
    </div>
</div>

<script src="../js/artista_modal.js"></script>
<?php require_once "../include/footer.php"; ?>