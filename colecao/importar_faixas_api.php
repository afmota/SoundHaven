<?php
set_time_limit(300); 
header('Content-Type: application/json');

const DISCOGS_TOKEN = 'XquypjKpERmGKjMRfgUbbVonxtGjHTggIeFgHxvo'; 
const USER_AGENT = 'SoundHavenApp/1.0';

require_once "../src/config/config.php";
require_once "../src/functions/funcoes.php"; 

// --- CAPTURA HÍBRIDA (Lê JSON ou POST) ---
$json = file_get_contents('php://input');
$data = json_decode($json, true);

$colecao_id = $data['colecao_id'] ?? filter_input(INPUT_POST, 'colecao_id', FILTER_VALIDATE_INT) ?? 0;
$catalogo = $data['numero_catalogo'] ?? filter_input(INPUT_POST, 'numero_catalogo', FILTER_DEFAULT);
// Nova captura: Título para refinamento de busca
$titulo_pesquisa = $data['titulo'] ?? filter_input(INPUT_POST, 'titulo', FILTER_DEFAULT);

if (empty($catalogo)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Número de Catálogo não fornecido.']);
    exit();
}

/**
 * Versão Corrigida: Substitui a variável deprecated por http_get_last_response_headers()
 */
function request_discogs_api(string $url) {
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => [
                "User-Agent: " . USER_AGENT,
                "Authorization: Discogs token=" . DISCOGS_TOKEN
            ],
            "ignore_errors" => true,
            "timeout" => 60
        ],
        "ssl" => [
            "verify_peer" => false,
            "verify_peer_name" => false,
        ]
    ];

    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return null;
    }

    $headers = function_exists('http_get_last_response_headers') 
                ? http_get_last_response_headers() 
                : ($http_response_header ?? []);

    if (empty($headers) || !str_contains($headers[0] ?? '', '200')) {
        return null;
    }

    return json_decode($response, true);
}

// LÓGICA DE BUSCA REFINADA
$catalogo_clean = str_replace([' ', '-', '.', '/'], '', $catalogo); 
$search_success = false;
$search_result = null;

/**
 * Estratégia de busca em 3 níveis de precisão:
 * 1. Catálogo + Título (Alta precisão)
 * 2. Somente Catálogo (Busca exata de catálogo)
 * 3. Busca Geral (Fallback)
 */
$tentativas = [
    // Nível 1: Catálogo + Título (Se o título foi informado)
    !empty($titulo_pesquisa) ? "release_title=" . urlencode($titulo_pesquisa) . "&catno=" . urlencode($catalogo_clean) : null,
    // Nível 2: Apenas Catálogo
    "catno=" . urlencode($catalogo_clean),
    // Nível 3: Query geral com o número de catálogo
    "q=" . urlencode($catalogo_clean)
];

foreach ($tentativas as $query_string) {
    if (!$query_string) continue;

    $url = "https://api.discogs.com/database/search?{$query_string}&type=release&per_page=10";
    $search_result = request_discogs_api($url);
    
    if ($search_result && !empty($search_result['results'])) { 
        $search_success = true; 
        break; 
    }
}

if (!$search_success) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => "Nenhum álbum encontrado para o catálogo: $catalogo"]);
    exit();
}

// LÓGICA DE MATCH EXATO PARA DESEMPATE
$melhor_resultado = null;
foreach ($search_result['results'] as $res) {
    $cat_res = str_replace([' ', '-', '.', '/'], '', $res['catno'] ?? '');
    
    // Se bater o catálogo E o título for parecido, é o match perfeito
    if ($cat_res === $catalogo_clean) {
        if (!empty($titulo_pesquisa) && stripos($res['title'], $titulo_pesquisa) !== false) {
            $melhor_resultado = $res;
            break;
        }
        // Se não houver título para comparar, pegamos o primeiro com catálogo idêntico
        if (!$melhor_resultado) {
            $melhor_resultado = $res;
        }
    }
}

$final_res = $melhor_resultado ?? $search_result['results'][0];

// BUSCA DETALHES DAS FAIXAS
$release_data = request_discogs_api("https://api.discogs.com/releases/" . $final_res['id']);

if (!$release_data || empty($release_data['tracklist'])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Lista de faixas não encontrada no Discogs.']);
    exit();
}

$faixas = [];
$seq = 1;
foreach ($release_data['tracklist'] as $t) {
    if (($t['type_'] ?? 'track') === 'track') {
        $faixas[] = [
            'numero_faixa' => $seq++,
            'titulo' => strip_tags($t['title']),
            'duracao' => $t['duration'] ?? ''
        ];
    }
}

echo json_encode([
    'success' => true, 
    'release_title' => $final_res['title'], // Retorna o título encontrado para conferência
    'tracklist' => $faixas,
    'colecao_id' => $colecao_id
]);