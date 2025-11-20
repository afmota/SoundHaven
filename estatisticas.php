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
    'top_artistas_faixas' => [], 
    'top_generos' => [],
    'top_estilos' => [],
    'top_gravadoras' => [],
    'faixas' => [],
    'anos' => [],
];

// Funções time_to_seconds() e format_seconds() são necessárias para este bloco.
// Assumindo que elas estão disponíveis em funcoes.php.

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
    // 3. TOP 5 ARTISTAS (POR ÁLBUM)
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
    // 4. TOP 5 ARTISTAS (POR FAIXA)
    // ----------------------------------------------------
    $sql_artistas_faixas = "
        SELECT 
            a.nome AS nome,
            COUNT(f.id) AS total
        FROM artistas AS a
        JOIN colecao_artista AS ca ON a.id = ca.artista_id
        JOIN colecao_faixas AS f ON ca.colecao_id = f.colecao_id 
        JOIN colecao AS c ON ca.colecao_id = c.id
        WHERE c.ativo = 1
        GROUP BY a.nome
        ORDER BY total DESC
        LIMIT 5";
            
    $stmt_artistas_faixas = $pdo->query($sql_artistas_faixas);
    $stats['top_artistas_faixas'] = $stmt_artistas_faixas->fetchAll(PDO::FETCH_ASSOC);


    // ----------------------------------------------------
    // 5. TOP 5 GÊNEROS
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
    // 6. TOP 5 ESTILOS
    // ----------------------------------------------------
    $sql_estilos = "
        SELECT 
            e.descricao AS nome,
            COUNT(ce.colecao_id) AS total
        FROM estilos AS e
        JOIN colecao_estilo AS ce ON e.id = ce.estilo_id
        JOIN colecao AS c ON c.id = ce.colecao_id
        WHERE c.ativo = 1
        GROUP BY e.descricao
        ORDER BY total DESC
        LIMIT 5";
        
    $stmt_estilos = $pdo->query($sql_estilos);
    $stats['top_estilos'] = $stmt_estilos->fetchAll(PDO::FETCH_ASSOC);

    // ----------------------------------------------------
    // 7. TOP 5 GRAVADORAS
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
    
    // ----------------------------------------------------
    // 8. ESTATÍSTICAS DE FAIXAS E DURAÇÃO
    // ----------------------------------------------------
    $sql_faixas = "
        SELECT 
            c.id AS colecao_id,
            c.titulo AS album_titulo,
            f.titulo AS faixa_titulo,
            f.duracao
        FROM colecao AS c
        JOIN colecao_faixas AS f ON c.id = f.colecao_id 
        WHERE c.ativo = 1 
        AND f.duracao IS NOT NULL 
        AND f.duracao <> ''"; 
        
    $stmt_faixas = $pdo->query($sql_faixas);
    $all_faixas = $stmt_faixas->fetchAll(PDO::FETCH_ASSOC);

    // Variáveis de agregação
    $total_faixas = count($all_faixas);
    $tempo_total_segundos = 0;
    $faixa_mais_longa = ['segundos' => 0, 'titulo' => 'N/A', 'album' => 'N/A'];
    $faixa_mais_curta = ['segundos' => PHP_INT_MAX, 'titulo' => 'N/A', 'album' => 'N/A'];
    $albuns_duracao = []; 
    $album_mais_longo = ['segundos' => 0, 'titulo' => 'N/A'];
    $album_mais_curto = ['segundos' => PHP_INT_MAX, 'titulo' => 'N/A'];

    if ($total_faixas > 0) {
        foreach ($all_faixas as $faixa) {
            $segundos = time_to_seconds($faixa['duracao']);
            $tempo_total_segundos += $segundos;
            
            // Faixa Mais Longa
            if ($segundos > $faixa_mais_longa['segundos']) {
                $faixa_mais_longa['segundos'] = $segundos;
                $faixa_mais_longa['titulo'] = $faixa['faixa_titulo'];
                $faixa_mais_longa['album'] = $faixa['album_titulo'];
            }
            
            // Faixa Mais Curta (Maior que zero)
            if ($segundos > 0 && $segundos < $faixa_mais_curta['segundos']) {
                $faixa_mais_curta['segundos'] = $segundos;
                $faixa_mais_curta['titulo'] = $faixa['faixa_titulo'];
                $faixa_mais_curta['album'] = $faixa['album_titulo'];
            }
            
            // Agregação por Álbum
            if (!isset($albuns_duracao[$faixa['colecao_id']])) {
                $albuns_duracao[$faixa['colecao_id']] = ['segundos' => 0, 'titulo' => $faixa['album_titulo']];
            }
            $albuns_duracao[$faixa['colecao_id']]['segundos'] += $segundos;
        }

        // Álbum Mais Curto/Longo
        foreach ($albuns_duracao as $album) {
            if ($album['segundos'] > $album_mais_longo['segundos']) {
                $album_mais_longo['segundos'] = $album['segundos'];
                $album_mais_longo['titulo'] = $album['titulo'];
            }
            if ($album['segundos'] > 0 && $album['segundos'] < $album_mais_curto['segundos']) {
                $album_mais_curto['segundos'] = $album['segundos'];
                $album_mais_curto['titulo'] = $album['titulo'];
            }
        }
    }

    $tempo_medio_segundos = $total_faixas > 0 ? floor($tempo_total_segundos / $total_faixas) : 0;

    $stats['faixas'] = [
        'total_faixas' => $total_faixas,
        'tempo_total' => format_seconds($tempo_total_segundos),
        'tempo_medio' => format_seconds($tempo_medio_segundos),
        'faixa_mais_longa' => $faixa_mais_longa,
        'faixa_mais_curta' => $faixa_mais_curta['segundos'] !== PHP_INT_MAX ? $faixa_mais_curta : null, 
        'album_mais_longo' => $album_mais_longo,
        'album_mais_curto' => $album_mais_curto['segundos'] !== PHP_INT_MAX ? $album_mais_curto : null, 
    ];
    
    // ----------------------------------------------------
    // 9. DISTRIBUIÇÃO POR ANO DE LANÇAMENTO (Todos os anos, ASC)
    // ----------------------------------------------------
    $sql_anos = "
        SELECT 
            YEAR(data_lancamento) AS ano,
            COUNT(id) AS total
        FROM colecao
        WHERE ativo = 1 
        AND data_lancamento IS NOT NULL
        GROUP BY ano
        ORDER BY ano ASC"; // ORDENADO POR ANO ASCENDENTE
            
    $stmt_anos = $pdo->query($sql_anos);
    $stats['anos'] = $stmt_anos->fetchAll(PDO::FETCH_ASSOC);

    $total_itens_anos = array_sum(array_column($stats['anos'], 'total'));


} catch (\PDOException $e) {
    die("Erro ao calcular estatísticas: " . $e->getMessage());
}

// Cálculo do percentual para a distribuição de formatos
$total_itens_geral = $stats['geral']['total_itens'] > 0 ? $stats['geral']['total_itens'] : 1; 
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
            <h1 style="margin-bottom: 30px;">Estatísticas da Sua Coleção Pessoal 📈</h1>
            
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

            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px;">
                
                <div class="card stat-card" style="text-align: center; padding: 20px;">
                    <small style="color: var(--cor-texto-secundario);">Total de Músicas Registradas</small>
                    <h2 style="margin: 5px 0 0; font-size: 2.5em; color: #007bff;"><?php echo $stats['faixas']['total_faixas']; ?></h2>
                </div>
                
                <div class="card stat-card" style="text-align: center; padding: 20px;">
                    <small style="color: var(--cor-texto-secundario);">Tempo Total de Reprodução</small>
                    <h2 style="margin: 5px 0 0; font-size: 2.5em; color: #28a745;">
                        <?php echo $stats['faixas']['tempo_total']; ?>
                    </h2>
                </div>

                <div class="card stat-card" style="text-align: center; padding: 20px;">
                    <small style="color: var(--cor-texto-secundario);">Duração Média por Música</small>
                    <h2 style="margin: 5px 0 0; font-size: 2.5em; color: var(--cor-destaque);">
                        <?php echo $stats['faixas']['tempo_medio']; ?>
                    </h2>
                </div>
            </div>

            <div class="stats-detail-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                
                <div class="card" style="padding: 20px;">
                    <h3 style="border-bottom: 2px solid var(--cor-borda); padding-bottom: 5px; margin-bottom: 15px;">Distribuição por Formato 💿</h3>
                    
                    <?php if (!empty($stats['formatos'])): ?>
                        <div style="width: 100%; max-width: 400px; margin: 0 auto 20px;">
                            <canvas id="formatoPieChart"></canvas>
                        </div>
                    <?php endif; ?>
                    
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
                </div>
                
                <div style="display: grid; grid-template-rows: auto auto; gap: 30px;">
                    
                    <div class="card" style="padding: 20px;">
                        <h3 style="border-bottom: 2px solid var(--cor-borda); padding-bottom: 5px; margin-bottom: 15px;">Distribuição por Ano de Lançamento 📅</h3>
                        <?php if (empty($stats['anos'])): ?>
                            <p style="text-align: center;">Nenhum ano de lançamento registrado.</p>
                        <?php else: ?>
                            
                            <div style="width: 100%; margin: 0 auto;">
                                <canvas id="anosBarChart"></canvas>
                            </div>
                            
                        <?php endif; ?>
                    </div>
                    
                    <div class="card" style="padding: 20px;">
                        <h3 style="border-bottom: 2px solid var(--cor-borda); padding-bottom: 5px; margin-bottom: 10px;">Primeira e Última Aquisição 🛍️</h3>
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
            </div>
            
            <div class="stats-detail-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 40px;">
                
                <div class="card" style="padding: 20px; grid-column: span 2;">
                    <h3 style="border-bottom: 2px solid var(--cor-borda); padding-bottom: 5px; margin-bottom: 15px;">Destaques de Duração ⏱️</h3>
                    
                    <?php if ($stats['faixas']['total_faixas'] > 0): ?>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                            
                            <div>
                                <h4 style="margin-top: 0; border-bottom: 1px dashed var(--cor-borda); padding-bottom: 5px; color: var(--cor-texto-principal);">Músicas Extremas</h4>
                                <p style="margin-bottom: 5px;">
                                    <strong>Mais Longa:</strong> <span style="color: #dc3545;"><?php echo format_seconds($stats['faixas']['faixa_mais_longa']['segundos']); ?></span>
                                    (<?php echo htmlspecialchars($stats['faixas']['faixa_mais_longa']['titulo']); ?> - <?php echo htmlspecialchars($stats['faixas']['faixa_mais_longa']['album']); ?>)
                                </p>
                                <p>
                                    <strong>Mais Curta:</strong> 
                                    <?php if ($stats['faixas']['faixa_mais_curta']): ?>
                                        <span style="color: #007bff;"><?php echo format_seconds($stats['faixas']['faixa_mais_curta']['segundos']); ?></span>
                                        (<?php echo htmlspecialchars($stats['faixas']['faixa_mais_curta']['titulo']); ?> - <?php echo htmlspecialchars($stats['faixas']['faixa_mais_curta']['album']); ?>)
                                    <?php else: ?>
                                        N/A (Apenas músicas com duração > 0)
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <div>
                                <h4 style="margin-top: 0; border-bottom: 1px dashed var(--cor-borda); padding-bottom: 5px; color: var(--cor-texto-principal);">Álbuns Extremos</h4>
                                <p style="margin-bottom: 5px;">
                                    <strong>Álbum Mais Longo:</strong> <span style="color: #dc3545;"><?php echo format_seconds($stats['faixas']['album_mais_longo']['segundos']); ?></span>
                                    (<?php echo htmlspecialchars($stats['faixas']['album_mais_longo']['titulo']); ?>)
                                </p>
                                <p>
                                    <strong>Álbum Mais Curto:</strong> 
                                    <?php if ($stats['faixas']['album_mais_curto']): ?>
                                        <span style="color: #007bff;"><?php echo format_seconds($stats['faixas']['album_mais_curto']['segundos']); ?></span>
                                        (<?php echo htmlspecialchars($stats['faixas']['album_mais_curto']['titulo']); ?>)
                                    <?php else: ?>
                                        N/A (Apenas álbuns com duração > 0)
                                    <?php endif; ?>
                                </p>
                            </div>

                        </div>
                        
                    <?php else: ?>
                        <p style="color: var(--cor-texto-secundario);">Nenhum dado de faixas registrado para calcular durações.</p>
                    <?php endif; ?>
                </div>
                
                <div class="card" style="padding: 20px;">
                    <h3 style="border-bottom: 2px solid var(--cor-borda); padding-bottom: 5px; margin-bottom: 15px;">Top 5 Gêneros</h3>
                    <?php if (empty($stats['top_generos'])): ?>
                        <p style="text-align: center; color: var(--cor-texto-secundario);">Nenhum gênero registrado.</p>
                    <?php else: ?>
                        <div style="width: 100%; height: 250px;"> 
                            <canvas id="generoBarChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card" style="padding: 20px;">
                    <h3 style="border-bottom: 2px solid var(--cor-borda); padding-bottom: 5px; margin-bottom: 15px;">Top 5 Estilos</h3>
                    <?php if (empty($stats['top_estilos'])): ?>
                        <p style="text-align: center; color: var(--cor-texto-secundario);">Nenhum estilo registrado.</p>
                    <?php else: ?>
                        <div style="width: 100%; height: 250px;"> 
                            <canvas id="estiloBarChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <div class="stats-detail-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; margin-top: 40px;">

                <div class="card" style="padding: 20px;">
                    <h3 style="border-bottom: 2px solid var(--cor-borda); padding-bottom: 5px; margin-bottom: 15px;">Top 5 Artistas (por Álbum)</h3>
                    <?php if (empty($stats['top_artistas'])): ?>
                        <p style="text-align: center; color: var(--cor-texto-secundario);">Nenhum artista registrado.</p>
                    <?php else: ?>
                        <div style="width: 100%; height: 250px;"> 
                            <canvas id="artistaBarChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card" style="padding: 20px;">
                    <h3 style="border-bottom: 2px solid var(--cor-borda); padding-bottom: 5px; margin-bottom: 15px;">Top 5 Artistas (por Faixa) 🎶</h3>
                    <?php if (empty($stats['top_artistas_faixas'])): ?>
                        <p style="text-align: center; color: var(--cor-texto-secundario);">Nenhum artista com faixas registradas.</p>
                    <?php else: ?>
                        <div style="width: 100%; height: 250px;"> 
                            <canvas id="artistaFaixaBarChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="card" style="padding: 20px;">
                    <h3 style="border-bottom: 2px solid var(--cor-borda); padding-bottom: 5px; margin-bottom: 15px;">Top 5 Gravadoras</h3>
                    <?php if (empty($stats['top_gravadoras'])): ?>
                        <p style="text-align: center; color: var(--cor-texto-secundario);">Nenhuma gravadora registrada.</p>
                    <?php else: ?>
                        <div style="width: 100%; height: 250px;"> 
                            <canvas id="gravadoraBarChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --------------------------------------------------------------------------------
    // GRÁFICO 1: Distribuição por Formato (Pizza)
    // --------------------------------------------------------------------------------
    
    const formatoData = <?php echo json_encode($stats['formatos']); ?>;
    
    // Array de Cores
    const cores = [
        '#007bff', // Azul
        '#28a745', // Verde
        '#dc3545', // Vermelho
        '#ffc107', // Amarelo
        '#6f42c1', // Roxo
        '#17a2b8', // Ciano
        '#e83e8c', // Rosa
        '#fd7e14', // Laranja
    ];
    
    const labels = formatoData.map(item => item.formato);
    const dataValues = formatoData.map(item => item.total);
    
    const backgroundColors = labels.map((_, index) => cores[index % cores.length]);

    if (formatoData.length > 0) {
        const ctx = document.getElementById('formatoPieChart').getContext('2d');
        
        const formatoPieChart = new Chart(ctx, {
            type: 'pie', 
            data: {
                labels: labels,
                datasets: [{
                    data: dataValues,
                    backgroundColor: backgroundColors,
                    hoverOffset: 10 
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom', 
                        labels: {
                            // Cor do texto da legenda
                            color: '#ffffff', 
                            font: {
                                size: 14
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                const total = context.parsed;
                                const totalGeral = dataValues.reduce((a, b) => a + b, 0);
                                const percentual = ((total / totalGeral) * 100).toFixed(1);
                                return `${label}${total} itens (${percentual}%)`;
                            }
                        }
                    }
                }
            }
        });
    }


    // --------------------------------------------------------------------------------
    // GRÁFICO 2: Distribuição por Ano (Gráfico de Barras Verticais)
    // --------------------------------------------------------------------------------
    const anosData = <?php echo json_encode($stats['anos']); ?>;

    if (anosData.length > 0) {
        const anosLabels = anosData.map(item => item.ano);
        const anosValues = anosData.map(item => item.total);
        
        const barColor = '#28a745'; // Cor das barras (Verde)

        const ctxAnos = document.getElementById('anosBarChart').getContext('2d');
        
        const anosBarChart = new Chart(ctxAnos, {
            type: 'bar', 
            data: {
                labels: anosLabels,
                datasets: [{
                    label: 'Itens Lançados',
                    data: anosValues,
                    backgroundColor: barColor,
                    borderColor: barColor,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        // Configuração do eixo X para texto claro
                        ticks: {
                            color: 'white',
                            maxRotation: 45, 
                            minRotation: 45
                        },
                        // Configuração da grade
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)', 
                        }
                    },
                    y: {
                        // Configuração do eixo Y para texto claro
                        ticks: {
                            color: 'white',
                            beginAtZero: true,
                            precision: 0 
                        },
                         // Configuração da grade
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)', 
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false 
                    },
                    title: {
                        display: false 
                    },
                    tooltip: {
                         callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                return `${label}${context.parsed.y} itens`;
                            }
                        }
                    }
                }
            }
        });
    }

    // --------------------------------------------------------------------------------
    // FUNÇÃO AUXILIAR: Cria Gráfico de Barra Horizontal para Rankings (Top 5)
    // --------------------------------------------------------------------------------
    function createHorizontalBarChart(elementId, dataArray, labelKey, valueKey, barColor) {
        if (dataArray.length === 0) return;

        // Invertemos a ordem para que o "top 1" fique no topo do gráfico
        const chartLabels = dataArray.map(item => item[labelKey]).reverse();
        const chartValues = dataArray.map(item => item[valueKey]).reverse();

        const ctx = document.getElementById(elementId).getContext('2d');
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Itens',
                    data: chartValues,
                    backgroundColor: barColor,
                    borderColor: barColor,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // Permite que o height: 250px funcione
                indexAxis: 'y', // ESSENCIAL: Transforma em gráfico de barra HORIZONTAL
                scales: {
                    x: {
                        // Eixo X (Valores)
                        beginAtZero: true,
                        ticks: {
                            color: '#fff',
                            precision: 0,
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)',
                        }
                    },
                    y: {
                        // Eixo Y (Rótulos/Nomes)
                        ticks: {
                            color: '#fff',
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)',
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false 
                    },
                    title: {
                        display: false 
                    },
                    tooltip: {
                         callbacks: {
                            label: function(context) {
                                // Define a unidade baseado no ID do elemento (apenas para Artista por Faixa)
                                const unit = (elementId === 'artistaFaixaBarChart') ? ' faixas' : ' itens';
                                return `${context.parsed.x}${unit}`;
                            }
                        }
                    }
                }
            }
        });
    }

    // --------------------------------------------------------------------------------
    // GRÁÁFICOS 3 a 7: Top Rankings (Barras Horizontais)
    // --------------------------------------------------------------------------------

    // Artistas (por Álbum) - Cor Azul
    createHorizontalBarChart(
        'artistaBarChart', 
        <?php echo json_encode($stats['top_artistas']); ?>, 
        'nome', 
        'total', 
        '#007bff'
    );
    
    // Gêneros - Cor Verde
    createHorizontalBarChart(
        'generoBarChart', 
        <?php echo json_encode($stats['top_generos']); ?>, 
        'nome', 
        'total', 
        '#28a745' 
    );

    // Gravadoras - Cor Roxa
    createHorizontalBarChart(
        'gravadoraBarChart', 
        <?php echo json_encode($stats['top_gravadoras']); ?>, 
        'nome', 
        'total', 
        '#6f42c1'
    );
    
    // Artistas (por Faixa) - Cor Vermelha
    createHorizontalBarChart(
        'artistaFaixaBarChart', 
        <?php echo json_encode($stats['top_artistas_faixas']); ?>, 
        'nome', 
        'total', 
        '#dc3545'
    );

    // Estilos - Cor Ciano
    createHorizontalBarChart(
        'estiloBarChart', 
        <?php echo json_encode($stats['top_estilos']); ?>, 
        'nome', 
        'total', 
        '#17a2b8'
    );
    
}); // Fim do DOMContentLoaded
</script>

<?php require_once 'include/footer.php'; ?>