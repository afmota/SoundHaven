<?php
// Arquivo: colecao/salvar_faixas_confirmadas.php
// Endpoint para salvar as faixas confirmadas pelo usuário no banco de dados.

set_time_limit(60); 

require_once "../db/conexao.php";

header('Content-Type: application/json');

// =====================================================
// 1. OBTENÇÃO E VALIDAÇÃO DOS DADOS DE ENTRADA
// =====================================================

// Espera receber os dados via JSON no corpo da requisição POST
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

$colecao_id = $data['colecao_id'] ?? null;
$faixas = $data['tracklist'] ?? [];

// Validação básica dos dados
if (!$colecao_id || !is_array($faixas)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos ou incompletos para o salvamento.']);
    exit();
}

// =====================================================
// 2. SALVAR FAIXAS NO BANCO DE DADOS (TRANSAÇÃO)
// =====================================================

$faixas_salvas = 0;
$pdo->beginTransaction();

try {
    // 2.1. Limpa faixas existentes 
    $sql_delete = "DELETE FROM colecao_faixas WHERE colecao_id = :colecao_id";
    $stmt_delete = $pdo->prepare($sql_delete);
    $stmt_delete->bindParam(':colecao_id', $colecao_id, PDO::PARAM_INT);
    $stmt_delete->execute();
    
    // 2.2. Insere as novas faixas
    $sql_insert = "INSERT INTO colecao_faixas (colecao_id, numero_faixa, titulo, duracao) 
                     VALUES (:colecao_id, :numero_faixa, :titulo, :duracao)";
    $stmt_insert = $pdo->prepare($sql_insert);
    
    foreach ($faixas as $faixa) {
        
        $titulo = $faixa['titulo'] ?? 'Faixa Desconhecida';
        $numero_faixa = $faixa['numero_faixa'] ?? $faixas_salvas + 1; 

        // Tratamento da Duração: Garante NULL se for string vazia ou nulo
        $duracao = $faixa['duracao'] ?? null;
        if ($duracao === '') {
            $duracao = null;
        }

        $stmt_insert->bindValue(':colecao_id', $colecao_id, PDO::PARAM_INT);
        $stmt_insert->bindValue(':numero_faixa', $numero_faixa, PDO::PARAM_INT);
        $stmt_insert->bindValue(':titulo', $titulo, PDO::PARAM_STR);
        
        // Uso de PDO::PARAM_NULL se a duração for nula, garantindo o tipo correto.
        if ($duracao === null) {
            $stmt_insert->bindValue(':duracao', null, PDO::PARAM_NULL);
        } else {
            $stmt_insert->bindValue(':duracao', $duracao, PDO::PARAM_STR);
        }
        
        $stmt_insert->execute();

        $faixas_salvas++;
    }

    $pdo->commit();

    // SUCESSO
    http_response_code(200);
    echo json_encode([
        'success' => true, 
        'message' => "Importação de faixas concluída com sucesso! $faixas_salvas faixas salvas.",
        'faixas_salvas' => $faixas_salvas
    ]);

} catch (\PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro de banco de dados ao salvar as faixas: ' . $e->getMessage()]);
}
?>