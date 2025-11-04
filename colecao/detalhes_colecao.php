<?php
// Arquivo: detalhes_colecao.php
// Visualiza os detalhes de um item específico na COLEÇÃO PESSOAL (tabela 'colecao').
// NOVIDADE: Suporte à listagem de FAIXAS (Músicas) com URL de áudio.

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
                WHERE c.id = :id AND c.ativo = 1"; // Removido c.spotify_embed_url

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
                    
                    <a href="editar_colecao.php?id=<?php echo htmlspecialchars($item['id']); ?>" class="btn-adicionar-catalogo">
                        <i class="fas fa-edit"></i> Editar Item
                    </a>
                </div>

                <div class="card item-detalhe-card" style="margin-top: 20px;">
                    
                    <div class="card-body-primary">
                        <div class="capa-container">
                            <img src="<?php echo htmlspecialchars($item['capa_url'] ?? 'img/default_cover.png'); ?>" 
                                 alt="Capa do Álbum" class="capa-detalhe">
                        </div>
                        
                        <div class="info-principal">
                            <p class="titulo-store-ref">Referência Store ID: <span style="font-weight: bold;"><?php echo htmlspecialchars($item['store_id'] ?? 'N/A'); ?></span></p>
                            <h2><?php echo htmlspecialchars($item['titulo']); ?></h2>
                            <p class="data-lancamento">Lançamento: <?php echo formatar_data($item['data_lancamento']); ?></p>
                            
                            <p class="info-line"><i class="fas fa-compact-disc"></i> Formato: **<?php echo htmlspecialchars($item['descricao_formato'] ?? 'N/A'); ?>**</p>
                            <p class="info-line"><i class="fas fa-industry"></i> Gravadora: <?php echo htmlspecialchars($item['nome_gravadora'] ?? 'N/A'); ?></p>
                            <p class="info-line"><i class="fas fa-tag"></i> Nº Catálogo: <?php echo htmlspecialchars($item['numero_catalogo'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                    
                    <div class="card-body-secondary">
                        <div class="card-tags">
                            <span class="tag-label"><i class="fas fa-bookmark"></i> Gêneros:</span>
                            <?php echo empty($generos) ? 'N/A' : implode(', ', array_map('htmlspecialchars', $generos)); ?>
                            
                            <br><span class="tag-label"><i class="fas fa-palette"></i> Estilos:</span>
                            <?php echo empty($estilos) ? 'N/A' : implode(', ', array_map('htmlspecialchars', $estilos)); ?>
                        </div>
                        
                        <div class="info-block" style="margin-bottom: 20px;">
                            <h4 style="margin-bottom: 5px;">Detalhes da Sua Cópia</h4>
                            <p>Condição: <span class="destaque-valor"><?php echo htmlspecialchars($item['condicao'] ?? 'N/A'); ?></span></p>
                            <p>Adquirido em: <span class="destaque-valor"><?php echo formatar_data($item['data_aquisicao']); ?></span></p>
                            <p>Preço Pago: <span class="destaque-valor">R$ <?php echo number_format($item['preco'] ?? 0, 2, ',', '.'); ?></span></p>
                            <p>Status: <span class="destaque-valor"><?php echo ($item['ativo'] == 1) ? 'Ativo' : 'Excluído (Lógica)'; ?></span></p>
                        </div>
                        
                        <?php if (!empty($item['observacoes'])): ?>
                            <div class="info-block">
                                <h4 style="margin-bottom: 5px;">Observações</h4>
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