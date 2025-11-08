<?php
// Arquivo: adicionar_album.php (Criação de Novo Registro)

require_once '../db/conexao.php';

$erro = null;

// --- CONSULTA DE DADOS RELACIONADOS (para popular os <select>s) ---
$sql_artistas = "SELECT id, nome FROM artistas ORDER BY nome ASC";
$sql_tipos = "SELECT id, descricao FROM tipo_album ORDER BY descricao ASC";
$sql_situacao = "SELECT id, descricao FROM situacao ORDER BY descricao ASC";
$sql_formatos = "SELECT id, descricao FROM formatos ORDER BY descricao ASC";

try {
    // Executa as consultas de dados relacionados
    $stmt_artistas = $pdo->query($sql_artistas);
    $artistas = $stmt_artistas->fetchAll(PDO::FETCH_ASSOC);

    $stmt_tipos = $pdo->query($sql_tipos);
    $tipos = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_situacao = $pdo->query($sql_situacao);
    $situacoes = $stmt_situacao->fetchAll(PDO::FETCH_ASSOC);

    $stmt_formatos = $pdo->query($sql_formatos);
    $formatos = $stmt_formatos->fetchAll(PDO::FETCH_ASSOC);

} catch (\PDOException $e) {
    $erro = "Erro ao carregar dados de seleção: " . $e->getMessage();
}

// --- PROCESSAMENTO DO FORMULÁRIO (INSERT) ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pega e sanitiza os dados do POST
    $novo_titulo = filter_input(INPUT_POST, 'titulo', FILTER_SANITIZE_SPECIAL_CHARS);
    $nova_data = filter_input(INPUT_POST, 'data_lancamento', FILTER_SANITIZE_SPECIAL_CHARS);
    
    // Pega os IDs (garantindo que sejam inteiros)
    $novo_artista_id = filter_input(INPUT_POST, 'artista_id', FILTER_VALIDATE_INT);
    $novo_tipo_id = filter_input(INPUT_POST, 'tipo_id', FILTER_VALIDATE_INT);
    $nova_situacao_id = filter_input(INPUT_POST, 'situacao_id', FILTER_VALIDATE_INT);
    
    // NOVO: TRATAMENTO DO FORMATO (OPCIONAL)
    $novo_formato_id = filter_input(INPUT_POST, 'formato_id', FILTER_VALIDATE_INT);
    
    // Se a seleção for vazia (ou inválida), definimos o ID como NULL para o banco
    if ($novo_formato_id === false || $novo_formato_id === null) {
        $novo_formato_id = null;
    }
    
    // Monta a query de INSERT
    $sql_insert = "INSERT INTO store 
                    (titulo, artista_id, data_lancamento, tipo_id, situacao, formato_id, criado_em, atualizado_em)
                    VALUES
                    (:titulo, :artista_id, :data_lancamento, :tipo_id, :situacao_id, :formato_id, NOW(), NOW())";

    try {
        $stmt_insert = $pdo->prepare($sql_insert);
        
        $stmt_insert->bindParam(':titulo', $novo_titulo);
        $stmt_insert->bindParam(':artista_id', $novo_artista_id, PDO::PARAM_INT);
        $stmt_insert->bindParam(':data_lancamento', $nova_data);
        $stmt_insert->bindParam(':tipo_id', $novo_tipo_id, PDO::PARAM_INT);
        $stmt_insert->bindParam(':situacao_id', $nova_situacao_id, PDO::PARAM_INT);
        
        // NOVO BIND: PDO::PARAM_INT ou PDO::PARAM_NULL
        // Se o valor for NULL, o PDO envia como nulo para o banco
        if ($novo_formato_id === null) {
            $stmt_insert->bindParam(':formato_id', $novo_formato_id, PDO::PARAM_NULL);
        } else {
            $stmt_insert->bindParam(':formato_id', $novo_formato_id, PDO::PARAM_INT);
        }
        
        $stmt_insert->execute();
        
        // SUCESSO! Redireciona de volta à lista com mensagem
        // CORREÇÃO CRÍTICA: Redirecionar para store.php
        header('Location: store.php?status=criado&album=' . urlencode($novo_titulo));
        exit();
        
    } catch (\PDOException $e) {
        $erro = "Erro ao salvar novo álbum: " . $e->getMessage();
    }
}

require_once '../include/header.php'; 
?>

<div class="container" style="padding-top: 100px;">
    <div class="main-layout"> 
        
        <main class="content-area full-width"> <div class="page-header-actions">
                <h1>Adicionar Novo Álbum ao Catálogo</h1>
                <a href="store.php" class="back-link">
                    <i class="fas fa-chevron-left"></i> Voltar para o Catálogo
                </a>
            </div>

            <?php if ($erro): ?>
                <p class="erro"><?php echo $erro; ?></p>
            <?php endif; ?>

            <div class="card">
                <form method="POST" action="adicionar_album.php" class="edit-form">

                    <div class="form-grid">
                        
                        <div>
                            <label for="titulo">Título:</label>
                            <input type="text" id="titulo" name="titulo" required>

                            <label for="artista_id">Artista/Banda:</label>
                            <select id="artista_id" name="artista_id" required>
                                <option value="">-- Selecione um Artista --</option>
                                <?php foreach ($artistas as $a): ?>
                                    <option value="<?php echo $a['id']; ?>">
                                        <?php echo htmlspecialchars($a['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <label for="data_lancamento">Data de Lançamento:</label>
                            <input type="date" id="data_lancamento" name="data_lancamento" required>
                        </div>

                        <div>
                            <label for="tipo_id">Tipo:</label>
                            <select id="tipo_id" name="tipo_id" required>
                                <option value="">-- Selecione o Tipo --</option>
                                <?php foreach ($tipos as $t): ?>
                                    <option value="<?php echo $t['id']; ?>">
                                        <?php echo htmlspecialchars($t['descricao']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <label for="situacao_id">Situação:</label>
                            <select id="situacao_id" name="situacao_id" required>
                                <option value="">-- Selecione a Situação --</option>
                                <?php foreach ($situacoes as $s): ?>
                                    <option value="<?php echo $s['id']; ?>">
                                        <?php echo htmlspecialchars($s['descricao']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <label for="formato_id">Formato (Opcional):</label>
                            <select id="formato_id" name="formato_id">
                                <option value="">-- Selecione o Formato --</option>
                                <?php foreach ($formatos as $f): ?>
                                    <option value="<?php echo $f['id']; ?>">
                                        <?php echo htmlspecialchars($f['descricao']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div> <div class="form-actions">
                         <a href="store.php" class="back-link secondary-action">
                            <i class="fas fa-times-circle"></i> Cancelar
                         </a>
                         <button type="submit" class="save-button">
                            <i class="fas fa-save"></i> Adicionar Álbum
                         </button>
                    </div>
                </form>
            </div> </main>
    </div> </div> <?php
require_once '../include/footer.php';