<?php
// Arquivo: colecao.php
// Objetivo: Listagem da Coleção Pessoal com paginação e filtros.

// 1. Conexões e Dependências
require_once __DIR__ . "/../src/config/config.php"; // Única fonte de conexão agora
require_once __DIR__ . "/../funcoes.php";

// 2. Parâmetros de Filtro e Paginação
$limite_por_pagina = 25; 
$pagina_atual = filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT) ?: 1; 
$offset = ($pagina_atual - 1) * $limite_por_pagina;

// status: 1 (Ativos), 0 (Lixeira)
$view_status = filter_input(INPUT_GET, 'view_status', FILTER_VALIDATE_INT) ?? 1;
$filtro_formato = filter_input(INPUT_GET, 'formato', FILTER_SANITIZE_SPECIAL_CHARS);

// 3. Títulos Dinâmicos
$page_title = ($view_status == 0) ? 'Lixeira da Coleção' : 'Sua Coleção Pessoal';
if ($filtro_formato) {
    $page_title .= " (Formato: " . htmlspecialchars($filtro_formato) . ")";
}

// 4. Lógica de Busca no Banco de Dados
try {
    $where_base = ($view_status == 0) ? "c.ativo = 0" : "c.ativo = 1";
    $params = [];

    if ($filtro_formato) {
        $where_base .= " AND f.descricao = :formato";
        $params[':formato'] = $filtro_formato;
    }

    // --- Contagem Total (para a sua função de paginação) ---
    $sql_count = "SELECT COUNT(c.id) FROM colecao c LEFT JOIN formatos f ON c.formato_id = f.id WHERE $where_base";
    $stmt_count = $pdo->prepare($sql_count);
    foreach($params as $k => $v) $stmt_count->bindValue($k, $v);
    $stmt_count->execute();
    $total_itens = (int)$stmt_count->fetchColumn();
    $total_paginas = ceil($total_itens / $limite_por_pagina);

    // --- Busca dos Itens (Grid) ---
    $sql_colecao = "
        SELECT 
            c.id, c.titulo, c.capa_url, c.ativo, 
            YEAR(c.data_lancamento) AS ano_lancamento,
            f.descricao AS formato_descricao,
            (
                SELECT a.nome 
                FROM colecao_artista ca
                JOIN artistas a ON ca.artista_id = a.id
                WHERE ca.colecao_id = c.id
                ORDER BY a.nome ASC LIMIT 1
            ) AS artista_principal
        FROM colecao c
        LEFT JOIN formatos f ON c.formato_id = f.id
        WHERE $where_base
        ORDER BY c.data_aquisicao DESC, c.titulo ASC
        LIMIT :limite OFFSET :offset";

    $stmt = $pdo->prepare($sql_colecao);
    foreach($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limite', $limite_por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $colecao = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao carregar coleção: " . $e->getMessage());
}

// 5. Inclusão do Header
require_once "../include/header.php";
?>

<div class="container" style="padding-top: 20px;">
    <div class="main-layout">
        <main class="content-area">

            <div class="page-header-actions" style="margin-bottom: 10px;">
                <h1><i class="fas fa-compact-disc"></i> <?php echo $page_title; ?> <small style="font-size: 0.6em; opacity: 0.7;">(<?php echo $total_itens; ?> itens)</small></h1>

                <div class="view-switcher">
                    <a href="colecao.php?view_status=1" class="btn-action <?php echo ($view_status == 1) ? 'primary-action' : 'secondary-action'; ?>">
                        <i class="fas fa-check-circle"></i> Ativos
                    </a>
                    <a href="colecao.php?view_status=0" class="btn-action <?php echo ($view_status == 0) ? 'primary-action' : 'secondary-action'; ?>">
                        <i class="fas fa-trash-alt"></i> Lixeira
                    </a>
                </div>
            </div>

            <div class="filter-bar" style="margin-bottom: 30px; display: flex; gap: 10px; flex-wrap: wrap; border-bottom: 1px solid var(--cor-borda); padding-bottom: 15px;">
                <span style="align-self: center; font-weight: bold; margin-right: 10px;">Filtrar por:</span>

                <a href="colecao.php?view_status=<?php echo $view_status; ?>" 
                   class="btn-action <?php echo empty($filtro_formato) ? 'primary-action' : 'secondary-action'; ?>" style="font-size: 0.85rem;">
                   Todos
                </a>

                <?php 
                // Opções de formatos comuns
                $formatos_disponiveis = ['LP', 'CD', 'CD-R', 'Digital']; 
                foreach ($formatos_disponiveis as $f): 
                ?>
                    <a href="colecao.php?view_status=<?php echo $view_status; ?>&formato=<?php echo urlencode($f); ?>" 
                       class="btn-action <?php echo ($filtro_formato === $f) ? 'primary-action' : 'secondary-action'; ?>" style="font-size: 0.85rem;">
                       <?php echo $f; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="colecao-card-grid">
                <?php if (empty($colecao)): ?>
                    <div class="alerta info" style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                        <p>Nenhum item encontrado nesta categoria.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($colecao as $album): ?>
                        <div class="card colecao-item-card js-modal-colecao" data-album-id="<?php echo $album['id']; ?>">
                            
                            <div class="card-capa-wrapper">
                                <img src="<?php echo htmlspecialchars($album['capa_url'] ?: '../img/default-cover.png'); ?>" 
                                     alt="Capa" class="colecao-capa-grande" loading="lazy">
                                
                                <?php if (!empty($album['formato_descricao'])): ?>
                                    <span class="album-format-tag <?php 
                                        $formato = strtolower($album['formato_descricao'] ?? '');

                                        if (str_contains($formato, 'lp') || str_contains($formato, 'vinyl')) {
                                            echo 'tag-vinyl'; 
                                        } elseif (str_contains($formato, 'cd-r')) {
                                            echo 'tag-cdr'; 
                                        } elseif (str_contains($formato, 'digital') || str_contains($formato, 'dig')) {
                                            echo 'tag-dig'; // Preparado para o futuro
                                        } else {
                                            echo 'tag-cd'; 
                                        }
                                    ?>">
                                        <?php echo htmlspecialchars($album['formato_descricao'] ?? '-'); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-details-main">
                                <h3 class="card-titulo-minimal" title="<?php echo htmlspecialchars($album['titulo']); ?>">
                                    <?php echo htmlspecialchars(limitar_texto($album['titulo'], 35)); ?>
                                </h3>
                                <p class="card-artista-minimal">
                                    <?php echo htmlspecialchars($album['artista_principal'] ?? 'Vários Artistas'); ?>
                                </p>
                                <p class="card-ano-minimal"><?php echo $album['ano_lancamento'] ?: '----'; ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php 
                $link_base = "colecao.php?view_status=$view_status";
                if ($filtro_formato) $link_base .= "&formato=" . urlencode($filtro_formato);
                renderizar_paginacao($pagina_atual, $total_paginas, $link_base); 
            ?>

        </main>
    </div>
</div>

<div id="albumModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <span class="modal-close">&times;</span>
        <div id="modal-loader" style="text-align: center; padding: 50px;">
            <i class="fas fa-circle-notch fa-spin" style="font-size: 3em; color: var(--cor-destaque);"></i>
        </div>
        <div id="modal-details-target"></div>
    </div>
</div>

<script src="../js/colecao_modal.js"></script>
<?php require_once "../include/footer.php"; ?>