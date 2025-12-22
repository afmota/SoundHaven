<?php
// Arquivo: views/store/index.php
require_once '../include/header.php'; 
?>

<div class="container" style="padding-top: 100px;">
    <div class="main-layout" style="display: flex; gap: 20px; align-items: flex-start;"> 
        
        <main class="content-area" style="flex: 1;">
            <div class="page-header-actions">
                <h1>Catálogo (Total: <?= $total_registros ?>)</h1>
                <div style="display: flex; gap: 10px;"> 
                    <a href="importar_store.php" class="btn-action primary-action">
                        <i class="fas fa-file-upload"></i> Importar em Lote
                    </a>
                    <a href="adicionar_album.php" class="btn-action primary-action">
                        <i class="fas fa-plus-circle"></i> Adicionar Álbum
                    </a>
                </div>
            </div>

            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] == 'editado'): ?>
                    <p class="sucesso">Álbum "<?= htmlspecialchars($_GET['album'] ?? 'N/D') ?>" atualizado com sucesso!</p>
                <?php elseif ($_GET['status'] == 'criado'): ?>
                    <p class="sucesso">Álbum "<?= htmlspecialchars($_GET['album'] ?? 'N/D') ?>" adicionado com sucesso!</p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (empty($albuns)): ?>
                <p class="alerta">Nenhum álbum encontrado com os filtros selecionados.</p>
            <?php else: ?>
                
                <div class="store-grid-container" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 20px;">
<?php foreach ($albuns as $album): ?>
    <div class="album-card" 
         onclick="abrirModalDetalhes(<?= $album['id'] ?>)"
         style="cursor: pointer; background-color: var(--cor-fundo-card); border: 1px solid var(--cor-borda); border-radius: 8px; overflow: hidden; box-shadow: 0 4px 8px var(--sombra-card); transition: transform 0.2s; <?= ($album['deletado'] == 1) ? 'opacity: 0.5; filter: grayscale(100%);' : ''; ?>" 
         onmouseover="this.style.transform='translateY(-5px)'" 
         onmouseout="this.style.transform='translateY(0)'">
        
        <div class="album-cover-wrapper" style="width: 100%; aspect-ratio: 1 / 1; overflow: hidden; background-color: #333;">
            <?php if (!empty($album['capa_url'])): ?>
                <img src="<?= htmlspecialchars($album['capa_url']) ?>" alt="Capa" style="width: 100%; height: 100%; object-fit: cover;">
            <?php else: ?>
                <div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; color: #aaa;">
                    <i class="fas fa-compact-disc fa-3x"></i>
                </div>
            <?php endif; ?>
        </div>

        <div class="card-details" style="padding: 10px;">
            <h4 style="margin: 0 0 5px 0; font-size: 1em; height: 2.8em; overflow: hidden; color: var(--cor-texto-principal); font-weight: bold;">
                <?= htmlspecialchars(limitar_texto($album['titulo'], 40)) ?>
            </h4>
            <p style="margin: 0 0 5px 0; font-size: 0.85em; color: var(--cor-texto-secundario);">
                <?= htmlspecialchars($album['nome_artista']) ?> (<?= $album['ano_lancamento'] ?>)
            </p>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                <span class="price-tag" style="font-weight: bold; color: var(--cor-destaque); font-size: 1.1em;">
                    R$ <?= number_format($album['preco_sugerido'] ?? 0.00, 2, ',', '.') ?>
                </span>
                <span class="btn-action-sm" style="font-size: 0.8em; padding: 5px 10px;">
                    <i class="fas fa-search-plus"></i> Ver mais
                </span>
            </div>
        </div>
    </div>
<?php endforeach; ?>
                </div>

                <?php renderizar_paginacao($pagina_atual, $total_paginas, $link_base); ?>

            <?php endif; ?>
        </main>

        <aside class="sidebar-filters" style="width: 250px; flex-shrink: 0;">
            <div class="card" style="padding: 15px;"> 
                <h3><i class="fas fa-filter"></i> Filtros de Catálogo</h3>
                <form method="GET" action="store.php" class="filters-container">
                    
                    <div class="search-container" style="margin-bottom: 10px;">
                        <label>Buscar Título:</label>
                        <input type="text" name="search_titulo" value="<?= htmlspecialchars($filtros['titulo']) ?>" style="width: 100%;">
                    </div>

                    <div class="search-container" style="margin-bottom: 10px;">
                        <label>Artista:</label>
                        <select name="filter_artista" style="width: 100%;">
                            <option value="">-- Todos --</option>
                            <?php foreach ($artistas as $artista): ?>
                                <option value="<?= $artista['id'] ?>" <?= ($filtros['artista_id'] == $artista['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($artista['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="search-container" style="margin-bottom: 10px;">
                        <label>Tipo:</label>
                        <select name="filter_tipo" style="width: 100%;">
                            <option value="">-- Todos --</option>
                            <?php foreach ($tipos as $tipo): ?>
                                <option value="<?= $tipo['id'] ?>" <?= ($filtros['tipo_id'] == $tipo['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tipo['descricao']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="search-container" style="margin-bottom: 10px;">
                        <label>Situação:</label>
                        <select name="filter_situacao" style="width: 100%;">
                            <option value="">-- Todas --</option>
                            <?php foreach ($situacoes as $situacao): ?>
                                <option value="<?= $situacao['id'] ?>" <?= ($filtros['situacao'] == $situacao['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($situacao['descricao']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="search-container" style="margin-bottom: 10px;">
                        <label>Formato:</label>
                        <select name="filter_formato" style="width: 100%;">
                            <option value="">-- Todos --</option>
                            <option value="-1" <?= ($filtros['formato_id'] == -1) ? 'selected' : '' ?>>-- Sem Formato --</option>
                            <?php foreach ($formatos as $formato): ?>
                                <option value="<?= $formato['id'] ?>" <?= ($filtros['formato_id'] == $formato['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($formato['descricao']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="search-container" style="margin-bottom: 10px;">
                        <label>Status:</label>
                        <select name="filter_deletado" style="width: 100%;">
                            <option value="0" <?= ($filtros['deletado'] == 0) ? 'selected' : '' ?>>Ativos</option>
                            <option value="1" <?= ($filtros['deletado'] == 1) ? 'selected' : '' ?>>Excluídos</option>
                            <option value="-1" <?= ($filtros['deletado'] == -1) ? 'selected' : '' ?>>Todos</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-action primary-action" style="width: 100%; margin-top: 15px; justify-content: center;">Aplicar Filtros</button>
                    
                    <?php if (!empty(array_filter($filtros))): ?>
                        <a href="store.php" class="back-link" style="text-align: center; margin-top: 10px; display: block;">Limpar Filtros</a>
                    <?php endif; ?>
                </form>
            </div>
        </aside>

    </div> 
</div>

<div id="modal-detalhes" class="modal" style="display:none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); backdrop-filter: blur(5px);">
    <div class="modal-content" style="background-color: var(--cor-fundo-card); margin: 5% auto; padding: 20px; border: 1px solid var(--cor-borda); width: 60%; border-radius: 12px; position: relative; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
        <span class="close-modal" onclick="fecharModal()" style="position: absolute; right: 20px; top: 15px; font-size: 28px; cursor: pointer; color: var(--cor-texto-secundario);">&times;</span>
        <div id="modal-body-content">
            <div style="text-align: center; padding: 50px;">
                <i class="fas fa-spinner fa-spin fa-3x"></i>
                <p>A carregar detalhes...</p>
            </div>
        </div>
    </div>
</div>

<script src="/../js/store.js"></script>

<?php require_once '../include/footer.php'; ?>