<?php
// Arquivo: dashboard.php
require_once 'conexao.php'; // Garante a conexão PDO

$total_albuns = 0;
$total_cds = 0;
$total_lps = 0;
$total_artistas = 0;
$total_generos = 0;
$total_gravadoras = 0;
$ano_min = date('Y');
$ano_max = date('Y');
$anos_cobertos = 0;
$ultimos_albuns = [];

try {
    // ----------------------------------------------------
    // 1. QUERY DE MÉTRICAS SIMPLES (6 Cards)
    // ----------------------------------------------------
    
    // Total Geral
    $total_albuns = $pdo->query("SELECT COUNT(id) FROM colecao WHERE ativo = 1")->fetchColumn();

    // Total por Formato (Assumindo que 'CD' e 'LP' estão em formatos.descricao)
    $stmt_formatos = $pdo->prepare("
        SELECT f.descricao, COUNT(c.id) as total
        FROM colecao c
        JOIN formatos f ON c.formato_id = f.id
        WHERE c.ativo = 1
        GROUP BY f.descricao
    ");
    $stmt_formatos->execute();
    $formatos = $stmt_formatos->fetchAll(PDO::FETCH_KEY_PAIR);
    $total_cds = $formatos['CD'] ?? 0;
    $total_lps = $formatos['LP'] ?? 0;

    // Total de Entidades Únicas (Assumindo que temos as tabelas generos, artistas e gravadoras)
    $total_artistas = $pdo->query("SELECT COUNT(DISTINCT artista_id) FROM colecao WHERE ativo = 1")->fetchColumn();
    $total_generos = $pdo->query("SELECT COUNT(DISTINCT genero_id) FROM colecao WHERE ativo = 1")->fetchColumn();
    $total_gravadoras = $pdo->query("SELECT COUNT(DISTINCT gravadora_id) FROM colecao WHERE ativo = 1")->fetchColumn();

    // ----------------------------------------------------
    // 2. QUERY DE ABRANGÊNCIA DA COLEÇÃO (Span Card)
    // ----------------------------------------------------
    $stmt_span = $pdo->query("
        SELECT 
            MIN(YEAR(data_lancamento)) AS min_year, 
            MAX(YEAR(data_lancamento)) AS max_year
        FROM colecao 
        WHERE ativo = 1
    ")->fetch(PDO::FETCH_ASSOC);

    if ($stmt_span && $stmt_span['min_year'] && $stmt_span['max_year']) {
        $ano_min = $stmt_span['min_year'];
        $ano_max = $stmt_span['max_year'];
        $anos_cobertos = $ano_max - $ano_min + 1; // +1 para incluir o ano inicial
    }

    // ----------------------------------------------------
    // 3. QUERY DOS ÚLTIMOS ÁLBUNS INCLUÍDOS (5 Cards)
    // ----------------------------------------------------
    $stmt_recentes = $pdo->prepare("
        SELECT 
            c.id,
            c.titulo, 
            c.capa_url, 
            c.numero_catalogo, 
            YEAR(c.data_lancamento) AS ano_lancamento, 
            r.nome AS gravadora_nome, 
            g.nome AS genero_nome,
            a.nome AS artista_nome
        FROM colecao c
        LEFT JOIN gravadoras r ON c.gravadora_id = r.id
        LEFT JOIN generos g ON c.genero_id = g.id
        LEFT JOIN artistas a ON c.artista_id = a.id
        WHERE c.ativo = 1
        ORDER BY c.data_aquisicao DESC
        LIMIT 5
    ");
    $stmt_recentes->execute();
    $ultimos_albuns = $stmt_recentes->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Em caso de erro, definimos as variáveis como 0 ou array vazio
    $erro_db = "Erro ao buscar dados do Dashboard: " . $e->getMessage();
}

// Inclui o Cabeçalho (que já tem o logotipo e a navegação)
require_once 'header.php'; 
?>

<div class="container">
    
    <div class="header-welcome-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div style="font-size: 1.8em; font-weight: 300; color: #333;">
                SoundHaven | <span style="font-weight: 600;"><?php echo $total_albuns; ?> Álbuns na Coleção</span>
            </div>
            <a href="adicionar_colecao.php" class="btn-adicionar-album">
                <i class="fas fa-plus-circle"></i> Adicionar Álbum
            </a>
        </div>
    </div>

    <div class="metric-grid">
        
        <div class="card">
            <div class="metric-value"><?php echo $total_albuns; ?></div>
            <div class="metric-label">Total de Álbuns</div>
        </div>

        <div class="card">
            <div class="metric-value"><?php echo $total_cds; ?></div>
            <div class="metric-label">Total de CDs</div>
        </div>
        
        <div class="card">
            <div class="metric-value"><?php echo $total_lps; ?></div>
            <div class="metric-label">Total de LPs</div>
        </div>
        
        <div class="card">
            <div class="metric-value"><?php echo $total_artistas; ?></div>
            <div class="metric-label">Artistas Únicos</div>
        </div>
        
        <div class="card">
            <div class="metric-value"><?php echo $total_generos; ?></div>
            <div class="metric-label">Gêneros Únicos</div>
        </div>
        
        <div class="card">
            <div class="metric-value"><?php echo $total_gravadoras; ?></div>
            <div class="metric-label">Gravadoras</div>
        </div>
    </div>

    <div class="span-card">
        <div class="span-details">
            <i class="fas fa-calendar-alt"></i> <div>
                <div style="font-weight: 600; font-size: 1.1em;">Abrangência da Coleção</div>
                <div style="color: #888;">De <?php echo $ano_min; ?> a <?php echo $ano_max; ?></div>
            </div>
        </div>
        
        <div class="span-value">
            <div class="years"><?php echo $anos_cobertos; ?></div>
            <div class="label">Anos Cobertos</div>
        </div>
    </div>
    
    <h2 style="margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px;">Últimas 5 Aquisições</h2>

    <?php if (!empty($ultimos_albuns)): ?>
        <div class="recent-albums-grid">
            <?php foreach ($ultimos_albuns as $album): ?>
                <div class="card album-card">
                    <img src="<?php echo htmlspecialchars($album['capa_url'] ?? 'placeholder.png'); ?>" alt="Capa do Álbum <?php echo htmlspecialchars($album['titulo']); ?>">
                    
                    <p class="album-title" title="<?php echo htmlspecialchars($album['titulo']); ?>">
                        <?php echo htmlspecialchars($album['titulo']); ?>
                    </p>
                    <p class="album-artist">
                        <?php echo htmlspecialchars($album['artista_nome'] ?? 'Artista Desconhecido'); ?>
                    </p>
                    <div class="album-details">
                        <p>Gravadora: <?php echo htmlspecialchars($album['gravadora_nome'] ?? '-'); ?></p>
                        
                        <p><?php echo htmlspecialchars($album['ano_lancamento']); ?> - <?php echo htmlspecialchars($album['genero_nome'] ?? 'Não Definido'); ?></p>
                        
                        <p>Catálogo: <?php echo htmlspecialchars($album['numero_catalogo'] ?? 'S/N'); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>Ainda não há álbuns na coleção para exibir no dashboard. <a href="adicionar_colecao.php">Adicione o seu primeiro!</a></p>
    <?php endif; ?>

</div>

<?php require_once 'footer.php'; ?>