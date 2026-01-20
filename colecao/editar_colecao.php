<?php
// 1. CONFIGURAÇÃO E LÓGICA DE DADOS
require_once '../src/config/config.php';
/** @var PDO $pdo */

$id = $_GET['id'] ?? null;
if (!$id) { die("Álbum não encontrado."); }

// Busca dados básicos do álbum
$stmt = $pdo->prepare("SELECT * FROM colecao WHERE id = ?");
$stmt->execute([$id]);
$album = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$album) { die("Álbum não existe no banco de dados."); }

// Busca opções para os Selects
$gravadoras = $pdo->query("SELECT id, nome FROM gravadoras ORDER BY nome")->fetchAll();
$formatos = $pdo->query("SELECT id, descricao FROM formatos ORDER BY descricao")->fetchAll();
$generos = $pdo->query("SELECT id, descricao FROM generos ORDER BY descricao")->fetchAll();
$estilos = $pdo->query("SELECT id, descricao FROM estilos ORDER BY descricao")->fetchAll();
$produtores = $pdo->query("SELECT id, nome FROM produtores ORDER BY nome")->fetchAll();
$artistas = $pdo->query("SELECT id, nome FROM artistas ORDER BY nome")->fetchAll();

// Busca o que já está marcado (Relacionamentos M:N)
$meus_artistas = $pdo->query("SELECT artista_id FROM colecao_artista WHERE colecao_id = $id")->fetchAll(PDO::FETCH_COLUMN);
$meus_generos = $pdo->query("SELECT genero_id FROM colecao_genero WHERE colecao_id = $id")->fetchAll(PDO::FETCH_COLUMN);
$meus_estilos = $pdo->query("SELECT estilo_id FROM colecao_estilo WHERE colecao_id = $id")->fetchAll(PDO::FETCH_COLUMN);
$meus_produtores = $pdo->query("SELECT produtor_id FROM colecao_produtor WHERE colecao_id = $id")->fetchAll(PDO::FETCH_COLUMN);

// Busca a Tracklist
$stmt_t = $pdo->prepare("SELECT * FROM colecao_faixas WHERE colecao_id = ? ORDER BY numero_faixa ASC");
$stmt_t->execute([$id]);
$minhas_faixas = $stmt_t->fetchAll(PDO::FETCH_ASSOC);

include_once '../include/header.php'; 
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .colecao-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-top: 20px; }
    
    .card-dark { 
        background: rgba(255, 255, 255, 0.03); 
        backdrop-filter: blur(10px);
        border-radius: 15px; 
        padding: 25px; 
        margin-bottom: 25px; 
        border: 1px solid rgba(255, 255, 255, 0.1); 
    }

    .card-dark legend { 
        font-size: 1.1rem; font-weight: 700; color: #00d4ff; 
        text-transform: uppercase; margin-bottom: 20px; float: none; width: auto;
    }

    .card-dark label { font-size: 0.8rem; color: #aaa; margin-bottom: 5px; display: block; }

    input, select, textarea { 
        width: 100%; background: #1a1a1a !important; border: 1px solid #333 !important; 
        border-radius: 8px !important; color: #fff !important; padding: 10px; margin-bottom: 15px;
    }

    /* Estilo para o Preview da Capa */
    .cover-preview-wrapper {
        width: 100%;
        height: 120px;
        background: #111;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border: 1px solid #333;
        margin-bottom: 15px;
    }
    .cover-preview-wrapper img { max-width: 100%; max-height: 100%; object-fit: cover; }

    .tracklist-table { width: 100%; border-collapse: separate; border-spacing: 0 5px; }
    .tracklist-table th { color: #555; font-size: 0.7rem; text-transform: uppercase; padding: 10px; }
    .tracklist-table tr { background: rgba(255, 255, 255, 0.03); }
    .editable-cell { padding: 12px; outline: none; }

    .btn-save-master {
        background: linear-gradient(135deg, #007bff, #00d4ff); 
        border: none; padding: 20px; border-radius: 50px; color: #fff; 
        font-weight: bold; width: 30%; margin: 40px auto; font-size: 1rem;
        box-shadow: 0 10px 20px rgba(0, 123, 255, 0.2); transition: 0.3s;
        display: block;
    }
    .btn-save-master:hover { transform: translateY(-3px); box-shadow: 0 15px 25px rgba(0, 212, 255, 0.4); }

    .select2-container--default .select2-selection--multiple { background-color: #1a1a1a !important; border: 1px solid #333 !important; padding: 5px; }
    .select2-container--default .select2-selection--multiple .select2-selection__choice { background-color: #007bff !important; border: none !important; color: #fff !important; }
</style>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Editar Álbum: <span class="text-info"><?= htmlspecialchars($album['titulo']) ?></span></h1>
        <a href="colecao.php" class="btn-action primary-action">
            <i class="fas fa-times-circle"></i> Cancelar
        </a>
    </div>

    <input type="hidden" id="colecao_id" value="<?= $id ?>">

    <div class="colecao-grid">
        <div class="col-left">
            <fieldset class="card-dark">
                <legend><i class="fas fa-record-vinyl"></i> Identificação</legend>
                
                <label>Título</label>
                <input type="text" id="titulo" value="<?= htmlspecialchars($album['titulo']) ?>">

                <label>Artistas</label>
                <select id="artistas" class="select2" multiple>
                    <?php foreach($artistas as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= in_array($a['id'], $meus_artistas) ? 'selected' : '' ?>><?= $a['nome'] ?></option>
                    <?php endforeach; ?>
                </select>

                <div class="row">
                    <div class="col-6">
                        <label>Gravadora</label>
                        <select id="gravadora_id">
                            <option value="">Selecione...</option>
                            <?php foreach($gravadoras as $g): ?>
                                <option value="<?= $g['id'] ?>" <?= $g['id'] == $album['gravadora_id'] ? 'selected' : '' ?>><?= $g['nome'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label>Formato</label>
                        <select id="formato_id">
                            <option value="">Selecione...</option>
                            <?php foreach($formatos as $f): ?>
                                <option value="<?= $f['id'] ?>" <?= $f['id'] == $album['formato_id'] ? 'selected' : '' ?>><?= $f['descricao'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-md-9">
                        <label>URL da Capa (Imagem)</label>
                        <input type="text" id="capa_url" value="<?= htmlspecialchars($album['capa_url'] ?? '') ?>" placeholder="https://exemplo.com/imagem.jpg">
                    </div>
                    <div class="col-md-3">
                        <label>Preview</label>
                        <div class="cover-preview-wrapper">
                            <img id="img-preview" src="<?= $album['capa_url'] ?: '/img/no-cover.png' ?>" alt="Preview">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <label>Data de Lançamento Original</label>
                        <input type="date" id="data_lancamento" value="<?= $album['data_lancamento'] ?>">
                    </div>
                </div>
            </fieldset>
        </div>

        <div class="col-right">
            <fieldset class="card-dark">
                <legend><i class="fas fa-tags"></i> Classificação</legend>
                
                <label>Gêneros</label>
                <select id="generos" class="select2" multiple>
                    <?php foreach($generos as $gen): ?>
                        <option value="<?= $gen['id'] ?>" <?= in_array($gen['id'], $meus_generos) ? 'selected' : '' ?>><?= $gen['descricao'] ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Estilos</label>
                <select id="estilos" class="select2" multiple>
                    <?php foreach($estilos as $est): ?>
                        <option value="<?= $est['id'] ?>" <?= in_array($est['id'], $meus_estilos) ? 'selected' : '' ?>><?= $est['descricao'] ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Produtores</label>
                <select id="produtores" class="select2" multiple>
                    <?php foreach($produtores as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= in_array($p['id'], $meus_produtores) ? 'selected' : '' ?>><?= $p['nome'] ?></option>
                    <?php endforeach; ?>
                </select>
            </fieldset>
        </div>
    </div>

    <fieldset class="card-dark">
        <legend><i class="fas fa-list"></i> Tracklist / Faixas</legend>
        <div class="d-flex gap-2 mb-3">
            <button type="button" id="btn-import-discogs" class="btn-action primary-action">
                <i class="fas fa-sync"></i> Sincronizar Discogs
            </button>
            <button type="button" id="btn-add-manual" class="btn-action secondary-action">
                <i class="fas fa-plus"></i> Nova Faixa
            </button>
        </div>

        <table class="tracklist-table">
            <thead>
                <tr>
                    <th width="40">#</th>
                    <th>Título</th>
                    <th width="100">Duração</th>
                    <th width="40"></th>
                </tr>
            </thead>
            <tbody id="tracklist-body">
                <?php foreach($minhas_faixas as $f): ?>
                <tr>
                    <td class="track-num text-center text-info font-weight-bold"><?= $f['numero_faixa'] ?></td>
                    <td contenteditable="true" class="editable-cell editable-title"><?= htmlspecialchars($f['titulo']) ?></td>
                    <td contenteditable="true" class="editable-cell editable-duration"><?= $f['duracao'] ?></td>
                    <td class="text-center"><i class="fas fa-times btn-remove text-muted" style="cursor:pointer"></i></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </fieldset>

    <fieldset class="card-dark">
        <legend><i class="fas fa-coins"></i> Aquisição & Observações</legend>
        <div class="row">
            <div class="col-md-4">
                <label>Nº de Catálogo</label>
                <input type="text" id="numero_catalogo" value="<?= $album['numero_catalogo'] ?>">
            </div>
            <div class="col-md-4">
                <label>Preço Pago</label>
                <input type="text" id="preco" value="<?= number_format($album['preco'], 2, ',', '.') ?>">
            </div>
            <div class="col-md-4">
                <label>Data Aquisição</label>
                <input type="date" id="data_aquisicao" value="<?= $album['data_aquisicao'] ?>">
            </div>
        </div>
        <label>Notas Pessoais</label>
        <textarea id="observacoes" rows="3"><?= htmlspecialchars(html_entity_decode($album['observacoes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
    </fieldset>

    <button id="btn-save-full-album" class="btn-save-master">
        <i class="fas fa-check-circle"></i> CONCLUIR E SALVAR ALTERAÇÕES
    </button>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="/js/tracklist_manager.js?v=<?= time(); ?>"></script>
<script>
    $(document).ready(function() {
        if ($.fn.select2) {
            $('.select2').select2({ width: '100%' });
        }

        // Lógica de Atualização em tempo real do preview da imagem
        $('#capa_url').on('input', function() {
            const url = $(this).val().trim();
            if(url) {
                $('#img-preview').attr('src', url);
            } else {
                $('#img-preview').attr('src', '/img/no-cover.png');
            }
        });
    });
</script>

<?php include_once '../include/footer.php'; ?>