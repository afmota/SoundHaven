<?php
set_time_limit(300); 
// Arquivo: colecao/importar_faixas_api.php
// OBJETIVO: Buscar lista de faixas e retornar para CONFIRMAÇÃO DO USUÁRIO.

// --- CONFIGURAÇÃO DO DISCOGS API ---
const DISCOGS_TOKEN = 'XquypjKpERmGKjMRfgUbbVonxtGjHTggIeFgHxvo'; 
const USER_AGENT = 'SoundHavenApp/1.0';

// Inclui arquivos
require_once "../db/conexao.php";
require_once "../funcoes.php"; 

header('Content-Type: application/json');

// =====================================================
// FUNÇÃO AUXILIAR PARA REQUISIÇÕES cURL
// =====================================================

function request_discogs_api(string $url): ?array
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); 
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); 
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: ' . USER_AGENT, 
        'Authorization: Discogs token=' . DISCOGS_TOKEN
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch); 
    
    curl_close($ch);

    if ($curl_error) {
        return ['error' => true, 'http_code' => 0, 'message' => "Erro de conexão/timeout cURL: " . $curl_error];
    }
    
    if ($http_code !== 200) {
        return ['error' => true, 'http_code' => $http_code];
    }

    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => true, 'http_code' => $http_code, 'message' => 'Erro ao decodificar JSON da API.'];
    }

    return $data;
}


// =====================================================
// 1. OBTENÇÃO E VALIDAÇÃO DOS DADOS DE ENTRADA
// =====================================================

$colecao_id = filter_input(INPUT_POST, 'colecao_id', FILTER_VALIDATE_INT);
$catalogo = filter_input(INPUT_POST, 'numero_catalogo', FILTER_DEFAULT);

// NOTE: O $titulo_album foi removido daqui para manter a versão estável, 
// mas o JS continua enviando, o que é inofensivo neste arquivo.

if (!$colecao_id || empty($catalogo)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID da Coleção ou Número de Catálogo não fornecidos.']);
    exit();
}

// ----------------------------------------------------
// 2. BUSCAR LANÇAMENTO NO DISCOGS (USANDO NÚMERO DE CATÁLOGO)
// ----------------------------------------------------

$catalogo_clean = str_replace([' ', '-', '.', '/'], '', $catalogo); 

$search_result = null;
$search_success = false;
$search_attempt = 1;

while (!$search_success && $search_attempt <= 2) {
    
    if ($search_attempt === 1) {
        // Tenta a busca estrita por número de catálogo
        $search_query = urlencode("catno:{$catalogo_clean}");
        $discogs_search_url = "https://api.discogs.com/database/search?q={$search_query}&type=release&per_page=10"; 
    } else {
        // FALLBACK: Tenta a busca geral
        $search_query = urlencode($catalogo_clean);
        $discogs_search_url = "https://api.discogs.com/database/search?q={$search_query}&type=release&per_page=10";
    }

    $search_result = request_discogs_api($discogs_search_url);
    
    // Sucesso se não houver erro e houver resultados
    if (!isset($search_result['error']) && isset($search_result['results']) && count($search_result['results']) > 0) {
        $search_success = true;
        break;
    }
    
    $search_attempt++;
}

if (!$search_success) {
    $error_message = $search_result['message'] ?? "Erro na API do Discogs (Busca). Código HTTP: {$search_result['http_code']}.";
    
    $http_code_return = 404;
    if (isset($search_result['http_code'])) {
        $http_code_return = ($search_result['http_code'] === 0 || $search_result['http_code'] === 500) ? 500 : $search_result['http_code'];
    }
    
    http_response_code($http_code_return); 
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit();
}

// Filtra resultados para encontrar o match exato do Catálogo
$release_id = null;
$release_title = null;
$catalogo_buscado = $catalogo_clean;
$melhor_resultado = null;

foreach ($search_result['results'] as $result) {
    if (isset($result['catno'])) {
        $catno_result_clean = str_replace([' ', '-', '.', '/'], '', $result['catno']);
        
        if ($catno_result_clean === $catalogo_buscado) {
            $melhor_resultado = $result;
            break; 
        }
    }
}

if ($melhor_resultado) {
    $release_id = $melhor_resultado['id'];
    $release_title = $melhor_resultado['title'];
} else {
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
    $error_message = $release_data['message'] ?? "Erro na API do Discogs (Detalhes). Código HTTP: {$release_data['http_code']}.";
    $http_code_return = 404;
    if (isset($release_data['http_code'])) {
        $http_code_return = ($release_data['http_code'] === 0 || $release_data['http_code'] === 500) ? 500 : $release_data['http_code'];
    }
    
    http_response_code($http_code_return); 
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
// 4. PREPARAÇÃO PARA CONFIRMAÇÃO (RETORNA AS FAIXAS)
// ----------------------------------------------------

$faixas_para_importar = [];
$numero_sequencial = 1;

foreach ($tracklist as $track) {
    if (!isset($track['type_']) || $track['type_'] !== 'track') {
        continue; 
    }
    
    $titulo_limpo = strip_tags($track['title'] ?? 'Faixa Desconhecida');

    $faixas_para_importar[] = [
        'numero_faixa' => $numero_sequencial,
        'titulo' => $titulo_limpo,
        'duracao' => $track['duration'] ?? null,
    ];

    $numero_sequencial++;
}

// SUCESSO - Retorna a lista para o JavaScript AGUARDAR CONFIRMAÇÃO
http_response_code(200);
echo json_encode([
    'success' => true, 
    'action' => 'confirm_tracks', // Ação para o JS
    'colecao_id' => $colecao_id, // CRUCIAL para o próximo passo de salvamento
    'release_id' => $release_id,
    'release_title' => $release_title,
    'message' => "Faixas encontradas. Confirme a importação de **$release_title**.",
    'tracklist' => $faixas_para_importar
]);
?>