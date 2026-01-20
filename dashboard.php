<?php
// Arquivo: dashboard.php (Controller e View - INTEGRADO COM MODAL)

session_start();

// 1. CARREGAR O AMBIENTE E VERIFICAR LOGIN
require_once 'src/config/config.php';
/** @var PDO $pdo */
require_once 'src/Model/AlbumModel.php'; 

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php'); 
    exit();
}

// 2. LÓGICA (Controller) - BUSCA DE DADOS
$userId = $_SESSION['user_id']; 
$albumModel = new AlbumModel($pdo); 
$stats = $albumModel->getDashboardStats($userId);

$erro_db = $stats['erro_db'] ?? '';
$total_albuns = $stats['count_total'] ?? 0;
$total_lps = $stats['count_lp'] ?? 0;
$total_cds = $stats['count_cd'] ?? 0;
$total_cdrs = $stats['count_cdr'] ?? 0;
$total_k7 = $stats['count_k7'] ?? 0;
$total_digital = $stats['count_digital'] ?? 0;
$total_video = ($stats['count_dvd'] ?? 0) + ($stats['count_bluray'] ?? 0);
$total_artistas = $stats['count_artistas'] ?? 0;
$total_generos = $stats['total_generos'] ?? 0;
$total_gravadoras = $stats['total_gravadoras'] ?? 0;

$ano_min = $stats['min_year'] ?? date('Y');
$ano_max = $stats['max_year'] ?? date('Y');
$anos_cobertos = $stats['years_span'] ?? 1;

$aniversariantes = $stats['aniversariantes'] ?? [];
$ultimos_albuns = $stats['ultimos_albuns'] ?? [];

require_once 'include/header.php'; 
?>

<?php if (!empty($erro_db)): ?>
    <div class="alerta container" style="margin-top: 85px; background-color: #dc3545; color: white; padding: 15px; border-radius: 5px;"><?php echo $erro_db; ?></div>
<?php endif; ?>

<div class="dashboard-header-section container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div class="dashboard-title">
            <span class="dashboard-album-count"><?php echo $total_albuns; ?> Álbuns na Coleção</span>
        </div>
    </div>
</div>

<div class="metric-grid container" style="padding-top: 0px;">
    <a href="colecao/colecao.php" style="text-decoration: none; color: inherit;">
        <div class="card metric-card"><div class="metric-card-content"><div><div class="metric-value"><?php echo $total_albuns; ?></div><div class="metric-label">Álbuns</div></div><div class="icon-container cor-1"><i class="fas fa-compact-disc"></i></div></div></div>
    </a>
    <a href="colecao/colecao.php?formato=LP" style="text-decoration: none; color: inherit;">
        <div class="card metric-card"><div class="metric-card-content"><div><div class="metric-value"><?php echo $total_lps; ?></div><div class="metric-label">LPs (Vinyl)</div></div><div class="icon-container cor-2"><i class="fas fa-record-vinyl"></i></div></div></div>
    </a>
    <a href="colecao/colecao.php?formato=CD" style="text-decoration: none; color: inherit;">
        <div class="card metric-card"><div class="metric-card-content"><div><div class="metric-value"><?php echo $total_cds; ?></div><div class="metric-label">CD's</div></div><div class="icon-container cor-3"><i class="fas fa-compact-disc"></i></div></div></div>
    </a>
    <a href="colecao/colecao.php?formato=CD-R" style="text-decoration: none; color: inherit;">
        <div class="card metric-card"><div class="metric-card-content"><div><div class="metric-value"><?php echo $total_cdrs; ?></div><div class="metric-label">CD-R's</div></div><div class="icon-container cor-7"><i class="fas fa-compact-disc"></i></div></div></div>
    </a>
    <div class="card metric-card"><div class="metric-card-content"><div><div class="metric-value"><?php echo $total_artistas; ?></div><div class="metric-label">Artistas</div></div><div class="icon-container cor-5"><i class="fas fa-users"></i></div></div></div>
    <div class="card metric-card"><div class="metric-card-content"><div><div class="metric-value"><?php echo $total_gravadoras; ?></div><div class="metric-label">Gravadoras</div></div><div class="icon-container cor-6"><i class="fas fa-building"></i></div></div></div>
</div>

<div class="span-card-container container" style="padding-top: 0px;">
    <div class="span-card card">
        <div class="span-details">
            <i class="fas fa-calendar-alt"></i> 
            <div>
                <div class="span-title">Abrangência da Coleção</div>
                <div class="span-years-range">Lançamentos entre <?php echo $ano_min; ?> e <?php echo $ano_max; ?></div>
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
            <h2 class="anniversary-title"><i class="fas fa-calendar-alt"></i> Aniversariantes de Hoje</h2>
        </div>
        <div class="card-content space-y-4">
            <?php foreach ($aniversariantes as $album): ?>
                <div class="anniversary-album-item open-modal" data-album-id="<?php echo $album['id']; ?>" data-ativo="1" style="cursor:pointer;">
                    <div class="album-cover-sm">
                        <?php if ($album['capa_url']): ?>
                            <img src="<?php echo htmlspecialchars($album['capa_url']); ?>" alt="Capa" class="w-full h-full object-cover rounded-lg">
                        <?php else: ?>
                            <i class="fas fa-music text-white"></i>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="font-semibold text-white truncate"><?php echo htmlspecialchars($album['titulo']); ?></h4>
                        <p class="text-sm text-gray-400 truncate"><?php echo htmlspecialchars($album['artista_nome']); ?></p>
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
                </div>
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
                <div class="card album-card-modern group open-modal" 
                     data-album-id="<?php echo $album['id']; ?>" 
                     data-ativo="1" 
                     style="cursor: pointer;">
                    
                    <button type="button" class="btn-edit-album" title="Editar Álbum" onclick="event.stopPropagation(); window.location.href='/colecao/editar_colecao.php?id=<?php echo $album['id']; ?>';">
                        <i class="fas fa-edit"></i> 
                    </button>

                    <div class="album-cover-area">
                        <?php if ($album['capa_url']): ?>
                            <img src="<?php echo htmlspecialchars($album['capa_url']); ?>" alt="Capa" class="album-image">
                        <?php else: ?>
                            <div class="album-placeholder"><i class="fas fa-music"></i></div>
                        <?php endif; ?>
                        
                        <span class="album-format-tag <?php 
                            $formato = strtolower($album['formato_descricao'] ?? '');
                            if (str_contains($formato, 'lp') || str_contains($formato, 'vinyl')) echo 'tag-vinyl'; 
                            elseif (str_contains($formato, 'cd-r')) echo 'tag-cdr'; 
                            elseif (str_contains($formato, 'digital')) echo 'tag-dig'; 
                            else echo 'tag-cd'; 
                        ?>">
                            <?php echo htmlspecialchars($album['formato_descricao'] ?? '-'); ?>
                        </span>
                    </div>
                    
                    <div class="album-details-content">
                        <h3 class="album-title-h3" title="<?php echo htmlspecialchars($album['titulo']); ?>">
                            <?php echo htmlspecialchars($album['titulo']); ?>
                        </h3>
                        <div class="album-info-line artist-line">
                            <i class="fas fa-user"></i> <span><?php echo htmlspecialchars($album['artista_nome'] ?? 'Artista Desconhecido'); ?></span>
                        </div>
                        <div class="album-info-line label-line">
                            <i class="fas fa-building"></i> <span><?php echo htmlspecialchars($album['gravadora_nome'] ?? '-'); ?></span>
                        </div>
                        <div class="album-info-line year-genre-line">
                            <div class="flex items-center">
                                <i class="fas fa-calendar-alt"></i> <span><?php echo htmlspecialchars($album['ano_lancamento']); ?></span>
                            </div>
                            <span class="genre-tag"><?php echo htmlspecialchars($album['genero_nome'] ?? 'Não Definido'); ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="no-albums-message">Ainda não há álbuns na coleção. <a href="/colecao/adicionar_colecao.php">Adicione o seu primeiro!</a></p>
    <?php endif; ?>
</div>

<div id="albumModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <span class="modal-close">&times;</span>
        <div id="modal-loader" style="display: none; text-align: center; padding: 50px;">
            <i class="fas fa-circle-notch fa-spin" style="font-size: 3em; color: var(--cor-destaque);"></i>
        </div>
        <div id="modal-details" style="display: none;">
            <div class="modal-grid" style="display: flex; gap: 20px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 250px;">
                    <img id="modal-capa-img" src="" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.5);">
                    <div id="modal-relacionamentos" style="margin-top: 15px; font-size: 0.9em;"></div>
                </div>
                <div style="flex: 2; min-width: 300px;">
                    <h2 id="modal-titulo" style="color: var(--cor-destaque); margin-bottom: 5px;"></h2>
                    <p id="modal-artistas" style="font-size: 1.2em; font-weight: bold; margin-bottom: 15px;"></p>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 0.9em; background: rgba(255,255,255,0.05); padding: 10px; border-radius: 5px;">
                        <span><strong>Lançamento:</strong> <span id="modal-lancamento"></span></span>
                        <span><strong>Gravadora:</strong> <span id="modal-gravadora"></span></span>
                        <span><strong>Catálogo:</strong> <span id="modal-catalogo"></span></span>
                        <span><strong>Formato:</strong> <span id="modal-formato"></span></span>
                        <span><strong>Preço:</strong> <span id="modal-preco"></span></span>
                        <span><strong>Condição:</strong> <span id="modal-condicao"></span></span>
                    </div>
                    <div style="margin-top: 20px;">
                        <h3 style="border-bottom: 1px solid var(--cor-borda); padding-bottom: 5px;">Faixas</h3>
                        <div id="import-message-area">
                            <ul id="tracklist-ul" style="list-style: none; padding: 0; margin-top: 10px;"></ul>
                        </div>
                        <div id="manual-edit-controls" style="display:none;"></div>
                    </div>
                </div>
            </div>
            <div id="modal-actions" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--cor-borda); text-align: right;"></div>
        </div>
    </div>
</div>

<?php require_once 'include/footer.php'; ?>