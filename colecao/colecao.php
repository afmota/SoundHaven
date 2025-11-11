<?php
// Arquivo: colecao.php
// Listagem dos itens da Coleção Pessoal (tabela 'colecao'). Com visualização em Modal.
// NOVIDADE: Suporte à Paginação, Lixeira (Soft Delete) e Navegação entre Itens Ativos/Excluídos.

require_once "../db/conexao.php";
require_once "../funcoes.php";

// ----------------------------------------------------
// 1. CONFIGURAÇÃO DE PAGINAÇÃO E FILTRO DE STATUS
// ----------------------------------------------------

$limite_por_pagina = 25; 
$pagina_atual = isset($_GET['p']) ? (int)$_GET['p'] : 1; 
$offset = ($pagina_atual - 1) * $limite_por_pagina;

// view_status: 1 (Ativos - padrão), 0 (Lixeira/Excluídos)
$view_status = filter_input(INPUT_GET, 'view_status', FILTER_VALIDATE_INT) ?? 1;

$where_ativo = "";
if ($view_status == 1) {
    $where_ativo = "WHERE c.ativo = 1";
} elseif ($view_status == 0) {
    $where_ativo = "WHERE c.ativo = 0";
}
// Variável para a barra de título
$page_title = ($view_status == 0) ? 'Lixeira da Coleção' : 'Sua Coleção Pessoal';


// ----------------------------------------------------
// 2. BUSCA DO TOTAL DE ITENS (Para a Paginação)
// ----------------------------------------------------

$total_itens = 0;
try {
    // Conta o total de itens (respeitando o filtro de ativo/lixeira)
    $sql_count = "SELECT COUNT(id) FROM colecao AS c $where_ativo";
    $total_itens = $pdo->query($sql_count)->fetchColumn();
} catch (\PDOException $e) {
    // Apenas ignora ou loga o erro de contagem, se necessário
}

$total_paginas = ceil($total_itens / $limite_por_pagina);


// ----------------------------------------------------
// 3. CARREGAR DADOS BÁSICOS DA COLEÇÃO COM PAGINAÇÃO
// ----------------------------------------------------

$colecao = [];

try {
    $sql_colecao = "
        SELECT 
            c.id, 
            c.titulo, 
            c.capa_url,
            c.ativo, 
            YEAR(c.data_lancamento) AS ano_lancamento,
            f.descricao AS formato_descricao,
            (
                SELECT a.nome 
                FROM colecao_artista AS ca
                JOIN artistas AS a ON ca.artista_id = a.id
                WHERE ca.colecao_id = c.id
                ORDER BY a.nome ASC 
                LIMIT 1
            ) AS artista_principal
        FROM colecao AS c
        LEFT JOIN formatos AS f ON c.formato_id = f.id
        $where_ativo 
        ORDER BY c.data_aquisicao DESC, c.titulo ASC
        LIMIT :limite OFFSET :offset"; // <-- CLÁUSULAS DE PAGINAÇÃO

    $stmt_colecao = $pdo->prepare($sql_colecao);
    $stmt_colecao->bindParam(':limite', $limite_por_pagina, PDO::PARAM_INT);
    $stmt_colecao->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt_colecao->execute();
    
    $colecao = $stmt_colecao->fetchAll(PDO::FETCH_ASSOC);

} catch (\PDOException $e) {
    die("Erro ao carregar coleção: " . $e->getMessage());
}

// ----------------------------------------------------
// 4. HTML DA PÁGINA
// ----------------------------------------------------
require_once "../include/header.php";

/**
 * Função auxiliar para gerar o link da paginação, mantendo o view_status
 * @param int $pagina
 * @param int $view_status
 * @return string
 */
function getPaginationLink($pagina, $view_status) {
    $url = "colecao.php?p=$pagina";
    if ($view_status !== 1) { // Só adiciona view_status se for diferente do padrão (1)
        $url .= "&view_status=$view_status";
    }
    return $url;
}

?>

<div class="container">
    <div class="main-layout">
        <div class="content-area">

<div class="page-header-actions">
    <h1><?php echo $page_title; ?> (Total: <?php echo $total_itens; ?> itens)</h1>
    <div class="view-switcher">
        <a href="colecao.php?view_status=1" class="btn-action <?php echo ($view_status == 1) ? 'primary-action' : 'secondary-action'; ?>">
            <i class="fas fa-box"></i> Itens Ativos
        </a>
        <a href="colecao.php?view_status=0" class="btn-action btn-lixeira-toggle <?php echo ($view_status == 0) ? 'primary-action' : 'secondary-action'; ?>">
            <i class="fas fa-trash"></i> Lixeira
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
            
            <p style="margin-bottom: 20px; color: var(--cor-texto-secundario);">Clique na capa do álbum para ver todos os detalhes e opções de edição.</p>
            
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
                <?php if (empty($colecao)): ?>
                    <div class="card" style="padding: 20px; text-align: center; grid-column: 1 / -1;">
                        <p style="margin: 0;">
                            <?php 
                                echo ($view_status == 0) 
                                    ? "A Lixeira está vazia. Nenhum item foi excluído logicamente." 
                                    : "Sua coleção está vazia ou não há itens na página atual. Adicione itens a partir do Catálogo!"; 
                            ?>
                        </p>
                    </div>
                <?php else: ?>
                
                    <?php foreach ($colecao as $album): ?>
                        
                        <div class="card colecao-item-card open-modal" 
                            data-album-id="<?php echo $album['id']; ?>" 
                            data-ativo="<?php echo $album['ativo']; ?>" 
                            style="cursor: pointer;">
                            
                            <div class="card-capa-wrapper">
                                <?php if (!empty($album['capa_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($album['capa_url']); ?>"
                                        alt="Capa de <?php echo htmlspecialchars($album['titulo']); ?>"
                                        class="colecao-capa-grande"
                                        loading="lazy">
                                <?php else: ?>
                                    <div class="colecao-capa-grande no-cover">S/ Capa</div>
                                <?php endif; ?>
                                
                                <?php if ($album['ativo'] == 0): ?>
                                    <span class="trash-icon" style="position: absolute; top: 10px; right: 10px; color: #dc3545; font-size: 1.5em;"><i class="fas fa-trash-alt"></i></span>
                                <?php endif; ?>

                                <?php if (!empty($album['formato_descricao'])): ?>
                                    <span class="album-format-tag <?php 
                                        $formato = strtolower($album['formato_descricao']);
                                        echo (str_contains($formato, 'lp') || str_contains($formato, 'vinyl')) 
                                            ? 'tag-vinyl' : 'tag-cd'; 
                                    ?>">
                                        <?php echo htmlspecialchars($album['formato_descricao']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-details-main colecao-card-minimal-details">
                                <h3 class="card-titulo-minimal"><?php echo htmlspecialchars($album["titulo"]); ?></h3>
                                <p class="card-artista-minimal"><?php echo htmlspecialchars($album['artista_principal'] ?? 'Vários'); ?></p>
                                
                                <?php if (!empty($album['ano_lancamento'])): ?>
                                    <p class="card-ano-minimal"><?php echo htmlspecialchars($album['ano_lancamento']); ?></p> 
                                <?php endif; ?> 
                                
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

<div id="albumModal" class="modal-overlay">
<div class="modal-content">
        <span class="modal-close">&times;</span>
        
        <div id="modal-loader" style="text-align: center; padding: 50px;">
            <i class="fas fa-spinner fa-spin" style="font-size: 3em; color: var(--cor-destaque);"></i>
            <p style="margin-top: 15px;">Carregando detalhes do álbum...</p>
        </div>
        
        <div id="modal-details" style="display: none;">
            <div class="modal-grid">
                
                <div class="modal-col-capa">
                    <img id="modal-capa-img" class="modal-capa" src="" alt="Capa do Álbum">
                </div>
                
                <div class="modal-col-details modal-details-container">
                    <h2 id="modal-titulo"></h2>
                    <p id="modal-artistas" style="color: var(--cor-destaque); font-weight: bold;"></p>
                    <p id="modal-lancamento"></p>
                    <p id="modal-gravadora"></p>

                    <div id="modal-relacionamentos" class="modal-details-section">
                        </div>

                    <div id="modal-copia" class="modal-details-section">
                        <h3>Detalhes da Cópia</h3>
                        <div class="modal-info-group">
                            <div class="modal-info-item">
                                <strong>Formato</strong>
                                <span id="modal-formato"></span>
                            </div>
                            <div class="modal-info-item">
                                <strong>Aquisição</strong>
                                <span id="modal-aquisicao"></span>
                            </div>
                            <div class="modal-info-item">
                                <strong>Preço Pago</strong>
                                <span id="modal-preco"></span>
                            </div>
                            <div class="modal-info-item">
                                <strong>Condição</strong>
                                <span id="modal-condicao"></span>
                            </div>
                            <div class="modal-info-item">
                                <strong>Nº Catálogo</strong>
                                <span id="modal-catalogo"></span>
                            </div>
                        </div>
                    </div>
                    
                    <div id="modal-observacoes" class="modal-details-section">
                        <h3>Observações</h3>
                        <p id="modal-obs-text" style="white-space: pre-wrap;"></p>
                    </div>
                </div>
            </div>
            
            <div id="modal-actions" class="modal-actions">
                </div>
            
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('albumModal');
    const modalContent = modal.querySelector('.modal-content');
    const closeBtn = modal.querySelector('.modal-close');
    // NOTE: Esta seleção só pegará os itens da página atual, o que é o comportamento desejado.
    const cardElements = document.querySelectorAll('.colecao-item-card.open-modal'); 
    const detailsDiv = document.getElementById('modal-details');
    const loaderDiv = document.getElementById('modal-loader');
    
    // Configurações e URLs
    const editUrlBase = 'editar_colecao.php?id=';
    const deleteUrlBase = 'excluir_colecao.php?id=';
    const restoreUrlBase = 'restaurar_colecao.php?id='; 
    const fetchUrlBase = 'fetch_album_details.php?id=';

    // Função para fechar o modal
    const closeModal = () => {
        modal.style.display = 'none';
        detailsDiv.style.display = 'none';
        loaderDiv.style.display = 'block';
        modalContent.classList.remove('loaded');
        document.getElementById('modal-relacionamentos').innerHTML = ''; 
        document.getElementById('modal-actions').innerHTML = ''; 
    };

    // Fechar ao clicar no 'x' e fora do modal
    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });

    // Fechar ao pressionar ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            closeModal();
        }
    });

    // Lógica para abrir e popular o modal
    cardElements.forEach(card => {
        card.addEventListener('click', async (e) => {
            const albumId = e.currentTarget.dataset.albumId;
            const albumAtivo = e.currentTarget.dataset.ativo; 
            
            // 1. Mostrar modal e loader
            modal.style.display = 'flex';
            
            try {
                // 2. Fazer requisição AJAX
                const response = await fetch(fetchUrlBase + albumId);
                const result = await response.json();

                if (result.success && result.album) {
                    const album = result.album;
                    
                    // 3. Popular o conteúdo do modal (Manteve-se o mesmo)
                    document.getElementById('modal-capa-img').src = album.capa_url || '../assets/no-cover.png'; 
                    document.getElementById('modal-titulo').textContent = album.titulo;
                    
                    const artistas = album.relacionamentos.artistas ? album.relacionamentos.artistas.join(', ') : 'N/A';
                    document.getElementById('modal-artistas').textContent = artistas;
                    
                    document.getElementById('modal-lancamento').textContent = 'Lançamento: ' + album.data_lancamento_formatada;
                    document.getElementById('modal-gravadora').textContent = 'Gravadora: ' + (album.gravadora_nome || 'N/A');

                    document.getElementById('modal-formato').textContent = album.formato_descricao || 'N/A';
                    document.getElementById('modal-aquisicao').textContent = album.data_aquisicao_formatada || 'N/A';
                    document.getElementById('modal-preco').textContent = album.preco_formatado;
                    document.getElementById('modal-condicao').textContent = album.condicao || 'N/A';
                    document.getElementById('modal-catalogo').textContent = album.numero_catalogo || 'N/A';
                    
                    document.getElementById('modal-obs-text').textContent = album.observacoes || 'Nenhuma observação registrada.';
                    
                    // Relacionamentos M:N
                    const relContainer = document.getElementById('modal-relacionamentos');
                    relContainer.innerHTML = '';

                    const appendRelationship = (title, items) => {
                        if (items && items.length > 0) {
                            const p = document.createElement('p');
                            p.innerHTML = `<strong>${title}:</strong> ${items.join(', ')}`;
                            relContainer.appendChild(p);
                        }
                    };

                    appendRelationship('Gêneros', album.relacionamentos.generos);
                    appendRelationship('Estilos', album.relacionamentos.estilos);
                    appendRelationship('Produtores', album.relacionamentos.produtores);


                    // Lógica Condicional para Links de Ação (Manteve-se o mesmo)
                    const actionsDiv = document.getElementById('modal-actions');
                    actionsDiv.innerHTML = ''; 
                    
                    if (albumAtivo == 1) { // Item ATIVO
                        actionsDiv.innerHTML = `
                            <a href="${editUrlBase + albumId}" class="edit action-icon"><i class="fa fa-pencil-alt"></i> Editar</a>
                            <a href="${deleteUrlBase + albumId}" class="delete action-icon"
                                onclick="return confirm('Tem certeza que deseja REMOVER (Exclusão Lógica) este item da sua coleção?');">
                                <i class="fa fa-trash-alt"></i> Remover
                            </a>
                        `;
                    } else { // Item REMOVIDO / LIXEIRA
                        actionsDiv.innerHTML = `
                            <a href="${restoreUrlBase + albumId}" class="restore action-icon" style="background-color: #28a745;"
                                onclick="return confirm('Tem certeza que deseja RESTAURAR este item para a sua Coleção?');">
                                <i class="fa fa-undo"></i> Restaurar Item
                            </a>
                        `;
                    }

                    // 4. Esconder loader e mostrar detalhes
                    loaderDiv.style.display = 'none';
                    detailsDiv.style.display = 'block';
                    modalContent.classList.add('loaded');

                } else {
                    alert('Erro ao carregar detalhes: ' + (result.message || 'Resposta inválida.'));
                    closeModal();
                }

            } catch (error) {
                console.error('Erro de rede ou parsing:', error);
                alert('Falha ao conectar ao servidor para carregar os detalhes.');
                closeModal();
            }
        });
    });
});
</script>

<?php require_once "../include/footer.php"; ?>