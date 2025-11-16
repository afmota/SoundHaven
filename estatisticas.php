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
    // 9. DISTRIBUIÇÃO POR ANO DE LANÇAMENTO (Corrigido)
    // ----------------------------------------------------
    $sql_anos = "
        SELECT 
            YEAR(data_lancamento) AS ano,
            COUNT(id) AS total
        FROM colecao
        WHERE ativo = 1 
        AND data_lancamento IS NOT NULL
        GROUP BY ano
        ORDER BY ano DESC";
            
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


            <div class="stats-detail-grid" style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px;">
                
                <div style="display: grid; grid-template-rows: auto auto auto; gap: 30px;">
                    
                    <div class="card" style="padding: 20px;">
                        <h3 style="border-bottom: 2px solid var(--cor-borda); padding-bottom: 5px; margin-bottom: 15px;">Distribuição por Formato 💿</h3>
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
                    
                    <div class="card" style="padding: 20px;">
                        <h3 style="border-bottom: 2px solid var(--cor-borda); padding-bottom: 5px; margin-bottom: 15px;">Distribuição por Ano de Lançamento (Top 10) 📅</h3>
                        <?php if (empty($stats['anos'])): ?>
                            <p style="text-align: center;">Nenhum ano de lançamento registrado.</p>
                        <?php else: ?>
                            
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                                <?php 
                                $counter = 0;
                                $limit = 10;
                                $max_total_anos = $total_itens_anos > 0 ? $total_itens_anos : 1;
                                
                                foreach ($stats['anos'] as $ano): 
                                    if ($counter >= $limit) break; 
                                    $percentual_ano = ($ano['total'] / $max_total_anos) * 100;
                                ?>
                                    <div style="margin-bottom: 10px;">
                                        <span style="font-weight: bold;"><?php echo $ano['ano']; ?></span> 
                                        (<?php echo $ano['total']; ?> itens - <?php echo number_format($percentual_ano, 1, ',', '.'); ?>%)
                                        <div style="background-color: var(--cor-fundo-principal); height: 8px; margin-top: 2px; border-radius: 4px;">
                                            <div style="width: <?php echo $percentual_ano; ?>%; height: 100%; background-color: var(--cor-destaque); border-radius: 4px;"></div>
                                        </div>
                                    </div>
                                <?php $counter++; endforeach; ?>
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

                                <div class="card" style="padding: 20px;">
                    
                    <h3 style="border-bottom: 2px solid var(--cor-borda); padding-bottom: 5px; margin-bottom: 15px;">Destaques de Duração ⏱️</h3>
                    
                    <?php if ($stats['faixas']['total_faixas'] > 0): ?>
                        <h4 style="margin-top: 15px; border-bottom: 1px dashed var(--cor-borda); padding-bottom: 5px; color: var(--cor-texto-principal);">Músicas Extremas</h4>
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

                        <h4 style="margin-top: 25px; border-bottom: 1px dashed var(--cor-borda); padding-bottom: 5px; color: var(--cor-texto-principal);">Álbuns Extremos</h4>
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
                    <?php else: ?>
                        <p style="color: var(--cor-texto-secundario);">Nenhum dado de faixas registrado para calcular durações.</p>
                    <?php endif; ?>
                    
                </div>
            </div>
            
            <div class="stats-detail-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; margin-top: 40px;">

                <div class="card" style="padding: 20px;">
                    <h3 style="border-bottom: 2px solid var(--cor-borda); padding-bottom: 5px; margin-bottom: 15px;">Top 5 Artistas (por Álbum)</h3>
                    <ol style="padding-left: 20px; color: var(--cor-texto-principal);">
                        <?php if (empty($stats['top_artistas'])): ?>
                            <li>Nenhum artista registrado.</li>
                        <?php else: ?>
                            <?php foreach ($stats['top_artistas'] as $artista): ?>
                                <li><?php echo htmlspecialchars($artista['nome']); ?> (<span style="color: var(--cor-destaque);"><?php echo $artista['total']; ?></span> álbuns)</li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ol>
                </div>

                <div class="card" style="padding: 20px;">
                    <h3 style="border-bottom: 2px solid var(--cor-borda); padding-bottom: 5px; margin-bottom: 15px;">Top 5 Artistas (por Faixa) 🎶</h3>
                    <ol style="padding-left: 20px; color: var(--cor-texto-principal);">
                        <?php if (empty($stats['top_artistas_faixas'])): ?>
                            <li>Nenhum artista com faixas registradas.</li>
                        <?php else: ?>
                            <?php foreach ($stats['top_artistas_faixas'] as $artista): ?>
                                <li><?php echo htmlspecialchars($artista['nome']); ?> (<span style="color: #28a745;"><?php echo $artista['total']; ?></span> faixas)</li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ol>
                </div>
                
                <div class="card" style="padding: 20px;">
                    <h3 style="border-bottom: 2px solid var(--cor-borda); padding-bottom: 5px; margin-bottom: 15px;">Top 5 Gravadoras</h3>
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

            <div class="stats-detail-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 30px; margin-top: 40px;">

                <div class="card" style="padding: 20px;">
                    <h3 style="border-bottom: 2px solid var(--cor-borda); padding-bottom: 5px; margin-bottom: 15px;">Top 5 Gêneros</h3>
                    <ol style="padding-left: 20px; color: var(--cor-texto-principal);">
                        <?php if (empty($stats['top_generos'])): ?>
                            <li>Nenhum gênero registrado.</li>
                        <?php else: ?>
                            <?php foreach ($stats['top_generos'] as $genero): ?>
                                <li><?php echo htmlspecialchars($genero['nome']); ?> (<span style="color: var(--cor-destaque);"><?php echo $genero['total']; ?></span> itens)</li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ol>
                </div>

                <div class="card" style="padding: 20px;">
                    <h3 style="border-bottom: 2px solid var(--cor-borda); padding-bottom: 5px; margin-bottom: 15px;">Top 5 Estilos</h3>
                    <ol style="padding-left: 20px; color: var(--cor-texto-principal);">
                        <?php if (empty($stats['top_estilos'])): ?>
                            <li>Nenhum estilo registrado.</li>
                        <?php else: ?>
                            <?php foreach ($stats['top_estilos'] as $estilo): ?>
                                <li><?php echo htmlspecialchars($estilo['nome']); ?> (<span style="color: var(--cor-destaque);"><?php echo $estilo['total']; ?></span> itens)</li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ol>
                </div>
            </div>
            
        </div>
    </div>
</div>

<?php require_once 'include/footer.php'; ?>