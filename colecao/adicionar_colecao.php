<?php
require_once '../db/conexao.php';
/** @var PDO $pdo */
require_once '../funcoes.php';

// Captura dados vindos da Loja (se houver)
$from_store_id = filter_input(INPUT_GET, 'from_store', FILTER_VALIDATE_INT);
$titulo        = filter_input(INPUT_GET, 'titulo', FILTER_DEFAULT) ?? '';
$artista_id    = filter_input(INPUT_GET, 'artista_id', FILTER_VALIDATE_INT);
$formato_id    = filter_input(INPUT_GET, 'formato_id', FILTER_VALIDATE_INT);
$capa_url      = filter_input(INPUT_GET, 'capa_url', FILTER_DEFAULT) ?? '';
$data_lanc     = filter_input(INPUT_GET, 'data_lanc', FILTER_DEFAULT) ?? '';

// Consultas para os selects
$artistas = $pdo->query("SELECT id, nome FROM artistas ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$gravadoras = $pdo->query("SELECT id, nome FROM gravadoras ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$formatos = $pdo->query("SELECT id, descricao FROM formatos ORDER BY descricao ASC")->fetchAll(PDO::FETCH_ASSOC);
$generos = $pdo->query("SELECT id, nome FROM generos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$estilos = $pdo->query("SELECT id, nome FROM estilos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$produtores = $pdo->query("SELECT id, nome FROM produtores ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once '../include/header.php';
?>

<div class="container" style="padding-top: 100px;">
    <div class="form-container card">
        <div class="page-header-actions">
            <h1><i class="fas fa-plus-circle"></i> Novo Álbum na Coleção</h1>
            <a href="colecao.php" class="btn-action secondary-action">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <form id="form-colecao" class="colecao-form">
            <input type="hidden" id="colecao_id" value="0"> <input type="hidden" id="store_id" value="<?php echo $from_store_id; ?>">

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
                    <select id="gravadora_id">
                        <option value="">Selecione...</option>
                        <?php foreach ($gravadoras as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
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
                        <button type="button" id="btn-import-discogs" class="btn-action primary-action">
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

<script src="../js/tracklist_manager.js"></script>
<script>
$(document).ready(function() {
    $('.select2-artistas').select2({ placeholder: "Selecione o(s) artista(s)" });
    $('.select2-tags').select2({ placeholder: "Selecione as opções" });
});
</script>

<?php require_once '../include/footer.php'; ?>