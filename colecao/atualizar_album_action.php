<?php
require_once '../src/config/config.php';
/** @var PDO $pdo */
header('Content-Type: application/json');

// 1. Recebe e decodifica o JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// 2. Validação básica do ID
$id_album = isset($data['colecao_id']) ? (int)$data['colecao_id'] : 0;

if ($id_album <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID do álbum inválido ou não recebido pelo servidor.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 3. Tratamento de preço (Converte 1.250,00 ou R$ 50,00 para 1250.00)
    $preco_raw = $data['preco'] ?? '0';
    $preco_limpo = str_replace(['R$', ' ', '.'], '', $preco_raw);
    $preco_final = (float)str_replace(',', '.', $preco_limpo);

    // 4. Update na Tabela Principal (colecao)
    $sql_main = "UPDATE colecao SET 
                    titulo = ?, 
                    capa_url = ?,
                    gravadora_id = ?, 
                    formato_id = ?, 
                    numero_catalogo = ?, 
                    data_lancamento = ?, 
                    data_aquisicao = ?, 
                    preco = ?, 
                    observacoes = ?, 
                    atualizado_em = NOW() 
                WHERE id = ?";
    
    $stmt = $pdo->prepare($sql_main);
    $stmt->execute([
        $data['titulo'],
        $data['capa_url'] ?? null,
        $data['gravadora_id'] ?: null,
        $data['formato_id'] ?: null,
        $data['numero_catalogo'] ?? '',
        $data['data_lancamento'] ?: null,
        $data['data_aquisicao'] ?: null,
        $preco_final,
        $data['observacoes'] ?? '',
        $id_album
    ]);

    // 5. Função de Sincronização para tabelas M:N
    // Ela usa o $id_album que validamos ser um inteiro existente
    function syncRelations($pdo, $table, $column, $list, $id_pai) {
        // Remove relações antigas
        $del = $pdo->prepare("DELETE FROM $table WHERE colecao_id = ?");
        $del->execute([$id_pai]);

        // Insere as novas
        if (is_array($list) && !empty($list)) {
            $ins = $pdo->prepare("INSERT INTO $table (colecao_id, $column) VALUES (?, ?)");
            foreach ($list as $item_id) {
                if (!empty($item_id)) {
                    $ins->execute([$id_pai, (int)$item_id]);
                }
            }
        }
    }

    // Executa sincronização para todas as tabelas auxiliares
    syncRelations($pdo, 'colecao_artista', 'artista_id', $data['artistas'] ?? [], $id_album);
    syncRelations($pdo, 'colecao_genero', 'genero_id', $data['generos'] ?? [], $id_album);
    syncRelations($pdo, 'colecao_estilo', 'estilo_id', $data['estilos'] ?? [], $id_album);
    syncRelations($pdo, 'colecao_produtor', 'produtor_id', $data['produtores'] ?? [], $id_album);

    // 6. Atualização da Tracklist (colecao_faixas)
    $del_faixas = $pdo->prepare("DELETE FROM colecao_faixas WHERE colecao_id = ?");
    $del_faixas->execute([$id_album]);

    if (isset($data['tracks']) && is_array($data['tracks'])) {
        $sql_faixa = "INSERT INTO colecao_faixas (colecao_id, numero_faixa, titulo, duracao) VALUES (?, ?, ?, ?)";
        $stmt_f = $pdo->prepare($sql_faixa);
        foreach ($data['tracks'] as $idx => $track) {
            if (!empty($track['titulo'])) {
                $stmt_f->execute([
                    $id_album,
                    $idx + 1,
                    $track['titulo'],
                    $track['duracao'] ?? ''
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
    echo json_encode(['success' => false, 'error' => "Erro no banco: " . $e->getMessage()]);
}