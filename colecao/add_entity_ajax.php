<?php
// Arquivo: add_entity_ajax.php
// Endpoint AJAX para adicionar novas entidades (Artistas, Gravadoras, Gêneros, Produtores, Estilos)

require_once '../db/conexao.php'; // Ajuste o caminho conforme necessário

header('Content-Type: application/json');

// Lista branca de tabelas permitidas para INSERT via AJAX
// A chave é o nome da tabela; o valor é o nome da coluna de descrição.
$allowed_tables = [
    'artistas' => 'nome',
    'produtores' => 'nome',
    'gravadoras' => 'nome',
    'generos' => 'descricao',
    'estilos' => 'descricao',
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

// 1. Sanitização dos dados
$table_name = filter_input(INPUT_POST, 'table', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$new_value = filter_input(INPUT_POST, 'value', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// 2. Validação básica
if (empty($table_name) || !array_key_exists($table_name, $allowed_tables)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tabela inválida.']);
    exit;
}

if (empty($new_value)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valor a ser inserido não pode ser vazio.']);
    exit;
}

// Determinar o nome da coluna (nome ou descricao)
$column_name = $allowed_tables[$table_name];

try {
    // 3. (Opcional) Verificar se o valor já existe
    $sql_check = "SELECT id FROM {$table_name} WHERE {$column_name} = :value";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([':value' => $new_value]);
    
    if ($stmt_check->fetch()) {
        http_response_code(409); // Conflito (já existe)
        echo json_encode(['success' => false, 'message' => "O valor '{$new_value}' já existe em {$table_name}."]);
        exit;
    }

    // 4. Inserir o novo valor
    $sql_insert = "INSERT INTO {$table_name} ({$column_name}) VALUES (:value)";
    $stmt_insert = $pdo->prepare($sql_insert);
    $stmt_insert->execute([':value' => $new_value]);

    $new_id = $pdo->lastInsertId();

    if (!$new_id) {
        throw new \PDOException("Falha ao obter o ID da nova inserção.");
    }
    
    // 5. Sucesso: retorna o ID e o valor
    echo json_encode([
        'success' => true,
        'id' => $new_id,
        'value' => $new_value,
    ]);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro do banco de dados: ' . $e->getMessage()]);
}
?>