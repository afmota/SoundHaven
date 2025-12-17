<?php
// 1. Configurações Iniciais - Caminho corrigido!
require_once "../src/config/config.php"; 
require_once "../funcoes.php";

$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: store.php?error=id_ausente");
    exit;
}

try {
    // 2. Busca os dados principais do álbum
    // Corrigido: Não existe artista_id em colecao. Buscamos via colecao_artista.
    // Usamos GROUP_CONCAT para pegar todos os artistas caso haja mais de um (ex: Collabs)
    $sql = "SELECT c.*, 
            GROUP_CONCAT(a.nome SEPARATOR ', ') AS nomes_artistas
            FROM colecao c 
            LEFT JOIN colecao_artista ca ON c.id = ca.colecao_id
            LEFT JOIN artistas a ON ca.artista_id = a.id 
            WHERE c.id = :id
            GROUP BY c.id";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $album = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$album) {
        die("Erro: Álbum não encontrado no banco de dados.");
    }

    // 3. Busca as faixas deste álbum
    $sqlFaixas = "SELECT * FROM colecao_faixas WHERE colecao_id = :id ORDER BY numero_faixa ASC";
    $stmtFaixas = $pdo->prepare($sqlFaixas);
    $stmtFaixas->execute([':id' => $id]);
    $faixas = $stmtFaixas->fetchAll(PDO::FETCH_ASSOC);

    $album['faixas'] = $faixas ?: []; 

} catch (PDOException $e) {
    die("Erro crítico de banco de dados: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar: <?= htmlspecialchars($album['titulo']) ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .tracklist-table { width: 100%; border-collapse: collapse; margin-top: 15px; background: #1a1a1a; border-radius: 8px; overflow: hidden; }
        .tracklist-table th { background: #333; color: #aaa; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; }
        .tracklist-table th, .tracklist-table td { padding: 12px; border-bottom: 1px solid #222; text-align: left; }
        .editable-cell { background: #252525; border: 1px solid transparent; transition: 0.3s; }
        .editable-cell:focus { border-color: #007bff; background: #333; outline: none; }
        .btn-remove { color: #e74c3c; cursor: pointer; transition: 0.2s; }
        .btn-remove:hover { color: #ff0000; transform: scale(1.1); }
        .import-section { border: 1px solid #28a745; padding: 20px; border-radius: 10px; margin-bottom: 30px; }
    </style>
</head>
<body>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center">
        <h2><i class="fas fa-record-vinyl"></i> Editar Coleção</h2>
        <span class="badge bg-info"><?= htmlspecialchars($album['nomes_artistas']) ?></span>
    </div>
    <hr>

    <form id="form-editar-colecao">
        <input type="hidden" id="album_id" value="<?= $album['id'] ?>">

        <div class="row">
            <div class="col-md-8">
                <div class="form-group mb-3">
                    <label>Título do Álbum</label>
                    <input type="text" id="titulo_album" class="form-control" value="<?= htmlspecialchars($album['titulo']) ?>" style="font-size: 1.2rem; font-weight: bold;">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group mb-3">
                    <label>Nº Catálogo (Discogs)</label>
                    <div class="input-group">
                        <input type="text" id="cat-numero-import" class="form-control" value="<?= htmlspecialchars($album['numero_catalogo'] ?? '') ?>">
                        <button type="button" id="btn-import-discogs" class="btn btn-outline-success">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="tracklist-section mt-4">
            <h4><i class="fas fa-list-ol"></i> Faixas do Álbum</h4>
            <div id="import-status"></div>
            
            <table class="tracklist-table">
                <thead>
                    <tr>
                        <th width="5%">#</th>
                        <th width="70%">Título da Faixa</th>
                        <th width="15%">Duração</th>
                        <th width="10%"></th>
                    </tr>
                </thead>
                <tbody id="tracklist-body">
                    <?php foreach ($album['faixas'] as $faixa): ?>
                    <tr>
                        <td class="track-num"><?= $faixa['numero_faixa'] ?></td>
                        <td contenteditable="true" class="editable-cell editable-title"><?= htmlspecialchars($faixa['titulo']) ?></td>
                        <td contenteditable="true" class="editable-cell editable-duration"><?= htmlspecialchars($faixa['duracao'] ?? '0:00') ?></td>
                        <td class="text-center">
                            <i class="fas fa-times-circle btn-remove" title="Remover Faixa"></i>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="mt-3">
                <button type="button" id="btn-add-manual" class="btn btn-sm btn-secondary">
                    <i class="fas fa-plus"></i> Adicionar Faixa
                </button>
            </div>
        </div>

        <div class="footer-actions mt-5 pt-4 border-top">
            <button type="button" id="btn-save-full-album" class="btn btn-primary btn-lg px-5">
                <i class="fas fa-save"></i> SALVAR ALTERAÇÕES
            </button>
            <a href="store.php" class="btn btn-link text-muted">Descartar mudanças</a>
        </div>
    </form>
</div>

<script src="js/tracklist_manager.js"></script>

</body>
</html>