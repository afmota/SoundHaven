<?php
// Arquivo: detalhes_colecao.php
// Visualiza os detalhes de um item específico na COLEÇÃO PESSOAL (tabela 'colecao').
// NOVIDADE: Suporte à listagem de FAIXAS (Músicas) com URL de áudio (substituindo o Spotify iframe).
// CORREÇÃO: Restaura o layout de dois cards lado a lado usando details-grid.

require_once '../db/conexao.php';
require_once '../funcoes.php'; 

// ----------------------------------------------------
// 1. OBTENÇÃO DO ID E BUSCA DOS DADOS PRINCIPAIS
// ----------------------------------------------------
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$item = null;
$faixas = []; // Novo array para faixas
$generos = [];
$estilos = [];
$mensagem_status = '';
$tipo_mensagem = '';

if (!$id) {
    $mensagem_status = "ID do item da Coleção não fornecido.";
    $tipo_mensagem = 'erro';
} else {
    try {
        // SQL para obter os dados principais
        $sql = "SELECT 
                    c.*, 
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
            $mensagem_status = "Item da Coleção não encontrado ou está inativo.";
            $tipo_mensagem = 'erro';
        } else {
            // 2. CARREGA FAIXAS
            $sql_faixas = "SELECT numero_faixa, titulo, duracao, audio_url 
                           FROM faixas_colecao 
                           WHERE colecao_id = :id 
                           ORDER BY numero_faixa ASC";
            $stmt_faixas = $pdo->prepare($sql_faixas);
            $stmt_faixas->execute([':id' => $id]);
            $faixas = $stmt_faixas->fetchAll(PDO::FETCH_ASSOC);

            // 3. CARREGA GÊNEROS
            $sql_generos = "SELECT g.descricao FROM colecao_genero cg 
                            JOIN generos g ON cg.genero_id = g.id 
                            WHERE cg.colecao_id = :id";
            $stmt_generos = $pdo->prepare($sql_generos);
            $stmt_generos->execute([':id' => $id]);
            $generos = array_column($stmt_generos->fetchAll(PDO::FETCH_ASSOC), 'descricao');

            // 4. CARREGA ESTILOS
            $sql_estilos = "SELECT e.descricao FROM colecao_estilo ce 
                            JOIN estilos e ON ce.estilo_id = e.id 
                            WHERE ce.colecao_id = :id";
            $stmt_estilos = $pdo->prepare($sql_estilos);
            $stmt_estilos->execute([':id' => $id]);
            $estilos = array_column($stmt_estilos->fetchAll(PDO::FETCH_ASSOC), 'descricao');
        }

    } catch (\PDOException $e) {
        $mensagem_status = "Erro de banco de dados: " . $e->getMessage();
        $tipo_mensagem = 'erro';
    }
}

require_once '../include/header.php'; 
?>

<div class="container" style="padding-top: 100px;">
    <div class="main-layout">
        
        <main class="content-area full-width">
            
            <?php if (!empty($mensagem_status)): ?>
                <p class="alerta <?php echo $tipo_mensagem; ?>"><?php echo $mensagem_status; ?></p>
            <?php elseif ($item): ?>

                <div class="page-header-actions">
                    <h1>Detalhes da Coleção: <?php echo htmlspecialchars($item['titulo']); ?></h1>
                    
                    <div class="header-buttons">
                        <a href="editar_colecao.php?id=<?php echo htmlspecialchars($item['id']); ?>" class="btn-action edit-button">
                            <i class="fas fa-edit"></i> Editar Item
                        </a>
                    </div>
                </div>

                <div class="details-grid">
                    
                    <div class="card">
                        <h3 style="margin-top: 0;"><i class="fas fa-info-circle"></i> Metadados do Álbum</h3>
                        
                        <div style="display: flex; gap: 20px; margin-bottom: 20px; align-items: flex-start;">
                            <div class="capa-container" style="flex-shrink: 0; width: 150px;">
                                <img src="<?php echo htmlspecialchars($item['capa_url'] ?? 'img/default_cover.png'); ?>" 
                                     alt="Capa do Álbum" class="album-cover-detail" style="width: 150px; height: 150px;">
                            </div>
                            
                            <div class="info-principal" style="flex-grow: 1;">
                                <h2 style="margin-top: 0; font-size: 1.5em;"><?php echo htmlspecialchars($item['titulo']); ?></h2>
                                <p class="data-lancamento" style="font-size: 1em;">Lançamento Original: **<?php echo formatar_data($item['data_lancamento']); ?>**</p>
                                <p class="titulo-store-ref" style="font-size: 0.9em; color: var(--cor-texto-secundaria);">Ref. Catálogo Store ID: #<?php echo htmlspecialchars($item['store_id'] ?? 'N/A'); ?></p>
                            </div>
                        </div>

                        <div class="details-info-section" style="grid-template-columns: 1fr; gap: 0;">
                            <div class="detail-item">
                                <span class="label"><i class="fas fa-industry"></i> Gravadora:</span>
                                <span class="value"><?php echo htmlspecialchars($item['nome_gravadora'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="label"><i class="fas fa-tag"></i> Nº Catálogo:</span>
                                <span class="value"><?php echo htmlspecialchars($item['numero_catalogo'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="label"><i class="fas fa-compact-disc"></i> Formato:</span>
                                <span class="value">**<?php echo htmlspecialchars($item['descricao_formato'] ?? 'N/A'); ?>**</span>
                            </div>
                        </div>

                        <div class="full-width-section" style="padding-top: 15px; margin-top: 15px; border-top: 1px dashed var(--cor-borda);">
                            <p class="card-tags">
                                <span class="tag-label" style="font-weight: 600;"><i class="fas fa-bookmark"></i> Gêneros:</span>
                                <?php echo empty($generos) ? 'N/A' : implode(', ', array_map('htmlspecialchars', $generos)); ?>
                            </p>
                            <p class="card-tags">
                                <span class="tag-label" style="font-weight: 600;"><i class="fas fa-palette"></i> Estilos:</span>
                                <?php echo empty($estilos) ? 'N/A' : implode(', ', array_map('htmlspecialchars', $estilos)); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="card">
                        <h3 style="margin-top: 0;"><i class="fas fa-receipt"></i> Detalhes Dessa Cópia</h3>

                        <div class="details-info-section" style="grid-template-columns: 1fr; gap: 0;">
                            <div class="detail-item">
                                <span class="label"><i class="fas fa-calendar-check"></i> Adquirido em:</span>
                                <span class="value"><?php echo formatar_data($item['data_aquisicao']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="label"><i class="fas fa-dollar-sign"></i> Preço Pago:</span>
                                <span class="value">R$ <?php echo number_format($item['preco'] ?? 0, 2, ',', '.'); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="label"><i class="fas fa-star"></i> Condição:</span>
                                <span class="value destaque-valor"><?php echo htmlspecialchars($item['condicao'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="label"><i class="fas fa-toggle-on"></i> Status:</span>
                                <span class="value destaque-valor"><?php echo ($item['ativo'] == 1) ? 'Ativo' : 'Excluído (Lógica)'; ?></span>
                            </div>
                        </div>

                        <?php if (!empty($item['observacoes'])): ?>
                            <div class="info-block full-width-section" style="padding-top: 15px; margin-top: 15px; border-top: 1px dashed var(--cor-borda);">
                                <h4 style="margin-bottom: 5px; font-size: 1em;"><i class="fas fa-comment-dots"></i> Observações</h4>
                                <p style="white-space: pre-wrap; font-size: 0.9em;"><?php echo htmlspecialchars($item['observacoes']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card" style="margin-top: 30px;">
                    <h3><i class="fas fa-list-ol"></i> Faixas e Preview de Áudio</h3>
                    
                    <?php if (!empty($faixas)): ?>
                        <ul class="lista-faixas" style="list-style: none; padding: 0;">
                            <?php foreach ($faixas as $faixa): ?>
                                <li style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--cor-borda);">
                                    <div style="flex: 3; display: flex; align-items: center; gap: 15px;">
                                        <span style="font-weight: bold; min-width: 25px; text-align: right;"><?php echo htmlspecialchars($faixa['numero_faixa']); ?>.</span>
                                        <span style="flex-grow: 1;"><?php echo htmlspecialchars($faixa['titulo']); ?></span>
                                    </div>
                                    <div style="flex: 2; display: flex; justify-content: flex-end; align-items: center; gap: 20px;">
                                        <span style="color: var(--cor-texto-secundario); font-size: 0.9em;"><?php echo htmlspecialchars($faixa['duracao'] ?? 'N/A'); ?></span>
                                        
                                        <?php if (!empty($faixa['audio_url'])): ?>
                                            <audio controls style="width: 200px; height: 30px;">
                                                <source src="<?php echo htmlspecialchars($faixa['audio_url']); ?>" type="audio/mpeg">
                                                <source src="<?php echo htmlspecialchars($faixa['audio_url']); ?>" type="audio/flac">
                                                Seu navegador não suporta o elemento de áudio.
                                            </audio>
                                        <?php else: ?>
                                            <span style="width: 200px; text-align: center; color: var(--cor-borda);">Sem Preview</span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="alerta">Nenhuma faixa cadastrada para este item da coleção.</p>
                    <?php endif; ?>
                </div>

            <?php endif; ?>

        </main>
    </div>
</div>

<?php require_once '../include/footer.php'; ?>