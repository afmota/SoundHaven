<?php
// Arquivo: detalhes_artista.php
// Visualiza os detalhes de um ARTISTA específico (tabela 'artistas') em um Modal.
// Assume que $item (dados do artista) foi carregado pelo endpoint AJAX (fetch_artista_details.php).

// NOTA: Os arquivos conexao.php e funcoes.php DEVEM ser incluídos no fetch_artista_details.php,
// que chama este arquivo, para que as funções formatar_data estejam disponíveis.

// ----------------------------------------------------
// 1. CONFIGURAÇÃO E VARIÁVEIS DE CONTEXTO
// ----------------------------------------------------

// Assume que $item está populado com os dados do artista
if (!isset($item) || empty($item)) {
    // Esta mensagem deve ser rara se o fetch_artista_details.php funcionar corretamente
    echo '<div class="alert erro">Erro: Dados do artista não carregados.</div>';
    return; // Encerra a execução do HTML de detalhes
}

// Variáveis para facilitar o uso no HTML
$artista_id = $item['id'];
$is_lixeira = ($item['ativo'] == 0);
$imagem_url = !empty($item['imagem_url']) ? htmlspecialchars($item['imagem_url']) : '../imagens/no-cover.png'; // Assume uma imagem padrão
$nome_pais = $item['nome_pais'] ?? 'N/D';
$genero = !empty($item['genero_principal']) ? htmlspecialchars($item['genero_principal']) : 'Não Informado';

// Links de Ação (Adaptados do scripts.js)
$edit_link = 'editar_artista.php?id=' . $artista_id;
$delete_link = 'excluir_artista.php?id=' . $artista_id; // Soft Delete
$restore_link = 'restaurar_artista.php?id=' . $artista_id; 

?>

<div class="detalhes-container">
    <div class="detalhes-layout">

        <aside class="detalhes-sidebar">
            <div class="card detalhes-card-capa">
                
                <?php if ($is_lixeira): ?>
                    <div class="lixeira-overlay">
                        <i class="fas fa-trash-alt"></i> ARTISTA NA LIXEIRA
                    </div>
                <?php endif; ?>

                <?php if ($item['imagem_url']): ?>
                    <img src="<?php echo $imagem_url; ?>" 
                        alt="Foto de <?php echo htmlspecialchars($item['nome']); ?>" 
                        class="detalhes-capa-grande">
                <?php else: ?>
                    <div class="detalhes-capa-grande no-cover-lg">FOTO INDISPONÍVEL</div>
                <?php endif; ?>

                <div class="detalhes-card-actions" id="modal-actions-artista">
                    <?php if ($is_lixeira): ?>
                        <a href="<?php echo $restore_link; ?>" 
                           class="btn-action primary-action full-width action-confirm"
                           data-confirm-message="Tem certeza que deseja restaurar o artista '<?php echo htmlspecialchars($item['nome']); ?>' da Lixeira?">
                            <i class="fas fa-undo"></i> Restaurar Artista
                        </a>
                    <?php else: ?>
                        <a href="<?php echo $edit_link; ?>" class="btn-action primary-action full-width" id="btn-edit-artista">
                            <i class="fas fa-edit"></i> Editar Artista
                        </a>
                        <a href="<?php echo $delete_link; ?>" 
                           class="btn-action danger-action full-width action-confirm"
                           data-confirm-message="ATENÇÃO: Este artista será movido para a Lixeira (Soft Delete). Continuar?">
                            <i class="fas fa-trash-alt"></i> Mover para Lixeira
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </aside>

        <main class="detalhes-main">
            <h1 class="detalhes-titulo" id="modal-title-artista">
                <?php echo htmlspecialchars($item['nome']); ?>
            </h1>

            <div class="card detalhes-card-info" style="margin-top: 20px;">
                <h3 class="card-title-details"><i class="fas fa-info-circle"></i> Informações Essenciais</h3>
                <div class="detalhes-grid-info">
                    
                    <div class="detalhe-item">
                        <strong>Gênero Principal:</strong>
                        <span><?php echo $genero; ?></span>
                    </div>

                    <div class="detalhe-item">
                        <strong>País de Origem:</strong>
                        <span><?php echo $nome_pais; ?></span>
                    </div>
                    
                    <div class="detalhe-item">
                        <strong>Período de Atividade:</strong>
                        <span>
                            <?php 
                                // Adaptação: Se data_inicio e data_fim estiverem presentes
                                $inicio = formatar_data($item['data_inicio']);
                                $fim = formatar_data($item['data_fim']);
                                
                                if ($inicio != 'N/D') {
                                    echo $inicio . ' - ';
                                    echo ($fim != 'N/D' && $item['data_fim'] !== '0000-00-00') ? $fim : 'Atualmente em atividade';
                                } else {
                                    echo 'N/D';
                                }
                            ?>
                        </span>
                    </div>

                    <div class="detalhe-item full-row">
                        <strong>Site Oficial:</strong>
                        <span>
                            <?php if (!empty($item['site_oficial'])): ?>
                                <a href="<?php echo htmlspecialchars($item['site_oficial']); ?>" target="_blank" class="link-externo">
                                    <?php echo htmlspecialchars($item['site_oficial']); ?> <i class="fas fa-external-link-alt"></i>
                                </a>
                            <?php else: ?>
                                N/D
                            <?php endif; ?>
                        </span>
                    </div>

                </div>
            </div>

            <?php if (!empty($item['biografia'])): ?>
            <div class="card detalhes-card-info">
                <h3 class="card-title-details"><i class="fas fa-book"></i> Biografia</h3>
                <p><?php echo nl2br(htmlspecialchars($item['biografia'])); ?></p>
            </div>
            <?php endif; ?>

            <div class="card detalhes-card-info">
                <h3 class="card-title-details"><i class="fas fa-compact-disc"></i> Álbuns na Coleção (FUTURO)</h3>
                <div id="modal-relacionamentos-artista">
                    <p style="color: var(--cor-texto-secundario);">
                        Neste local, será exibida a lista de todos os álbuns associados a este artista na sua Coleção. 
                        A lógica de busca e exibição desta lista será implementada posteriormente, após o CRUD básico.
                    </p>
                </div>
            </div>
            
            <?php /* // Estes campos foram solicitados para NÃO serem exibidos
            <div class="card detalhes-card-info">
                <h3 class="card-title-details"><i class="fas fa-history"></i> Log</h3>
                <div class="detalhes-grid-info">
                    <div class="detalhe-item">
                        <strong>Criado em:</strong>
                        <span><?php echo formatar_data_log($item['criado_em']); ?></span>
                    </div>
                    <div class="detalhe-item">
                        <strong>Última atualização:</strong>
                        <span><?php echo formatar_data_log($item['atualizado_em']); ?></span>
                    </div>
                </div>
            </div>
            */ ?>

        </main>
    </div>
</div>