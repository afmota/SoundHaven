<?php if (!$album): ?>
    <p class="alerta erro">Álbum não encontrado ou excluído.</p>
<?php else: ?>
    <div class="modal-album-details">
        <div style="display: flex; gap: 20px; align-items: start;">
            <div class="modal-cover" style="width: 200px; flex-shrink: 0;">
                <?php if (!empty($album['capa_url'])): ?>
                    <img src="<?= htmlspecialchars($album['capa_url']) ?>" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.5);">
                <?php else: ?>
                    <div style="width: 100%; aspect-ratio: 1/1; background: #333; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                        <i class="fas fa-compact-disc fa-4x" style="color: #555;"></i>
                    </div>
                <?php endif; ?>
            </div>

            <div class="modal-info" style="flex: 1;">
                <h2 style="margin: 0 0 10px 0; color: var(--cor-destaque);"><?= htmlspecialchars($album['titulo']) ?></h2>
                <p style="font-size: 1.2em; margin-bottom: 20px;"><?= htmlspecialchars($album['nome_artista']) ?></p>

                <div class="details-list" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <small style="color: var(--cor-texto-secundario); display: block;">Lançamento</small>
                        <strong><?= date('Y', strtotime($album['data_lancamento'])) ?></strong>
                    </div>
                    <div>
                        <small style="color: var(--cor-texto-secundario); display: block;">Formato</small>
                        <strong><?= htmlspecialchars($album['descricao_formato'] ?? 'N/A') ?></strong>
                    </div>
                    <div>
                        <small style="color: var(--cor-texto-secundario); display: block;">Tipo</small>
                        <strong><?= htmlspecialchars($album['descricao_tipo'] ?? 'N/A') ?></strong>
                    </div>
                    <div>
                        <small style="color: var(--cor-texto-secundario); display: block;">Situação</small>
                        <span class="status-badge"><?= htmlspecialchars($album['descricao_situacao']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <hr style="border: 0; border-top: 1px solid var(--cor-borda); margin: 25px 0;">

        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div class="log-info" style="font-size: 0.8em; color: var(--cor-texto-secundario);">
                Criado em: <?= date('d/m/Y H:i', strtotime($album['criado_em'])) ?>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <a href="editar_album.php?id=<?= $album['id'] ?>" class="btn-action">
                    <i class="fas fa-edit"></i> Editar
                </a>
                
                <?php if ($album['situacao_id'] != 4): ?>
                    <a href="processar_aquisicao.php?id=<?= $album['id'] ?>" class="btn-action primary-action">
                        <i class="fas fa-check-circle"></i> Adquirir (Mover para Coleção)
                    </a>
                <?php endif; ?>

                <button onclick="confirmarDescarte(<?= $album['id'] ?>)" class="btn-action delete-button">
                    <i class="fas fa-times-circle"></i> Descartar
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>