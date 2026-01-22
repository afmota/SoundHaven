<?php
require_once '../src/config/config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$user_id = $_SESSION['user_id'] ?? 1; // Segurança: Sempre use o ID da sessão

/** @var PDO $pdo */
header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$id_album = isset($data['colecao_id']) ? (int)$data['colecao_id'] : 0;

if ($id_album <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID do álbum inválido.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. TRATAMENTO DE GRAVADORA (Lógica de Tag)
    $gravadora_id = $data['gravadora_id'] ?? null;
    if ($gravadora_id && !is_numeric($gravadora_id)) {
        // Se não for número, é uma gravadora nova vinda via Select2 Tag
        $stmt_g = $pdo->prepare("INSERT INTO gravadoras (nome) VALUES (?)");
        $stmt_g->execute([$gravadora_id]);
        $gravadora_id = $pdo->lastInsertId();
    }

    // 2. TRATAMENTO DE PREÇO
    $preco_raw = $data['preco'] ?? '0';
    $preco_limpo = str_replace(['R$', ' ', '.'], '', $preco_raw);
    $preco_final = (float)str_replace(',', '.', $preco_limpo);

    // 3. UPDATE COM TRAVA DE SEGURANÇA (user_id)
    $sql_main = "UPDATE colecao SET 
                    titulo = ?, capa_url = ?, gravadora_id = ?, 
                    formato_id = ?, numero_catalogo = ?, data_lancamento = ?, 
                    data_aquisicao = ?, preco = ?, observacoes = ?, 
                    atualizado_em = NOW() 
                WHERE id = ? AND user_id = ?";
    
    $stmt = $pdo->prepare($sql_main);
    $stmt->execute([
        $data['titulo'],
        $data['capa_url'] ?? null,
        $gravadora_id ?: null,
        $data['formato_id'] ?: null,
        $data['numero_catalogo'] ?? '',
        $data['data_lancamento'] ?: null,
        $data['data_aquisicao'] ?: null,
        $preco_final,
        $data['observacoes'] ?? '',
        $id_album,
        $user_id
    ]);

    // 4. FUNÇÃO DE SINCRONIZAÇÃO MELHORADA (Suporta Tags para Gêneros/Estilos)
    function syncAdvanced($pdo, $table, $column, $mainTable, $nameColumn, $values, $id_pai) {
        // Limpa antigos
        $pdo->prepare("DELETE FROM $table WHERE colecao_id = ?")->execute([$id_pai]);

        if (!empty($values) && is_array($values)) {
            $ins = $pdo->prepare("INSERT INTO $table (colecao_id, $column) VALUES (?, ?)");
            foreach ($values as $val) {
                if (is_numeric($val)) {
                    $idFinal = $val;
                } else {
                    // Se for texto, tenta achar ou cria (Tag)
                    $check = $pdo->prepare("SELECT id FROM $mainTable WHERE $nameColumn = ?");
                    $check->execute([$val]);
                    $idFinal = $check->fetchColumn();
                    if (!$idFinal) {
                        $create = $pdo->prepare("INSERT INTO $mainTable ($nameColumn) VALUES (?)");
                        $create->execute([$val]);
                        $idFinal = $pdo->lastInsertId();
                    }
                }
                $ins->execute([$id_pai, $idFinal]);
            }
        }
    }

    // Sincroniza M:N com suporte a criação dinâmica
    syncAdvanced($pdo, 'colecao_artista', 'artista_id', 'artistas', 'nome', $data['artistas'] ?? [], $id_album);
    syncAdvanced($pdo, 'colecao_genero', 'genero_id', 'generos', 'descricao', $data['generos'] ?? [], $id_album);
    syncAdvanced($pdo, 'colecao_estilo', 'estilo_id', 'estilos', 'descricao', $data['estilos'] ?? [], $id_album);
    syncAdvanced($pdo, 'colecao_produtor', 'produtor_id', 'produtores', 'nome', $data['produtores'] ?? [], $id_album);

    // 5. TRACKLIST (Wipe and Replace)
    $pdo->prepare("DELETE FROM colecao_faixas WHERE colecao_id = ?")->execute([$id_album]);

    if (!empty($data['tracks']) && is_array($data['tracks'])) {
        $stmt_f = $pdo->prepare("INSERT INTO colecao_faixas (colecao_id, numero_faixa, titulo, duracao) VALUES (?, ?, ?, ?)");
        foreach ($data['tracks'] as $idx => $track) {
            if (!empty($track['titulo'])) {
                $stmt_f->execute([$id_album, $idx + 1, $track['titulo'], $track['duracao'] ?? '']);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}