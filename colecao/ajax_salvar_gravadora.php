<?php
require_once '../src/config/config.php';

/** @var PDO $pdo */
header('Content-Type: application/json');

// 1. VERIFICAÇÃO DE DADOS
$nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';

if (empty($nome)) {
    echo json_encode(['success' => false, 'error' => 'O nome da gravadora não pode estar vazio.']);
    exit;
}

try {
    // 2. EVITAR DUPLICIDADE
    // Verificamos se já existe uma gravadora com esse nome para não criar nomes idênticos
    $stmt_check = $pdo->prepare("SELECT id, nome FROM gravadoras WHERE nome = ?");
    $stmt_check->execute([$nome]);
    $existente = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if ($existente) {
        // Se já existe, apenas retornamos o ID dela para o Select2 selecionar
        echo json_encode([
            'success' => true, 
            'id' => $existente['id'], 
            'nome' => $existente['nome'],
            'message' => 'Gravadora já existia e foi selecionada.'
        ]);
        exit;
    }

    // 3. INSERÇÃO
    $stmt = $pdo->prepare("INSERT INTO gravadoras (nome) VALUES (?)");
    $stmt->execute([$nome]);
    $novo_id = $pdo->lastInsertId();

    // 4. RESPOSTA DE SUCESSO
    echo json_encode([
        'success' => true, 
        'id' => $novo_id, 
        'nome' => $nome
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erro no banco de dados: ' . $e->getMessage()]);
}