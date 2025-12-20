<?php
require_once '../src/config/config.php';
/** @var PDO $pdo */
header('Content-Type: application/json');

// Recebe o JSON enviado pelo fetch
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['colecao_id'])) {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos ou ID ausente.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Update na Tabela Coleção (Dados Principais)
    $stmt = $pdo->prepare("UPDATE colecao SET 
        titulo = ?, 
        gravadora_id = ?, 
        formato_id = ?, 
        numero_catalogo = ?, 
        data_lancamento = ?, 
        data_aquisicao = ?, 
        preco = ?, 
        observacoes = ?, 
        atualizado_em = NOW() 
        WHERE id = ?");
    
    // Tratamento de preço: remove "R$", troca vírgula por ponto e limpa espaços
    $preco_limpo = str_replace(['R$', ' ', '.'], '', $data['preco']);
    $preco_formatado = str_replace(',', '.', $preco_limpo);

    $stmt->execute([
        $data['titulo'], 
        $data['gravadora_id'] ?: null, 
        $data['formato_id'] ?: null,
        $data['numero_catalogo'] ?? '', 
        $data['data_lancamento'] ?: null, 
        $data['data_aquisicao'] ?: null, 
        $preco_formatado ?: 0, 
        $data['observacoes'] ?? '', 
        $data['colecao_id']
    ]);

    // 2. Função para sincronizar tabelas M:N (Melhorada com verificação de array)
    function sync($pdo, $table, $column, $ids, $colecao_id) {
        $pdo->prepare("DELETE FROM $table WHERE colecao_id = ?")->execute([$colecao_id]);
        if (is_array($ids) && !empty($ids)) {
            $ins = $pdo->prepare("INSERT INTO $table (colecao_id, $column) VALUES (?, ?)");
            foreach ($ids as $id) {
                if (!empty($id)) $ins->execute([$colecao_id, $id]);
            }
        }
    }

    // Sincronização das tabelas auxiliares
    sync($pdo, 'colecao_artista', 'artista_id', $data['artistas'] ?? [], $data['colecao_id']);
    sync($pdo, 'colecao_genero', 'genero_id', $data['generos'] ?? [], $data['colecao_id']);
    sync($pdo, 'colecao_estilo', 'estilo_id', $data['estilos'] ?? [], $data['colecao_id']);
    sync($pdo, 'colecao_produtor', 'produtor_id', $data['produtores'] ?? [], $data['colecao_id']);

    // 3. Tracklist (Delete e Insert)
    $pdo->prepare("DELETE FROM colecao_faixas WHERE colecao_id = ?")->execute([$data['colecao_id']]);
    
    if (isset($data['tracks']) && is_array($data['tracks'])) {
        $stmt_f = $pdo->prepare("INSERT INTO colecao_faixas (colecao_id, numero_faixa, titulo, duracao) VALUES (?, ?, ?, ?)");
        foreach ($data['tracks'] as $i => $t) {
            // Só insere se o título não estiver vazio
            if (!empty($t['titulo'])) {
                $stmt_f->execute([
                    $data['colecao_id'], 
                    $i + 1, 
                    $t['titulo'], 
                    $t['duracao'] ?? ''
                ]);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}