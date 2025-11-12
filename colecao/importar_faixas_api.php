<?php
set_time_limit(300); // 1. CORREÇÃO: Aumenta o limite de execução para 5 minutos (300 segundos). Isso evita o erro 500 por timeout do PHP.
// Arquivo: colecao/importar_faixas_api.php
// Endpoint para buscar lista de faixas no Discogs usando o número de catálogo e salvar no banco.

// --- CONFIGURAÇÃO DO DISCOGS API ---
// USANDO TOKEN DE ACESSO PESSOAL, conforme automatizar_capas.php
// O token de exemplo foi mantido aqui. É altamente recomendável não expor seu token.
const DISCOGS_TOKEN = 'XquypjKpERmGKjMRfgUbbVonxtGjHTggIeFgHxvo'; 
const USER_AGENT = 'SoundHavenApp/1.0'; // User-Agent necessário para a API do Discogs

// Inclui arquivos
require_once "../db/conexao.php";
require_once "../funcoes.php"; // Presumimos que esta contém formatar_data, etc.

header('Content-Type: application/json');

// =====================================================
// FUNÇÃO AUXILIAR PARA REQUISIÇÕES cURL
// =====================================================

/**
 * Faz a requisição à API do Discogs usando o token de autenticação.
 * @param string $url A URL completa da API do Discogs.
 * @return array|null O objeto de resposta JSON decodificado ou um array de erro.
 */
function request_discogs_api(string $url): ?array
{
    $ch = curl_init();

    // ** CORREÇÃO PARA "SSL certificate problem" **
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 
    // FIM DA CORREÇÃO
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    
    // 2. CORREÇÃO: Define timeouts específicos para a requisição cURL
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);       // Tempo máximo para a requisição completa (60s)
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Tempo máximo para a conexão inicial (10s)
    
    // Headers necessários para autenticação via Token
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: ' . USER_AGENT, 
        'Authorization: Discogs token=' . DISCOGS_TOKEN
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch); // 3. NOVO: Captura erros de rede/timeout do cURL
    
    curl_close($ch);

    // 3. NOVO: Checa por erros de cURL (timeout, falha de conexão, que geram o "Código HTTP: 0")
    if ($curl_error) {
        return ['error' => true, 'http_code' => 0, 'message' => "Erro de conexão/timeout cURL: " . $curl_error];
    }
    
    if ($http_code !== 200) {
        // Erro da API (404, 403, 429)
        return ['error' => true, 'http_code' => $http_code];
    }

    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Erro de JSON (o corpo da resposta veio, mas está malformado)
        return ['error' => true, 'http_code' => $http_code, 'message' => 'Erro ao decodificar JSON da API.'];
    }

    return $data;
}


// =====================================================
// 1. OBTENÇÃO E VALIDAÇÃO DOS DADOS DE ENTRADA
// =====================================================

$colecao_id = filter_input(INPUT_POST, 'colecao_id', FILTER_VALIDATE_INT);
$catalogo = filter_input(INPUT_POST, 'numero_catalogo', FILTER_DEFAULT);

if (!$colecao_id || empty($catalogo)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID da Coleção ou Número de Catálogo não fornecidos.']);
    exit();
}

// ----------------------------------------------------
// 2. BUSCAR LANÇAMENTO NO DISCOGS (USANDO NÚMERO DE CATÁLOGO)
// ----------------------------------------------------

$catalogo_clean = str_replace(' ', '', $catalogo);

$search_query = urlencode($catalogo_clean); // Apenas o número limpo como query geral
$discogs_search_url = "https://api.discogs.com/database/search?q=$search_query&type=release&per_page=1";

$search_result = request_discogs_api($discogs_search_url);

if (isset($search_result['error'])) {
    // 3. MODIFICADO: Melhora o tratamento de erro para exibir a mensagem completa se for um erro de cURL
    $error_message = $search_result['message'] ?? "Erro na API do Discogs (Busca). Código HTTP: {$search_result['http_code']}.";
    
    // Retorna 500 (Erro de Servidor) apenas se for um erro de rede/conexão (http_code: 0 ou erro interno)
    if ($search_result['http_code'] === 0 || $search_result['http_code'] === 500) {
        http_response_code(500); 
    } else {
        // Se for um 404/403/429 da API, retorna um erro 400 ou o código da API
        http_response_code($search_result['http_code'] == 404 ? 404 : 400); 
    }
    
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit();
}

$release_id = null;
$release_title = null;

if (isset($search_result['results']) && count($search_result['results']) > 0) {
    $release_id = $search_result['results'][0]['id'];
    $release_title = $search_result['results'][0]['title'];
}

if (!$release_id) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => "Nenhum lançamento encontrado no Discogs para o Número de Catálogo: $catalogo."]);
    exit();
}

// ----------------------------------------------------
// 3. BUSCAR A LISTA DE FAIXAS COMPLETA
// ----------------------------------------------------

$release_url = "https://api.discogs.com/releases/$release_id";
$release_data = request_discogs_api($release_url);

if (isset($release_data['error'])) {
    // 3. MODIFICADO: Melhora o tratamento de erro para exibir a mensagem completa se for um erro de cURL
    $error_message = $release_data['message'] ?? "Erro na API do Discogs (Detalhes). Código HTTP: {$release_data['http_code']}.";
    
    if ($release_data['http_code'] === 0 || $release_data['http_code'] === 500) {
        http_response_code(500); 
    } else {
        http_response_code($release_data['http_code'] == 404 ? 404 : 400); 
    }
    
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit();
}

$tracklist = $release_data['tracklist'] ?? [];

if (empty($tracklist)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Nenhuma lista de faixas encontrada para este lançamento no Discogs.']);
    exit();
}

// ----------------------------------------------------
// 4. SALVAR FAIXAS NO BANCO DE DADOS
// ----------------------------------------------------

$faixas_salvas = 0;
$pdo->beginTransaction();

try {
    // 4.1. Limpa faixas existentes 
    $sql_delete = "DELETE FROM colecao_faixas WHERE colecao_id = :colecao_id";
    $stmt_delete = $pdo->prepare($sql_delete);
    $stmt_delete->bindParam(':colecao_id', $colecao_id, PDO::PARAM_INT);
    $stmt_delete->execute();
    
    // 4.2. Insere as novas faixas
    $sql_insert = "INSERT INTO colecao_faixas (colecao_id, numero_faixa, titulo, duracao) 
                    VALUES (:colecao_id, :numero_faixa, :titulo, :duracao)";
    $stmt_insert = $pdo->prepare($sql_insert);
    
    $numero_sequencial = 1;

    foreach ($tracklist as $track) {
        // O tipo 'track' garante que estamos pegando faixas musicais (e não headers como 'Side A')
        if (!isset($track['type_']) || $track['type_'] !== 'track') {
            continue; 
        }
        
        $titulo = $track['title'] ?? 'Faixa Desconhecida';
        $duracao = $track['duration'] ?? null;
        
        $stmt_insert->bindValue(':colecao_id', $colecao_id, PDO::PARAM_INT);
        $stmt_insert->bindValue(':numero_faixa', $numero_sequencial, PDO::PARAM_INT);
        $stmt_insert->bindValue(':titulo', $titulo, PDO::PARAM_STR);
        $stmt_insert->bindValue(':duracao', $duracao, PDO::PARAM_STR);
        $stmt_insert->execute();

        $faixas_salvas++;
        $numero_sequencial++;
    }

    $pdo->commit();

    // SUCESSO
    http_response_code(200);
    echo json_encode([
        'success' => true, 
        'message' => "Lista de faixas importada com sucesso de **$release_title** (Discogs)! $faixas_salvas faixas salvas.",
        'faixas_salvas' => $faixas_salvas
    ]);

} catch (\PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro de banco de dados ao salvar as faixas: ' . $e->getMessage()]);
}

?>