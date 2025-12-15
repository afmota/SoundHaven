<?php 
// Arquivo: src/View/colecao.view.php

// A função getPaginationLink é definida no controlador e está disponível aqui.

// Variáveis disponíveis:
// $page_title, $total_itens, $view_status, $filtro_formato, $colecao, 
// $total_paginas, $pagina_atual, $erro_db

// Verifica se houve erro fatal no banco de dados
if (isset($erro_db) && $erro_db): ?>
    <div class="container">
        <div class="alert-box error-box" style="padding: 20px; margin-top: 100px;">
            <i class="fas fa-exclamation-triangle"></i>
            <p><?php echo htmlspecialchars($erro_db); ?></p>
            <p>Por favor, tente novamente mais tarde.</p>
        </div>
    </div>
    <?php return; // Para a execução da view em caso de erro fatal
endif;
?>

<div class="container">
    <div class="main-layout">
        <div class="content-area">

            <div class="page-header-actions">
                <h1><?php echo $page_title; ?> (Total: <?php echo $total_itens; ?> itens)</h1>
                <div class="view-switcher">
                    <a href="colecao.php?view_status=1<?php echo $filtro_formato ? "&formato=" . urlencode($filtro_formato) : ''; ?>" class="btn-action <?php echo ($view_status == 1) ? 'primary-action' : 'secondary-action'; ?>">
                        <i class="fas fa-box"></i> Itens Ativos
                    </a>
                    <a href="colecao.php?view_status=0<?php echo $filtro_formato ? "&formato=" . urlencode($filtro_formato) : ''; ?>" class="btn-action btn-lixeira-toggle <?php echo ($view_status == 0) ? 'primary-action' : 'secondary-action'; ?>">
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
                    <a href="<?php echo getPaginationLink($pagina_atual - 1, $view_status, $filtro_formato); ?>" class="btn-action secondary-action">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </a>
                <?php else: ?>
                    <span class="btn-action secondary-action disabled" style="opacity: 0.5; cursor: not-allowed;">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </span>
                <?php endif; ?>

                <?php if ($pagina_atual < $total_paginas): ?>
                    <a href="<?php echo getPaginationLink($pagina_atual + 1, $view_status, $filtro_formato); ?>" class="btn-action secondary-action">
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
                            <?php if ($filtro_formato): ?>
                                <br>Não foram encontrados itens no formato "<?php echo htmlspecialchars($filtro_formato); ?>".
                            <?php endif; ?>
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
                                        // Usando a lógica que definimos: LP/Vinyl, CD-R e o resto é CD (ou default)
                                        if (str_contains($formato, 'lp') || str_contains($formato, 'vinyl')) {
                                            echo 'tag-vinyl'; 
                                        } elseif (str_contains($formato, 'cd-r')) {
                                            echo 'tag-cdr'; 
                                        } else {
                                            echo 'tag-cd'; 
                                        }
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
                        <a href="<?php echo getPaginationLink($pagina_atual - 1, $view_status, $filtro_formato); ?>" class="btn-action secondary-action">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </a>
                    <?php else: ?>
                        <span class="btn-action secondary-action disabled" style="opacity: 0.5; cursor: not-allowed;">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </span>
                    <?php endif; ?>

                    <?php if ($pagina_atual < $total_paginas): ?>
                        <a href="<?php echo getPaginationLink($pagina_atual + 1, $view_status, $filtro_formato); ?>" class="btn-action secondary-action">
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

<?php include 'src/View/colecao.modal.view.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('albumModal');
    const modalContent = modal.querySelector('.modal-content');
    const closeBtn = modal.querySelector('.modal-close');
    const cardElements = document.querySelectorAll('.colecao-item-card.open-modal'); 
    const detailsDiv = document.getElementById('modal-details');
    const loaderDiv = document.getElementById('modal-loader');
    
    // Configurações e URLs
    const editUrlBase = 'editar_colecao.php?id=';
    const deleteUrlBase = 'excluir_colecao.php?id=';
    const restoreUrlBase = 'restaurar_colecao.php?id='; 
    const fetchUrlBase = 'fetch_album_details.php?id='; // Controlador de API que criaremos

    // Função que busca e preenche o modal (será completada na próxima etapa)
    async function loadAlbumDetails(albumId, isDeleted) {
        detailsDiv.style.display = 'none';
        loaderDiv.style.display = 'block';
        modal.style.display = 'flex';
        
        try {
            const response = await fetch(`${fetchUrlBase}${albumId}`);
            if (!response.ok) {
                throw new Error('Erro ao buscar dados do álbum.');
            }
            const data = await response.json();

            if (data.status === 'sucesso') {
                // 1. Preencher Detalhes do Álbum (apenas alguns exemplos, precisamos de mais dados)
                document.getElementById('modal-titulo').textContent = data.album.titulo || 'N/A';
                document.getElementById('modal-artistas').textContent = data.album.artistas_nomes || 'Vários Artistas';
                document.getElementById('modal-lancamento').textContent = `Lançamento: ${data.album.data_lancamento_formatada || 'N/A'}`;
                document.getElementById('modal-gravadora').textContent = `Gravadora: ${data.album.gravadora_nome || 'N/A'}`;
                document.getElementById('modal-capa-img').src = data.album.capa_url || 'caminho/para/placeholder.png'; // Substituir pelo caminho real do placeholder
                
                // 2. Preencher Detalhes da Cópia (Cópia Local)
                document.getElementById('modal-formato').textContent = data.album.formato_descricao || 'N/A';
                document.getElementById('modal-aquisicao').textContent = data.album.data_aquisicao_formatada || 'N/A';
                document.getElementById('modal-preco').textContent = data.album.preco_formatado || 'N/A';
                document.getElementById('modal-condicao').textContent = data.album.condicao || 'N/A';
                document.getElementById('modal-catalogo').textContent = data.album.numero_catalogo || 'N/A';
                document.getElementById('modal-obs-text').textContent = data.album.observacoes || 'Nenhuma observação.';
                
                // 3. Preencher Relacionamentos (Gêneros, Estilos, Produtores)
                const relDiv = document.getElementById('modal-relacionamentos');
                relDiv.innerHTML = `
                    <p><strong>Gêneros:</strong> ${data.album.generos_nomes || 'N/A'}</p>
                    <p><strong>Estilos:</strong> ${data.album.estilos_nomes || 'N/A'}</p>
                    <p><strong>Produtores:</strong> ${data.album.produtores_nomes || 'N/A'}</p>
                `;

                // 4. Preencher Lista de Faixas
                const tracklistUl = document.getElementById('tracklist-ul');
                tracklistUl.innerHTML = ''; // Limpa o loader
                if (data.faixas && data.faixas.length > 0) {
                    data.faixas.forEach(faixa => {
                        tracklistUl.innerHTML += `<li>${faixa.numero_faixa}. ${faixa.titulo} (${faixa.duracao || '--'})</li>`;
                    });
                } else {
                    tracklistUl.innerHTML = '<li id="tracklist-status">Lista de faixas não disponível.</li>';
                }

                // 5. Construir Botões de Ação
                const actionsDiv = document.getElementById('modal-actions');
                let actionsHtml = `<a href="${editUrlBase}${albumId}" class="btn-action primary-action"><i class="fas fa-edit"></i> Editar Álbum</a>`;
                
                if (isDeleted) {
                    // Botão de Restaurar (Lixeira)
                    actionsHtml += `<a href="${restoreUrlBase}${albumId}" class="btn-action success-action" onclick="return confirm('Tem certeza que deseja restaurar este álbum?');"><i class="fas fa-undo"></i> Restaurar</a>`;
                } else {
                    // Botão de Excluir (Mover para Lixeira)
                    actionsHtml += `<a href="${deleteUrlBase}${albumId}" class="btn-action danger-action" onclick="return confirm('Tem certeza que deseja mover este álbum para a Lixeira?');"><i class="fas fa-trash-alt"></i> Excluir</a>`;
                }
                actionsDiv.innerHTML = actionsHtml;

                // 6. Exibir
                loaderDiv.style.display = 'none';
                detailsDiv.style.display = 'block';

            } else {
                detailsDiv.innerHTML = `<p class="alert-box error-box">${data.mensagem || 'Erro desconhecido ao carregar detalhes.'}</p>`;
                loaderDiv.style.display = 'none';
                detailsDiv.style.display = 'block';
            }

        } catch (error) {
            console.error('Fetch error:', error);
            detailsDiv.innerHTML = `<p class="alert-box error-box">Não foi possível conectar ao servidor para carregar os detalhes.</p>`;
            loaderDiv.style.display = 'none';
            detailsDiv.style.display = 'block';
        }
    }
    
    // =========================================================================
    // EVENT LISTENERS
    // =========================================================================

    cardElements.forEach(card => {
        card.addEventListener('click', () => {
            const albumId = card.dataset.albumId;
            const isDeleted = card.dataset.ativo == 0;
            loadAlbumDetails(albumId, isDeleted);
        });
    });
    
    // Função para fechar o modal
    const closeModal = () => {
        modal.style.display = 'none';
        detailsDiv.style.display = 'none';
        loaderDiv.style.display = 'block';
        modalContent.classList.remove('loaded');
        // Limpar áreas dinâmicas (como definimos no código original)
        document.getElementById('modal-relacionamentos').innerHTML = ''; 
        document.getElementById('modal-actions').innerHTML = ''; 
        document.getElementById('import-message-area').innerHTML = `
            <ul id="tracklist-ul" style="list-style-type: none; padding-left: 0;">
                <li id="tracklist-status">Carregando lista de faixas...</li>
            </ul>
        `; 
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

});
</script>