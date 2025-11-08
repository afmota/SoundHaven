<?php
// Arquivo: estatisticas.php
// Página que exibe estatísticas e métricas da Coleção Pessoal.

require_once 'db/conexao.php';
require_once 'funcoes.php'; 

// Array para armazenar todos os dados de estatísticas
$stats = [
    'geral' => [],
    'formatos' => [],
    'top_artistas' => [],
    'top_generos' => [],
    'top_gravadoras' => [],
];

try {
    // ----------------------------------------------------
    // 1. ESTATÍSTICAS GERAIS (Total de Itens, Preços, Datas)
    // ----------------------------------------------------
    $sql_geral = "
        SELECT 
            COUNT(id) AS total_itens,
            SUM(preco) AS soma_precos,
            AVG(preco) AS media_preco,
            COUNT(CASE WHEN preco IS NULL THEN 1 END) AS itens_sem_preco,
            MIN(data_aquisicao) AS data_mais_antiga,
            MAX(data_aquisicao) AS data_mais_recente
        FROM colecao
        WHERE ativo = 1";
        
    $stmt_geral = $pdo->query($sql_geral);
    $stats['geral'] = $stmt_geral->fetch(PDO::FETCH_ASSOC);

    // ----------------------------------------------------
    // 2. DISTRIBUIÇÃO POR FORMATO
    // ----------------------------------------------------
    $sql_formatos = "
        SELECT 
            f.descricao AS formato,
            COUNT(c.id) AS total
        FROM colecao AS c
        INNER JOIN formatos AS f ON c.formato_id = f.id
        WHERE c.ativo = 1
        GROUP BY f.descricao
        ORDER BY total DESC";
        
    $stmt_formatos = $pdo->query($sql_formatos);
    $stats['formatos'] = $stmt_formatos->fetchAll(PDO::FETCH_ASSOC);

    // ----------------------------------------------------
    // 3. TOP 5 ARTISTAS
    // ----------------------------------------------------
    $sql_artistas = "
        SELECT 
            a.nome AS nome,
            COUNT(ca.colecao_id) AS total
        FROM artistas AS a
        JOIN colecao_artista AS ca ON a.id = ca.artista_id
        JOIN colecao AS c ON c.id = ca.colecao_id
        WHERE c.ativo = 1
        GROUP BY a.nome
        ORDER BY total DESC
        LIMIT 5";
        
    $stmt_artistas = $pdo->query($sql_artistas);
    $stats['top_artistas'] = $stmt_artistas->fetchAll(PDO::FETCH_ASSOC);

    // ----------------------------------------------------
    // 4. TOP 5 GÊNEROS
    // ----------------------------------------------------
    $sql_generos = "
        SELECT 
            g.descricao AS nome,
            COUNT(cg.colecao_id) AS total
        FROM generos AS g
        JOIN colecao_genero AS cg ON g.id = cg.genero_id
        JOIN colecao AS c ON c.id = cg.colecao_id
        WHERE c.ativo = 1
        GROUP BY g.descricao
        ORDER BY total DESC
        LIMIT 5";
        
    $stmt_generos = $pdo->query($sql_generos);
    $stats['top_generos'] = $stmt_generos->fetchAll(PDO::FETCH_ASSOC);

    // ----------------------------------------------------
    // 5. TOP 5 GRAVADORAS
    // ----------------------------------------------------
    $sql_gravadoras = "
        SELECT 
            g.nome AS nome,
            COUNT(c.id) AS total
        FROM gravadoras AS g
        JOIN colecao AS c ON g.id = c.gravadora_id
        WHERE c.ativo = 1
        GROUP BY g.nome
        ORDER BY total DESC
        LIMIT 5";
        
    $stmt_gravadoras = $pdo->query($sql_gravadoras);
    $stats['top_gravadoras'] = $stmt_gravadoras->fetchAll(PDO::FETCH_ASSOC);

} catch (\PDOException $e) {
    die("Erro ao calcular estatísticas: " . $e->getMessage());
}

// Cálculo do percentual para a distribuição de formatos
$total_itens_geral = $stats['geral']['total_itens'] > 0 ? $stats['geral']['total_itens'] : 1; // Evita divisão por zero
foreach ($stats['formatos'] as $key => $formato) {
    $stats['formatos'][$key]['percentual'] = ($formato['total'] / $total_itens_geral) * 100;
}


// ----------------------------------------------------
// HTML DA PÁGINA
// ----------------------------------------------------
require_once 'include/header.php'; 
?>

<div class="container">
    <div class="main-layout">
        <div class="content-area">
            <h1 style="margin-bottom: 30px;">Estatísticas da Sua Coleção Pessoal</h1>
            
            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px;">
                
                <div class="card stat-card" style="text-align: center; padding: 20px;">
                    <small style="color: var(--cor-texto-secundario);">Total de Itens na Coleção</small>
                    <h2 style="margin: 5px 0 0; font-size: 2.5em; color: #007bff;"><?php echo $stats['geral']['total_itens']; ?></h2>
                </div>
                
                <div class="card stat-card" style="text-align: center; padding: 20px;">
                    <small style="color: var(--cor-texto-secundario);">Valor Total Registrado</small>
                    <h2 style="margin: 5px 0 0; font-size: 2.5em; color: #28a745;">
                        R$ <?php echo number_format($stats['geral']['soma_precos'] ?? 0, 2, ',', '.'); ?>
                    </h2>
                    <?php if ($stats['geral']['itens_sem_preco'] > 0): ?>
                        <small style="display: block; color: #dc3545;">(<?php echo $stats['geral']['itens_sem_preco']; ?> itens sem preço)</small>
                    <?php endif; ?>
                </div>

                <div class="card stat-card" style="text-align: center; padding: 20px;">
                    <small style="color: var(--cor-texto-secundario);">Preço Médio por Item</small>
                    <h2 style="margin: 5px 0 0; font-size: 2.5em; color: var(--cor-destaque);">
                        R$ <?php echo number_format($stats['geral']['media_preco'] ?? 0, 2, ',', '.'); ?>
                    </h2>
                </div>
            </div>

            <div class="stats-detail-grid" style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px;">
                
                <div class="card" style="padding: 20px;">
                    
                    <h3 style="border-bottom: 2px solid var(--cor-borda); padding-bottom: 5px; margin-bottom: 15px;">Distribuição por Formato</h3>
                    <table class="data-table" style="width: 100%;">
                        <thead>
                            <tr style="color: var(--cor-destaque)">
                                <th>Formato</th>
                                <th>Quantidade</th>
                                <th>Percentual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($stats['formatos'])): ?>
                                <tr><td colspan="3" style="text-align: center;">Nenhum item com formato registrado.</td></tr>
                            <?php else: ?>
                                <?php foreach ($stats['formatos'] as $formato): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($formato['formato']); ?></td>
                                        <td><?php echo $formato['total']; ?></td>
                                        <td>
                                            <?php echo number_format($formato['percentual'], 1, ',', '.'); ?>%
                                            <div style="background-color: var(--cor-fundo-principal); height: 5px; margin-top: 5px; border-radius: 2px;">
                                                <div style="width: <?php echo $formato['percentual']; ?>%; height: 100%; background-color: #007bff; border-radius: 2px;"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div style="margin-top: 30px; padding-top: 15px;">
                        <h3 style="border-bottom: 2px solid var(--cor-borda); padding-bottom: 5px; margin-bottom: 10px;">Primeira e Última Aquisição</h3>
                        <p style="margin-bottom: 5px;">
                            <strong>Primeira Aquisição:</strong> 
                            <?php echo $stats['geral']['data_mais_antiga'] ? formatar_data($stats['geral']['data_mais_antiga']) : 'N/A'; ?>
                        </p>
                        <p>
                            <strong>Última Aquisição:</strong> 
                            <?php echo $stats['geral']['data_mais_recente'] ? formatar_data($stats['geral']['data_mais_recente']) : 'N/A'; ?>
                        </p>
                    </div>
                </div>

                <div class="card" style="padding: 20px;">
                    
                    <h3 style="border-bottom: 2px solid var(--cor-borda); padding-bottom: 5px; margin-bottom: 15px;">Top 5 Artistas</h3>
                    <ol style="padding-left: 20px; color: var(--cor-texto-principal);">
                        <?php if (empty($stats['top_artistas'])): ?>
                            <li>Nenhum artista registrado.</li>
                        <?php else: ?>
                            <?php foreach ($stats['top_artistas'] as $artista): ?>
                                <li><?php echo htmlspecialchars($artista['nome']); ?> (<span style="color: var(--cor-destaque);"><?php echo $artista['total']; ?></span> itens)</li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ol>
                    
                    <h3 style="border-bottom: 2px solid var(--cor-borda); padding-top: 20px; padding-bottom: 5px; margin-bottom: 15px;">Top 5 Gêneros</h3>
                    <ol style="padding-left: 20px; color: var(--cor-texto-principal);">
                        <?php if (empty($stats['top_generos'])): ?>
                            <li>Nenhum gênero registrado.</li>
                        <?php else: ?>
                            <?php foreach ($stats['top_generos'] as $genero): ?>
                                <li><?php echo htmlspecialchars($genero['nome']); ?> (<span style="color: var(--cor-destaque);"><?php echo $genero['total']; ?></span> itens)</li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ol>

                    <h3 style="border-bottom: 2px solid var(--cor-borda); padding-top: 20px; padding-bottom: 5px; margin-bottom: 15px;">Top 5 Gravadoras</h3>
                    <ol style="padding-left: 20px; color: var(--cor-texto-principal);">
                        <?php if (empty($stats['top_gravadoras'])): ?>
                            <li>Nenhuma gravadora registrada.</li>
                        <?php else: ?>
                            <?php foreach ($stats['top_gravadoras'] as $gravadora): ?>
                                <li><?php echo htmlspecialchars($gravadora['nome']); ?> (<span style="color: var(--cor-destaque);"><?php echo $gravadora['total']; ?></span> itens)</li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ol>
                </div>
            </div>
            
        </div>
    </div>
</div>

<?php require_once 'include/footer.php'; ?>