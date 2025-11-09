<?php
// Arquivo: colecao.php
// Listagem dos itens da Coleção Pessoal (tabela 'colecao'). Com visualização em Modal.

require_once "../db/conexao.php";
require_once "../funcoes.php";

// ----------------------------------------------------
// 1. CARREGAR DADOS BÁSICOS DA COLEÇÃO COM ARTISTA PRINCIPAL
// ----------------------------------------------------

$colecao = [];

try {
    // Busca o essencial para o card minimalista + o artista principal
    // A subconsulta traz o nome do primeiro artista associado (em ordem alfabética)
    $sql_colecao = "
        SELECT 
            c.id, 
            c.titulo, 
            c.capa_url,
            YEAR(c.data_lancamento) AS ano_lancamento, /* MUDANÇA: Inclui o Ano */
            f.descricao AS formato_descricao, /* MUDANÇA NOVA: Descrição do Formato */
            (
                SELECT a.nome 
                FROM colecao_artista AS ca
                JOIN artistas AS a ON ca.artista_id = a.id
                WHERE ca.colecao_id = c.id
                ORDER BY a.nome ASC 
                LIMIT 1
            ) AS artista_principal
        FROM colecao AS c
        LEFT JOIN formatos AS f ON c.formato_id = f.id /* MUDANÇA NOVA: JOIN para Formatos */
        WHERE c.ativo = 1 
        ORDER BY c.data_aquisicao DESC, c.titulo ASC";

    $stmt_colecao = $pdo->query($sql_colecao);
    $colecao = $stmt_colecao->fetchAll(PDO::FETCH_ASSOC);

} catch (\PDOException $e) {
    die("Erro ao carregar coleção: " . $e->getMessage());
}

// ----------------------------------------------------
// 2. HTML DA PÁGINA
// ----------------------------------------------------
require_once "../include/header.php";
?>

<div class="container">
    <div class="main-layout">
        <div class="content-area">
            <h1 style="margin-bottom: 20px;">Sua Coleção Pessoal (Total: <?php echo count(
                $colecao
            ); ?> itens)</h1>
            
            <p style="margin-bottom: 20px; color: var(--cor-texto-secundario);">Clique na capa do álbum para ver todos os detalhes e opções de edição.</p>
            
            <div class="colecao-card-grid">
                <?php if (empty($colecao)): ?>
                    <div class="card" style="padding: 20px; text-align: center;">
                        <p style="margin: 0;">Sua coleção está vazia. Adicione itens a partir do Catálogo!</p>
                    </div>
                <?php else: ?>
                
                    <?php foreach ($colecao as $album): ?>
                        
                        <div class="card colecao-item-card open-modal" data-album-id="<?php echo $album['id']; ?>" style="cursor: pointer;">
                            
                            <div class="card-capa-wrapper">
                                <?php if (!empty($album['capa_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($album['capa_url']); ?>"
                                        alt="Capa de <?php echo htmlspecialchars($album['titulo']); ?>"
                                        class="colecao-capa-grande"
                                        loading="lazy">
                                <?php else: ?>
                                    <div class="colecao-capa-grande no-cover">S/ Capa</div>
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
// O código JavaScript do modal permanece o mesmo
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
    const fetchUrlBase = 'fetch_album_details.php?id=';

    // Função para fechar o modal
    const closeModal = () => {
        modal.style.display = 'none';
        detailsDiv.style.display = 'none';
        loaderDiv.style.display = 'block';
        modalContent.classList.remove('loaded');
        // Limpa conteúdo para próxima abertura
        document.getElementById('modal-relacionamentos').innerHTML = ''; 
    };

    // Fechar ao clicar no 'x'
    closeBtn.addEventListener('click', closeModal);

    // Fechar ao clicar fora do modal
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
            
            // 1. Mostrar modal e loader
            modal.style.display = 'flex';
            
            try {
                // 2. Fazer requisição AJAX
                const response = await fetch(fetchUrlBase + albumId);
                const result = await response.json();

                if (result.success && result.album) {
                    const album = result.album;
                    
                    // 3. Popular o conteúdo do modal
                    document.getElementById('modal-capa-img').src = album.capa_url || '../assets/no-cover.png'; 
                    document.getElementById('modal-titulo').textContent = album.titulo;
                    
                    // Artistas
                    const artistas = album.relacionamentos.artistas ? album.relacionamentos.artistas.join(', ') : 'N/A';
                    document.getElementById('modal-artistas').textContent = artistas;
                    
                    // Outros 1:N
                    document.getElementById('modal-lancamento').textContent = 'Lançamento: ' + album.data_lancamento_formatada;
                    document.getElementById('modal-gravadora').textContent = 'Gravadora: ' + (album.gravadora_nome || 'N/A');

                    // Detalhes da Cópia
                    document.getElementById('modal-formato').textContent = album.formato_descricao || 'N/A';
                    document.getElementById('modal-aquisicao').textContent = album.data_aquisicao_formatada || 'N/A';
                    document.getElementById('modal-preco').textContent = album.preco_formatado;
                    document.getElementById('modal-condicao').textContent = album.condicao || 'N/A';
                    document.getElementById('modal-catalogo').textContent = album.numero_catalogo || 'N/A';
                    
                    // Observações
                    document.getElementById('modal-obs-text').textContent = album.observacoes || 'Nenhuma observação registrada.';
                    
                    // Relacionamentos M:N (Gêneros, Estilos, Produtores)
                    const relContainer = document.getElementById('modal-relacionamentos');
                    relContainer.innerHTML = ''; // Limpa antes de preencher

                    // Função auxiliar para criar parágrafos de relacionamento
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


                    // Links de Ação
                    const actionsDiv = document.getElementById('modal-actions');
                    actionsDiv.innerHTML = `
                        <a href="${editUrlBase + albumId}" class="edit action-icon"><i class="fa fa-pencil-alt"></i> Editar</a>
                        <a href="${deleteUrlBase + albumId}" class="delete action-icon"
                            onclick="return confirm('Tem certeza que deseja REMOVER (Exclusão Lógica) este item da sua coleção?');">
                            <i class="fa fa-trash-alt"></i> Remover
                        </a>
                    `;

                    // 4. Esconder loader e mostrar detalhes
                    loaderDiv.style.display = 'none';
                    detailsDiv.style.display = 'block';
                    modalContent.classList.add('loaded'); // Para o efeito de transição

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