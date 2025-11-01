<?php
// Arquivo: detalhes_colecao.php
// Visualiza os detalhes de um item específico na COLEÇÃO PESSOAL (tabela 'colecao').

require_once '../db/conexao.php';
require_once '../funcoes.php'; 

// ----------------------------------------------------
// 1. OBTENÇÃO DO ID E BUSCA DOS DADOS PRINCIPAIS
// ----------------------------------------------------
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$item = null;
$mensagem_status = '';
$tipo_mensagem = '';

if (!$id) {
    $mensagem_status = "ID do item da Coleção não fornecido.";
    $tipo_mensagem = 'erro';
} else {
    try {
        // SQL para obter os dados principais, incluindo o novo campo spotify_embed_url
        $sql = "SELECT 
                    c.*, 
                    c.spotify_embed_url, /* Campo do Spotify incluído */
                    g.nome AS nome_gravadora,
                    f.descricao AS descricao_formato,
                    s.titulo AS titulo_store_ref
                FROM colecao AS c
                LEFT JOIN gravadoras AS g ON c.gravadora_id = g.id
                LEFT JOIN formatos AS f ON c.formato_id = f.id
                LEFT JOIN store AS s ON c.store_id = s.id
                WHERE c.id = :id AND c.ativo = 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            $mensagem_status = "Item da Coleção não encontrado ou foi excluído.";
            $tipo_mensagem = 'erro';
        }

    } catch (\PDOException $e) {
        $mensagem_status = "Erro ao buscar dados do item: " . $e->getMessage();
        $tipo_mensagem = 'erro';
    }
    
    // ----------------------------------------------------
    // 2. BUSCA DOS RELACIONAMENTOS M:N (Se o item foi encontrado)
    // ----------------------------------------------------
    $artistas = fetchRelacionamentosM_N($pdo, 'colecao_artista', 'artistas', 'colecao_id', $id);
    $produtores = fetchRelacionamentosM_N($pdo, 'colecao_produtor', 'produtores', 'colecao_id', $id);
    $generos = fetchRelacionamentosM_N($pdo, 'colecao_genero', 'generos', 'colecao_id', $id);
    $estilos = fetchRelacionamentosM_N($pdo, 'colecao_estilo', 'estilos', 'colecao_id', $id);

    // Funções de busca M:N:
    // **NOTA:** Essa função 'fetchRelacionamentosM_N' deve estar em seu 'funcoes.php'
}

// ----------------------------------------------------
// 3. HTML DA PÁGINA
// ----------------------------------------------------
require_once '../include/header.php'; 
?>

<div class="container" style="padding-top: 100px; padding-bottom: 60px"> 
    <div class="main-layout"> 
        
        <main class="content-area full-width">
            
            <div class="page-header-actions">
                <h1>
                    <?php 
                        $titulo_limpo = $item ? html_entity_decode($item['titulo']) : 'Detalhes da Coleção';
                        echo 'Álbum: ' . htmlspecialchars($titulo_limpo);
                    ?>
                </h1>
                
                <div class="header-buttons">
                    <?php if ($item): ?>
                        
                        <a href="editar_colecao.php?id=<?php echo $item['id']; ?>" class="btn-action edit-button">
                            <i class="fas fa-edit"></i> Editar Cópia
                        </a>
                        
                    <?php endif; ?>

                    <a href="/dashboard.php" class="back-link secondary-action">
                        <i class="fas fa-chevron-left"></i> Voltar à Coleção
                    </a>
                </div>
            </div>

            <?php if (!empty($mensagem_status)): ?>
                <p class="alerta <?php echo $tipo_mensagem; ?>"><?php echo $mensagem_status; ?></p>
            <?php endif; ?>

            <?php if ($item): ?>
                
                <div class="details-cards-wrapper" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                
                    <div class="card details-card-metadata"> 
                        
                        <div class="details-panel-info">
                            <h2><i class="fas fa-compact-disc"></i> Metadados do Álbum</h2>
                            
                            <div class="detail-item">
                                <span class="label">Título:</span>
                                <span class="value main-title"><?php echo htmlspecialchars($item['titulo']); ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="label">Artista(s):</span>
                                <span class="value"><?php echo join(', ', array_column($artistas, 'nome')) ?: 'N/A'; ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="label">Produtor(es):</span>
                                <span class="value"><?php echo join(', ', array_column($produtores, 'nome')) ?: 'N/A'; ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="label">Gravadora:</span>
                                <span class="value"><?php echo htmlspecialchars($item['nome_gravadora'] ?? 'N/A'); ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="label">Data Lançamento:</span>
                                <span class="value"><?php echo formatar_data(htmlspecialchars($item['data_lancamento'])); ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="label">Gênero(s):</span>
                                <span class="value"><?php echo join(', ', array_column($generos, 'descricao')) ?: 'N/A'; ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="label">Estilos/Subgêneros:</span>
                                <span class="value"><?php echo join(', ', array_column($estilos, 'descricao')) ?: 'N/A'; ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="label">Link da Capa:</span>
                                <span class="value"><?php echo $item['capa_url'] ? '<a href="' . htmlspecialchars($item['capa_url']) . '" target="_blank">Ver Imagem</a>' : 'N/A'; ?></span>
                            </div>
                            
                            <?php if ($item['store_id']): ?>
                                <div class="detail-item">
                                    <span class="label">Ref. Catálogo/Loja:</span>
                                    <span class="value">
                                        <a href="detalhes_album.php?id=<?php echo $item['store_id']; ?>">
                                            #<?php echo $item['store_id'] . ' (' . htmlspecialchars($item['titulo_store_ref']) . ')'; ?>
                                        </a>
                                    </span>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div> <div class="card details-card-status">
                        <div class="details-panel-status">
                            <h2><i class="fas fa-tags"></i> Detalhes Dessa Cópia</h2>

                            <div class="detail-item">
                                <span class="label">Data de Aquisição:</span>
                                <span class="value date-log"><?php echo formatar_data(htmlspecialchars($item['data_aquisicao'])); ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="label">Formato:</span>
                                <span class="value"><?php echo htmlspecialchars($item['descricao_formato'] ?? 'N/A'); ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="label">Número de Catálogo:</span>
                                <span class="value"><?php echo htmlspecialchars($item['numero_catalogo'] ?? 'N/A'); ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="label">Preço Pago:</span>
                                <span class="value price-tag">
                                    <?php echo $item['preco'] !== null ? 'R$ ' . number_format($item['preco'], 2, ',', '.') : 'N/A'; ?>
                                </span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="label">Condição:</span>
                                <span class="value"><?php echo htmlspecialchars($item['condicao'] ?? 'N/A'); ?></span>
                            </div>

                            <div class="detail-item full-width-obs">
                                <span class="label">Observações:</span>
                                <p class="value-obs"><?php echo nl2br(htmlspecialchars($item['observacoes'] ?? 'Nenhuma')); ?></p>
                            </div>

                            <h3 class="log-title">Logs do Sistema</h3>
                            <div class="detail-item">
                                <span class="label">Criado em:</span>
                                <span class="value date-log-full"><?php echo formatar_data(htmlspecialchars($item['criado_em'])); ?></span>
                            </div>

                            <div class="detail-item">
                                <span class="label">Atualizado em:</span>
                                <span class="value date-log-full"><?php echo formatar_data(htmlspecialchars($item['atualizado_em'])); ?></span>
                            </div>
                        </div>
                    </div> </div> <?php if (!empty($item['spotify_embed_url'])): ?>
                    
                    <div class="card spotify-embed-card" style="margin-top: 0px; margin-bottom: 40px; padding: 15px; overflow: hidden; max-height: 450px;"> 
                        
                        <h3 style="margin-top: 0; border-bottom: 1px solid var(--cor-borda); padding-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                            <i class="fab fa-spotify" style="color: #1DB954;"></i> Player do Álbum
                        </h3>
                        
                        <?php 
                        $embed_html = $item['spotify_embed_url'];
                        
                        // 1. Removemos atributos problemáticos de width/height
                        $embed_html = preg_replace('/width="[0-9%]*"|height="[0-9%]*"/', '', $embed_html);
                        
                        // 2. ADICIONAMOS UM WRAPPER DENTRO DO CARD E FORÇAMOS OS ESTILOS NO IFRAME
                        
                        // Ajusta o iframe para ter uma altura fixa (352px é o padrão do Spotify) e ser 100% da largura
                        $embed_html_final = str_replace(
                            '<iframe', 
                            '<iframe style="width: 100%; height: 352px; display: block; border: none; max-height: 352px;"', 
                            $embed_html
                        );
                        
                        // Envolvemos o iframe manipulado em uma div com overflow e max-height para contenção
                        echo '<div style="overflow: hidden; max-height: 352px;">';
                        echo $embed_html_final; 
                        echo '</div>';
                        ?>
                        
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </main>
    </div>
</div>

<?php require_once '../include/footer.php'; ?>