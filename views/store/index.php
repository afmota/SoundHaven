<?php
// Arquivo: views/store/index.php
// Este arquivo recebe: $albuns, $total_registros, $total_paginas, $pagina_atual, $link_base, $filtros
// E as listas para os selects: $artistas, $tipos, $situacoes, $formatos

require_once '../include/header.php'; 
?>

<div class="container" style="padding-top: 100px;">
    <div class="main-layout"> 
        
        <main class="content-area">
            <div class="page-header-actions">
                <h1>Catálogo (Total: <?= $total_registros ?>)</h1>
                <div style="display: flex; gap: 10px;"> 
                    <a href="importar_store.php" class="btn-adicionar-catalogo">
                        <i class="fas fa-file-upload"></i> Importar em Lote
                    </a>
                    <a href="adicionar_album.php" class="btn-adicionar-catalogo">
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
                
                <div class="store-grid-container">
                    <?php foreach ($albuns as $album): ?>
                        <div class="album-card" style="
                            background-color: var(--cor-fundo-card);
                            border: 1px solid var(--cor-borda); 
                            border-radius: 8px; 
                            overflow: hidden; 
                            box-shadow: 0 4px 8px var(--sombra-card);
                            transition: transform 0.2s;
                            <?= ($album['deletado'] == 1) ? 'opacity: 0.5; filter: grayscale(100%);' : ''; ?>
                        " onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                            
                            <a href="detalhes_album.php?id=<?= $album['id'] ?>" title="<?= htmlspecialchars($album['titulo']) ?>">
                                <div class="album-cover-wrapper" style="width: 100%; aspect-ratio: 1 / 1; overflow: hidden; background-color: #333;">
                                    <?php if (!empty($album['capa_url'])): ?>
                                        <img src="<?= htmlspecialchars($album['capa_url']) ?>" alt="Capa" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; color: #aaa;">
                                            <i class="fas fa-compact-disc fa-3x"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </a>

                            <div class="card-details" style="padding: 10px;">
                                <h4 style="margin: 0 0 5px 0; font-size: 1em; height: 2.8em; overflow: hidden;">
                                    <a href="detalhes_album.php?id=<?= $album['id'] ?>" style="text-decoration: none; color: var(--cor-texto-principal); font-weight: bold;">
                                        <?= htmlspecialchars(limitar_texto($album['titulo'], 40)) ?>
                                    </a>
                                </h4>
                                <p style="margin: 0 0 5px 0; font-size: 0.85em; color: var(--cor-texto-secundario);">
                                    <?= htmlspecialchars($album['nome_artista']) ?> (<?= $album['ano_lancamento'] ?>)
                                </p>
                                
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                                    <span class="price-tag" style="font-weight: bold; color: var(--cor-destaque); font-size: 1.1em;">
                                        R$ <?= number_format($album['preco_sugerido'] ?? 0.00, 2, ',', '.') ?>
                                    </span>

                                    <?php if ($album['deletado'] == 0): ?>
                                        <a href="editar_album.php?id=<?= $album['id'] ?>" class="btn-action-sm" style="font-size: 0.8em; padding: 5px 10px;">
                                            <i class="fas fa-edit"></i> Detalhes
                                        </a>
                                    <?php else: ?>
                                        <span style="font-size: 0.8em; color: var(--cor-erro);"><i class="fas fa-trash"></i> Excluído</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php renderizar_paginacao($pagina_atual, $total_paginas, $link_base); ?>

            <?php endif; ?>
        </main>

        <aside class="sidebar-filters">
            <div class="card" style="padding: 15px;"> 
                <h3><i class="fas fa-filter"></i> Filtros de Catálogo</h3>
                <form method="GET" action="store.php" class="filters-container">
                    
                    <div class="search-container">
                        <label>Buscar Título:</label>
                        <input type="text" name="search_titulo" value="<?= htmlspecialchars($filtros['titulo']) ?>">
                    </div>

                    <div class="search-container">
                        <label>Artista:</label>
                        <select name="filter_artista">
                            <option value="">-- Todos --</option>
                            <?php foreach ($artistas as $artista): ?>
                                <option value="<?= $artista['id'] ?>" <?= ($filtros['artista_id'] == $artista['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($artista['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="save-button" style="width: 100%; margin-top: 15px;">Aplicar Filtros</button>
                    <?php if (!empty(array_filter($filtros))): ?>
                        <a href="store.php" class="back-link" style="text-align: center; margin-top: 10px;">Limpar Filtros</a>
                    <?php endif; ?>
                </form>
            </div>
        </aside>

    </div> 
</div> 

<?php require_once '../include/footer.php'; ?>