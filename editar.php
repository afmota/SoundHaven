<?php
// Arquivo: editar.php

require_once 'conexao.php';

// 1. VERIFICA SE O ID FOI PASSADO
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de álbum inválido.");
}
$album_id = (int)$_GET['id'];

$erro = null;
$album = null;

// --- CONSULTA DE DADOS RELACIONADOS (para popular os <select>s) ---
// Note que você pode reutilizar as consultas do index.php aqui

$sql_artistas = "SELECT id, nome FROM artistas ORDER BY nome ASC";
$sql_tipos = "SELECT id, descricao FROM tipo_album ORDER BY descricao ASC";
$sql_situacao = "SELECT id, descricao FROM situacao ORDER BY descricao ASC";
$sql_formatos = "SELECT id, descricao FROM formatos ORDER BY descricao ASC";

try {
    // 2. Executa as consultas de dados relacionados
    $stmt_artistas = $pdo->query($sql_artistas);
    $artistas = $stmt_artistas->fetchAll(PDO::FETCH_ASSOC);

    $stmt_tipos = $pdo->query($sql_tipos);
    $tipos = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_situacao = $pdo->query($sql_situacao);
    $situacoes = $stmt_situacao->fetchAll(PDO::FETCH_ASSOC);

    $stmt_formatos = $pdo->query($sql_formatos);
    $formatos = $stmt_formatos->fetchAll(PDO::FETCH_ASSOC);
    
    
    // --- CONSULTA DO ÁLBUM A SER EDITADO ---
    
    $sql_album = "SELECT 
                    titulo, artista_id, data_lancamento, tipo_id, situacao, formato_id 
                  FROM store 
                  WHERE id = :id";
                  
    $stmt_album = $pdo->prepare($sql_album);
    $stmt_album->bindParam(':id', $album_id, PDO::PARAM_INT);
    $stmt_album->execute();
    $album = $stmt_album->fetch(PDO::FETCH_ASSOC);

    if (!$album) {
        die("Álbum não encontrado.");
    }

} catch (\PDOException $e) {
    $erro = "Erro ao carregar dados para edição: " . $e->getMessage();
}

// --- PROCESSAMENTO DO FORMULÁRIO (UPDATE) ---

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $album) {
    // 1. Sanitiza e pega os dados do POST (Simplificado para os principais campos)
    $novo_titulo = filter_input(INPUT_POST, 'titulo', FILTER_SANITIZE_SPECIAL_CHARS);
    $nova_data = filter_input(INPUT_POST, 'data_lancamento', FILTER_SANITIZE_SPECIAL_CHARS);
    
    // 2. Pega os IDs (garantindo que sejam inteiros)
    $novo_artista_id = filter_input(INPUT_POST, 'artista_id', FILTER_VALIDATE_INT);
    $novo_tipo_id = filter_input(INPUT_POST, 'tipo_id', FILTER_VALIDATE_INT);
    $nova_situacao_id = filter_input(INPUT_POST, 'situacao_id', FILTER_VALIDATE_INT);
    $novo_formato_id = filter_input(INPUT_POST, 'formato_id', FILTER_VALIDATE_INT);
    
    // 3. Monta a query de UPDATE (Com segurança total via Prepared Statements)
    $sql_update = "UPDATE store SET 
                    titulo = :titulo,
                    artista_id = :artista_id,
                    data_lancamento = :data_lancamento,
                    tipo_id = :tipo_id,
                    situacao = :situacao_id,
                    formato_id = :formato_id,
                    atualizado_em = NOW()
                   WHERE id = :id";

    try {
        $stmt_update = $pdo->prepare($sql_update);
        
        $stmt_update->bindParam(':titulo', $novo_titulo);
        $stmt_update->bindParam(':artista_id', $novo_artista_id, PDO::PARAM_INT);
        $stmt_update->bindParam(':data_lancamento', $nova_data);
        $stmt_update->bindParam(':tipo_id', $novo_tipo_id, PDO::PARAM_INT);
        $stmt_update->bindParam(':situacao_id', $nova_situacao_id, PDO::PARAM_INT);
        $stmt_update->bindParam(':formato_id', $novo_formato_id, PDO::PARAM_INT);
        $stmt_update->bindParam(':id', $album_id, PDO::PARAM_INT);
        
        $stmt_update->execute();
        
        // SUCESSO! Redireciona de volta à lista com mensagem
        header('Location: index.php?status=editado&album=' . urlencode($novo_titulo));
        exit();
        
    } catch (\PDOException $e) {
        $erro = "Erro ao salvar alterações: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Editar Álbum: <?php echo htmlspecialchars($album['titulo'] ?? $album_id); ?></title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

    <h1>Editar Álbum</h1>
    <hr>
    <a href="index.php" class="back-link">Voltar para a Lista</a>

    <?php if ($erro): ?>
        <p class="erro"><?php echo $erro; ?></p>
    <?php endif; ?>

    <?php if ($album): ?>
    <form method="POST" action="editar.php?id=<?php echo $album_id; ?>" class="edit-form">

        <label for="titulo">Título:</label>
        <input type="text" id="titulo" name="titulo" 
               value="<?php echo htmlspecialchars($album['titulo']); ?>" required>

        <label for="artista_id">Artista/Banda:</label>
        <select id="artista_id" name="artista_id" required>
            <?php foreach ($artistas as $a): ?>
                <option value="<?php echo $a['id']; ?>" 
                        <?php echo ($a['id'] == $album['artista_id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($a['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <label for="data_lancamento">Data de Lançamento:</label>
        <input type="date" id="data_lancamento" name="data_lancamento" 
               value="<?php echo htmlspecialchars($album['data_lancamento']); ?>" required>

        <label for="tipo_id">Tipo:</label>
        <select id="tipo_id" name="tipo_id" required>
            <?php foreach ($tipos as $t): ?>
                <option value="<?php echo $t['id']; ?>" 
                        <?php echo ($t['id'] == $album['tipo_id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($t['descricao']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <label for="situacao_id">Situação:</label>
        <select id="situacao_id" name="situacao_id" required>
            <?php foreach ($situacoes as $s): ?>
                <option value="<?php echo $s['id']; ?>" 
                        <?php echo ($s['id'] == $album['situacao']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($s['descricao']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <label for="formato_id">Formato:</label>
        <select id="formato_id" name="formato_id" required>
            <?php foreach ($formatos as $f): ?>
                <option value="<?php echo $f['id']; ?>" 
                        <?php echo ($f['id'] == $album['formato_id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($f['descricao']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="save-button">Salvar Alterações</button>
    </form>
    <?php endif; ?>

</body>
</html>