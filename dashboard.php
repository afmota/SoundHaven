<?php
// Arquivo: dashboard.php

// A primeira coisa é carregar a conexão com o banco de dados
require_once 'db/conexao.php'; 

// ----------------------------------------------------
// 0. DEFINIÇÃO DE VARIÁVEIS E BUSCA DE DADOS
// ----------------------------------------------------

$total_albuns = 0;
// ... (outras variáveis)
$erro_db = '';

try {
    // ... (querys 1, 2, 3 e 4 continuam as mesmas)

    // Contagem Total Geral
    $total_albuns = $pdo->query("SELECT COUNT(id) FROM colecao WHERE ativo = 1")->fetchColumn();

    // Contagem de Artistas Únicos (N:M)
    $total_artistas = $pdo->query("
        SELECT COUNT(DISTINCT ca.artista_id) 
        FROM colecao_artista ca
        JOIN colecao c ON ca.colecao_id = c.id
        WHERE c.ativo = 1
    ")->fetchColumn();
    
    // Contagem de Gêneros Únicos (N:M)
    $total_generos = $pdo->query("
        SELECT COUNT(DISTINCT cg.genero_id) 
        FROM colecao_genero cg
        JOIN colecao c ON cg.colecao_id = c.id
        WHERE c.ativo = 1
    ")->fetchColumn();
    
    // Contagem de Gravadoras (1:N)
    $total_gravadoras = $pdo->query("SELECT COUNT(DISTINCT gravadora_id) FROM colecao WHERE ativo = 1 AND gravadora_id IS NOT NULL")->fetchColumn();

    // Contagem por Formato
    $stmt_formatos = $pdo->prepare("
        SELECT f.descricao AS formato_descricao, COUNT(c.id) as total
        FROM colecao c
        JOIN formatos f ON c.formato_id = f.id
        WHERE c.ativo = 1
        GROUP BY f.descricao
    ");
    $stmt_formatos->execute();
    $formatos = $stmt_formatos->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Busca por variações de CD/LP/Vinyl para maior compatibilidade
    $total_cds = ($formatos['CD'] ?? 0) + ($formatos['cd'] ?? 0); 
    $total_lps = ($formatos['LP'] ?? 0) + ($formatos['lp'] ?? 0) + ($formatos['Vinyl'] ?? 0) + ($formatos['vinyl'] ?? 0); 

    // 2. QUERY DE ABRANGÊNCIA
    $stmt_span = $pdo->query("
        SELECT 
            MIN(YEAR(data_lancamento)) AS min_year, 
            MAX(YEAR(data_lancamento)) AS max_year
        FROM colecao 
        WHERE ativo = 1 AND data_lancamento IS NOT NULL
    ")->fetch(PDO::FETCH_ASSOC);

    if ($stmt_span && $stmt_span['min_year'] && $stmt_span['max_year']) {
        $ano_min = $stmt_span['min_year'];
        $ano_max = $stmt_span['max_year'];
        $anos_cobertos = $ano_max - $ano_min + 1;
    }

    // 3. QUERY DE ANIVERSÁRIOS DO DIA (CORRIGIDA NA INTERAÇÃO ANTERIOR)
    $hoje_mes_dia = date('m-d'); 
    $ano_atual = date('Y');

    $stmt_aniversariantes = $pdo->prepare("
        SELECT 
            c.id, c.titulo, c.capa_url, 
            YEAR(c.data_lancamento) AS ano_lancamento, YEAR(c.data_aquisicao) AS ano_aquisicao,
            c.data_lancamento AS data_lancamento_completa, c.data_aquisicao AS data_aquisicao_completa
        FROM colecao c
        WHERE c.ativo = 1
          AND (
            DATE_FORMAT(c.data_lancamento, '%m-%d') = :hoje_mes_dia_lancamento 
            OR 
            DATE_FORMAT(c.data_aquisicao, '%m-%d') = :hoje_mes_dia_aquisicao
          )
    ");
    $stmt_aniversariantes->execute([
        ':hoje_mes_dia_lancamento' => $hoje_mes_dia,
        ':hoje_mes_dia_aquisicao' => $hoje_mes_dia
    ]);
    $albuns_aniversariantes = $stmt_aniversariantes->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($albuns_aniversariantes)) {
        $ids_aniversariantes = implode(',', array_column($albuns_aniversariantes, 'id'));
        $stmt_artistas_aniversariantes = $pdo->prepare("
            SELECT ca.colecao_id, a.nome AS nome FROM colecao_artista ca
            JOIN artistas a ON ca.artista_id = a.id
            WHERE ca.colecao_id IN ($ids_aniversariantes)
        ");
        $stmt_artistas_aniversariantes->execute();
        $artistas_map_aniversariantes = [];
        while ($row = $stmt_artistas_aniversariantes->fetch(PDO::FETCH_ASSOC)) {
            $artistas_map_aniversariantes[$row['colecao_id']][] = $row['nome'];
        }
        
        foreach ($albuns_aniversariantes as $album) {
            $id = $album['id'];
            $album['artista_nome'] = isset($artistas_map_aniversariantes[$id]) ? implode(', ', $artistas_map_aniversariantes[$id]) : 'Artista Desconhecido';
            $info = [];
            if ($album['data_lancamento_completa'] && date('m-d', strtotime($album['data_lancamento_completa'])) == $hoje_mes_dia) {
                $anos = $ano_atual - $album['ano_lancamento'];
                if ($anos > 0) { $info[] = ['type' => 'release', 'years' => $anos, 'text' => "{$anos} anos desde o lançamento"]; }
            }
            if ($album['data_aquisicao_completa'] && date('m-d', strtotime($album['data_aquisicao_completa'])) == $hoje_mes_dia) {
                $anos = $ano_atual - $album['ano_aquisicao'];
                if ($anos > 0) { $info[] = ['type' => 'acquisition', 'years' => $anos, 'text' => "{$anos} anos na coleção"]; }
            }
            if (!empty($info)) { $album['aniversario_info'] = $info; $aniversariantes[] = $album; }
        }
    }

    // 4. QUERY DOS ÚLTIMOS ÁLBUNS
    $stmt_ids = $pdo->prepare("
        SELECT id FROM colecao WHERE ativo = 1 ORDER BY data_aquisicao DESC LIMIT 5
    ");
    $stmt_ids->execute();
    $recent_ids = $stmt_ids->fetchAll(PDO::FETCH_COLUMN);
    
    
    if (!empty($recent_ids)) {
        $in_clause = implode(',', $recent_ids);
        $stmt_albuns = $pdo->prepare("
            SELECT c.id, c.titulo, c.capa_url, c.numero_catalogo, YEAR(c.data_lancamento) AS ano_lancamento, 
                   r.nome AS gravadora_nome, f.descricao AS formato_descricao 
            FROM colecao c
            LEFT JOIN gravadoras r ON c.gravadora_id = r.id
            LEFT JOIN formatos f ON c.formato_id = f.id 
            WHERE c.id IN ($in_clause)
            ORDER BY c.data_aquisicao DESC
        ");
        $stmt_albuns->execute();
        $albuns_detalhes = $stmt_albuns->fetchAll(PDO::FETCH_ASSOC);

        $stmt_artistas = $pdo->prepare("
            SELECT ca.colecao_id, a.nome AS nome FROM colecao_artista ca JOIN artistas a ON ca.artista_id = a.id WHERE ca.colecao_id IN ($in_clause)
        ");
        $stmt_artistas->execute();
        $artistas_map = [];
        while ($row = $stmt_artistas->fetch(PDO::FETCH_ASSOC)) { $artistas_map[$row['colecao_id']][] = $row['nome']; }

        $stmt_generos = $pdo->prepare("
            SELECT cg.colecao_id, g.descricao AS nome FROM colecao_genero cg JOIN generos g ON cg.genero_id = g.id WHERE cg.colecao_id IN ($in_clause)
        ");
        $stmt_generos->execute();
        $generos_map = [];
        while ($row = $stmt_generos->fetch(PDO::FETCH_ASSOC)) { $generos_map[$row['colecao_id']][] = $row['nome']; }

        foreach ($albuns_detalhes as $album) {
            $id = $album['id'];
            $album['artista_nome'] = isset($artistas_map[$id]) ? implode(', ', $artistas_map[$id]) : 'Artista Desconhecido';
            $album['genero_nome'] = isset($generos_map[$id]) ? implode(', ', $generos_map[$id]) : 'Não Definido';
            $ultimos_albuns[] = $album;
        }
    }


} catch (PDOException $e) {
    $erro_db = "Erro ao buscar dados do Dashboard: " . $e->getMessage();
}

// Inclui o Cabeçalho
require_once 'include/header.php'; 
?>

<?php if (!empty($erro_db)): ?>
    <div class="alerta container" style="margin-top: 85px;"><?php echo $erro_db; ?></div>
<?php endif; ?>

<div class="dashboard-header-section container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div class="dashboard-title">
            <span class="dashboard-album-count"><?php echo $total_albuns; ?> Álbuns na Coleção</span>
        </div>
        </div>
</div>

<div class="metric-grid container" style="padding-top: 0px;">
    <div class="card metric-card"><div class="metric-card-content"><div><div class="metric-value"><?php echo $total_albuns; ?></div><div class="metric-label">Total de Álbuns</div></div><div class="icon-container cor-1"><i class="fas fa-compact-disc"></i></div></div></div>
    <div class="card metric-card"><div class="metric-card-content"><div><div class="metric-value"><?php echo $total_lps; ?></div><div class="metric-label">LPs (Vinyl)</div></div><div class="icon-container cor-2"><i class="fas fa-record-vinyl"></i></div></div></div>
    <div class="card metric-card"><div class="metric-card-content"><div><div class="metric-value"><?php echo $total_cds; ?></div><div class="metric-label">CDs</div></div><div class="icon-container cor-3"><i class="fas fa-compact-disc"></i></div></div></div>
    <div class="card metric-card"><div class="metric-card-content"><div><div class="metric-value"><?php echo $total_generos; ?></div><div class="metric-label">Gêneros Únicos</div></div><div class="icon-container cor-4"><i class="fas fa-chart-line"></i></div></div></div>
    <div class="card metric-card"><div class="metric-card-content"><div><div class="metric-value"><?php echo $total_artistas; ?></div><div class="metric-label">Artistas Únicos</div></div><div class="icon-container cor-5"><i class="fas fa-users"></i></div></div></div>
    <div class="card metric-card"><div class="metric-card-content"><div><div class="metric-value"><?php echo $total_gravadoras; ?></div><div class="metric-label">Gravadoras</div></div><div class="icon-container cor-6"><i class="fas fa-building"></i></div></div></div>
</div>

<div class="span-card-container container" style="padding-top: 0px;">
    <div class="span-card">
        <div class="span-details">
            <i class="fas fa-calendar-alt"></i> 
            <div>
                <div class="span-title">Abrangência da Coleção</div>
                <div class="span-years-range">De <?php echo $ano_min; ?> a <?php echo $ano_max; ?></div>
            </div>
        </div>
        
        <div class="span-value-area">
            <div class="years-value"><?php echo $anos_cobertos; ?></div>
            <div class="years-label">Anos Cobertos</div>
        </div>
    </div>
</div>

<?php if (!empty($aniversariantes)): ?>
<div class="anniversary-section container" style="padding-top: 10px;">
    <div class="card anniversary-card">
        <div class="card-header">
            <h2 class="anniversary-title">
                <i class="fas fa-calendar-alt"></i> Aniversariantes de Hoje
            </h2>
        </div>
        <div class="card-content space-y-4">
            <?php foreach ($aniversariantes as $album): ?>
                <a href="/colecao/detalhes_colecao.php?id=<?php echo $album['id']; ?>" class="anniversary-album-item">
                    <div class="album-cover-sm">
                        <?php if ($album['capa_url']): ?>
                            <img src="<?php echo htmlspecialchars($album['capa_url']); ?>" alt="Capa" class="w-full h-full object-cover rounded-lg">
                        <?php else: ?>
                            <i class="fas fa-music text-white"></i>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="font-semibold text-white truncate"><?php echo htmlspecialchars($album['titulo']); ?></h4>
                        <p class="text-sm text-gray-400 truncate">
                            <?php echo htmlspecialchars($album['artista_nome']); ?>
                        </p>
                        <div class="flex flex-wrap gap-3 mt-1">
                            <?php foreach ($album['aniversario_info'] as $info): 
                                $icone = ($info['type'] === 'release') ? 'fas fa-compact-disc' : 'fas fa-gift'; 
                            ?>
                                <div class="anniversary-info-tag">
                                    <i class="<?php echo $icone; ?>"></i>
                                    <span><?php echo htmlspecialchars($info['text']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>


<div class="recent-albums-section container" style="padding-top: 0px;">
    <h2 class="recent-albums-title">Últimas Aquisições</h2>

    <?php if (!empty($ultimos_albuns)): ?>
        <div class="recent-albums-grid">
            <?php foreach ($ultimos_albuns as $album): ?>
                
                <a href="/colecao/detalhes_colecao.php?id=<?php echo $album['id']; ?>" 
                   class="card album-card-modern group">
                    
                    <button 
                        type="button" 
                        class="btn-edit-album"
                        title="Editar Álbum"
                        onclick="event.stopPropagation(); window.location.href='/colecao/editar_colecao.php?id=<?php echo $album['id']; ?>';"
                    >
                        <i class="fas fa-edit"></i> </button>

                    <div class="album-cover-area">
                        <?php if ($album['capa_url']): ?>
                            <img 
                                src="<?php echo htmlspecialchars($album['capa_url']); ?>" 
                                alt="Capa do Álbum <?php echo htmlspecialchars($album['titulo']); ?>"
                                class="album-image"
                            >
                        <?php else: ?>
                            <div class="album-placeholder">
                                <i class="fas fa-music"></i>
                            </div>
                        <?php endif; ?>
                        
                        <span class="album-format-tag <?php 
                            $formato = strtolower($album['formato_descricao'] ?? '');
                            echo (str_contains($formato, 'lp') || str_contains($formato, 'vinyl')) 
                                ? 'tag-vinyl' : 'tag-cd'; 
                        ?>">
                            <?php echo htmlspecialchars($album['formato_descricao'] ?? '-'); ?>
                        </span>
                    </div>
                    
                    <div class="album-details-content">
                        <h3 class="album-title-h3" title="<?php echo htmlspecialchars($album['titulo']); ?>">
                            <?php echo htmlspecialchars($album['titulo']); ?>
                        </h3>
                        
                        <div class="album-info-line artist-line">
                            <i class="fas fa-user"></i> 
                            <span title="<?php echo htmlspecialchars($album['artista_nome'] ?? 'Artista Desconhecido'); ?>">
                                <?php echo htmlspecialchars($album['artista_nome'] ?? 'Artista Desconhecido'); ?>
                            </span>
                        </div>
                        
                        <div class="album-info-line label-line">
                            <i class="fas fa-building"></i>
                            <span title="<?php echo htmlspecialchars($album['gravadora_nome'] ?? '-'); ?>">
                                <?php echo htmlspecialchars($album['gravadora_nome'] ?? '-'); ?>
                            </span>
                        </div>
                        
                        <div class="album-info-line year-genre-line">
                            <div class="flex items-center">
                                <i class="fas fa-calendar-alt"></i>
                                <span><?php echo htmlspecialchars($album['ano_lancamento']); ?></span>
                            </div>
                            <span class="genre-tag">
                                <?php echo htmlspecialchars($album['genero_nome'] ?? 'Não Definido'); ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($album['numero_catalogo'])): ?>
                            <div class="album-info-line catalog-line">
                                <i class="fas fa-compact-disc"></i>
                                <span><?php echo htmlspecialchars($album['numero_catalogo']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="no-albums-message">Ainda não há álbuns na coleção para exibir no dashboard. <a href="/colecao/adicionar_colecao.php">Adicione o seu primeiro!</a></p>
    <?php endif; ?>

</div> 

<?php require_once 'include/footer.php'; ?>