<?php
require_once '../src/config/config.php';
/** @var PDO $pdo */
require_once '../src/functions/funcoes.php';

// Captura dados vindos da Loja (se houver)
$from_store_id = filter_input(INPUT_GET, 'from_store', FILTER_VALIDATE_INT);
$titulo         = filter_input(INPUT_GET, 'titulo', FILTER_DEFAULT) ?? '';
$artista_id     = filter_input(INPUT_GET, 'artista_id', FILTER_VALIDATE_INT);
$formato_id     = filter_input(INPUT_GET, 'formato_id', FILTER_VALIDATE_INT);
$capa_url       = filter_input(INPUT_GET, 'capa_url', FILTER_DEFAULT) ?? '';
$data_lanc      = filter_input(INPUT_GET, 'data_lanc', FILTER_DEFAULT) ?? '';

// Consultas para os selects
$artistas = $pdo->query("SELECT id, nome FROM artistas ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$gravadoras = $pdo->query("SELECT id, nome FROM gravadoras ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$formatos = $pdo->query("SELECT id, descricao FROM formatos ORDER BY descricao ASC")->fetchAll(PDO::FETCH_ASSOC);
$generos = $pdo->query("SELECT id, descricao AS nome FROM generos ORDER BY descricao ASC")->fetchAll(PDO::FETCH_ASSOC);
$estilos = $pdo->query("SELECT id, descricao AS nome FROM estilos ORDER BY descricao ASC")->fetchAll(PDO::FETCH_ASSOC);
$produtores = $pdo->query("SELECT id, nome FROM produtores ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once '../include/header.php';
?>

<style>
    /* Alinhamento para o grupo de gravadora */
    .input-addon-group { display: flex; gap: 8px; align-items: flex-start; }
    .btn-add-inline { background: #28a745; border: none; color: white; border-radius: 8px; padding: 0 12px; height: 42px; cursor: pointer; transition: 0.3s; }
    .btn-add-inline:hover { background: #218838; }

    /* Ajuste para o Modal no Dark Mode (Compatível com o estilo do Soundhaven) */
    .modal-content { background-color: #1a1a1a; color: #fff; border: 1px solid #333; border-radius: 15px; }
    .modal-header { border-bottom: 1px solid #333; }
    .modal-footer { border-top: 1px solid #333; }
    .modal-body input { background: #111 !important; border: 1px solid #333 !important; color: #fff !important; }
</style>

<div class="container" style="padding-top: 100px;">
    <div class="form-container card">
        <div class="page-header-actions">
            <h1><i class="fas fa-plus-circle"></i> Novo Álbum na Coleção</h1>
            <a href="colecao.php" class="btn-action secondary-action">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <form id="form-colecao" class="colecao-form">
            <input type="hidden" id="colecao_id" value="0"> 
            <input type="hidden" id="store_id" value="<?php echo $from_store_id; ?>">
            
            <div class="form-group full-width" style="margin-bottom: 20px;">
                <label>URL da Capa do Álbum</label>
                <div style="display: flex; gap: 15px; align-items: flex-start;">
                    <div style="flex-grow: 1;">
                        <input type="text" id="capa_url" value="<?php echo htmlspecialchars($capa_url); ?>" placeholder="Cole aqui a URL da imagem em alta resolução">
                        <small style="color: #666;">Dica: Você pode trocar a capa vinda da loja por uma melhor.</small>
                    </div>
                    <div id="preview-container" style="width: 100px; height: 100px; border: 1px solid #ddd; border-radius: 4px; overflow: hidden; background: #f9f9f9;">
                        <img id="img-preview" src="<?php echo $capa_url ?: '../assets/img/default-cover.png'; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group full-width">
                    <label>Título do Álbum</label>
                    <input type="text" id="titulo" value="<?php echo htmlspecialchars($titulo); ?>" required>
                </div>

                <div class="form-group full-width">
                    <label>Artista(s)</label>
                    <select id="artistas" multiple="multiple" class="select2-artistas">
                        <?php foreach ($artistas as $art): ?>
                            <option value="<?= $art['id'] ?>" <?= ($art['id'] == $artista_id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($art['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Gravadora</label>
                    <div class="input-addon-group">
                        <select id="gravadora_id">
                            <option value="">Selecione...</option>
                            <?php foreach ($gravadoras as $g): ?>
                                <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn-add-inline" data-toggle="modal" data-target="#modalGravadora" title="Nova Gravadora">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Formato</label>
                    <select id="formato_id">
                        <option value="">Selecione...</option>
                        <?php foreach ($formatos as $f): ?>
                            <option value="<?= $f['id'] ?>" <?= ($f['id'] == $formato_id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($f['descricao']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Número de Catálogo</label>
                    <div class="input-with-button">
                        <input type="text" id="numero_catalogo" placeholder="Ex: 6328 151">
                        <button type="button" id="btn-import-tracks" class="btn-action primary-action">
                            <i class="fas fa-sync"></i> Sincronizar
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Lançamento</label>
                    <input type="date" id="data_lancamento" value="<?php echo $data_lanc; ?>">
                </div>

                <div class="form-group">
                    <label>Data de Aquisição</label>
                    <input type="date" id="data_aquisicao" value="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
                    <label>Preço Pago (R$)</label>
                    <input type="text" id="preco" placeholder="0,00">
                </div>
            </div>

            <div class="form-grid" style="margin-top: 20px;">
                <div class="form-group">
                    <label>Gêneros</label>
                    <select id="generos" multiple="multiple" class="select2-tags">
                        <?php foreach ($generos as $gen): ?>
                            <option value="<?= $gen['id'] ?>"><?= htmlspecialchars($gen['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Estilos</label>
                    <select id="estilos" multiple="multiple" class="select2-tags">
                        <?php foreach ($estilos as $est): ?>
                            <option value="<?= $est['id'] ?>"><?= htmlspecialchars($est['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group full-width" style="margin-top: 20px;">
                <label>Produtor(es)</label>
                <select id="produtores" multiple="multiple" class="select2-tags">
                    <?php foreach ($produtores as $prod): ?>
                        <option value="<?= $prod['id'] ?>">
                            <?= htmlspecialchars($prod['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="tracklist-section" style="margin-top: 30px;">
                <div class="section-header">
                    <h3><i class="fas fa-list"></i> Lista de Faixas</h3>
                    <button type="button" id="btn-add-manual" class="btn-action-sm">
                        <i class="fas fa-plus"></i> Nova Faixa
                    </button>
                </div>
                <table class="tracklist-table">
                    <thead>
                        <tr>
                            <th width="50">#</th>
                            <th>Título</th>
                            <th width="120">Duração</th>
                            <th width="50"></th>
                        </tr>
                    </thead>
                    <tbody id="tracklist-body">
                    </tbody>
                </table>
            </div>

            <div class="form-group full-width" style="margin-top: 20px;">
                <label>Observações</label>
                <textarea id="observacoes" rows="4"></textarea>
            </div>

            <div class="form-actions" style="margin-top: 30px;">
                <button type="button" id="btn-save-full-album" class="btn-action primary-action">
                    <i class="fas fa-save"></i> SALVAR NA COLEÇÃO
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalGravadora" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Nova Gravadora</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <label>Nome da Gravadora</label>
        <input type="text" id="nova_gravadora_nome" class="form-control" placeholder="Digite o nome da gravadora...">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" id="btn-save-new-gravadora" class="btn btn-success">Salvar e Selecionar</button>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script src="../js/tracklist_manager.js"></script>
<script src="../js/colecao_form.js"></script>

<script>
$(document).ready(function() {
    // Gravadora SEM tags (apenas seleção)
    $('#gravadora_id').select2({
        placeholder: "Selecione...",
        width: '100%',
        allowClear: true
    });

    // Configura os demais selects
    $('.select2-artistas').select2({ width: '100%' });
    $('.select2-tags').select2({
        tags: true,
        width: '100%'
    });

    // Preview da imagem
    $('#capa_url').on('input', function() {
        $('#img-preview').attr('src', $(this).val() || '../assets/img/default-cover.png');
    });
});
</script>

<?php require_once '../include/footer.php'; ?>