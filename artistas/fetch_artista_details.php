<?php
// Arquivo: fetch_artista_details.php
require_once __DIR__ . "/../src/config/config.php";
require_once __DIR__ . "/../funcoes.php";

header('Content-Type: application/json');

$artista_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$artista_id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

try {
    // 1. Busca os dados do Artista
    $sql = "SELECT a.*, p.nome AS pais_nome, g.descricao AS genero_nome
            FROM artistas a
            LEFT JOIN paises p ON a.pais_origem = p.id
            LEFT JOIN generos g ON a.genero_principal = g.id
            WHERE a.id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $artista_id]);
    $artista = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$artista) {
        echo json_encode(['success' => false, 'message' => 'Artista não encontrado.']);
        exit;
    }

    // 2. BUSCA OS ÁLBUNS DESSE ARTISTA NA COLEÇÃO
    $sql_albuns = "
        SELECT c.id, c.titulo, c.capa_url, c.data_lancamento
        FROM colecao c
        INNER JOIN colecao_artista ca ON c.id = ca.colecao_id
        WHERE ca.artista_id = :id
        ORDER BY c.data_lancamento ASC"; // Do mais antigo para o mais novo
    
    $stmt_albuns = $pdo->prepare($sql_albuns);
    $stmt_albuns->execute([':id' => $artista_id]);
    $artista['albuns_colecao'] = $stmt_albuns->fetchAll(PDO::FETCH_ASSOC);

    // Formatações
    $artista['data_inicio_pt'] = formatar_data($artista['data_inicio']);
    $artista['biografia_html'] = $artista['biografia'] ? nl2br(htmlspecialchars($artista['biografia'])) : 'Sem biografia disponível.';

    echo json_encode(['success' => true, 'artista' => $artista]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}