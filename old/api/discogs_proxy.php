<?php
header('Content-Type: application/json');

// 1. CONFIGURAÇÃO - PEGAR O TOKEN NO DISCOGS
// Vá em: https://www.discogs.com/settings/developers e clique em "Generate Personal Access Token"
$token = "SEU_TOKEN_AQUI"; 
$userAgent = "SoundHavenApp/1.0 +http://localhost";

$catno = $_GET['catno'] ?? '';

if (empty($catno)) {
    echo json_encode(['error' => 'Número de catálogo não fornecido.']);
    exit;
}

// 2. BUSCA PELO NÚMERO DE CATÁLOGO
$searchUrl = "https://api.discogs.com/database/search?catno=" . urlencode($catno) . "&type=release&token=" . $token;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $searchUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
$searchResponse = curl_exec($ch);

$searchData = json_decode($searchResponse, true);

if (empty($searchData['results'])) {
    echo json_encode(['error' => 'Nenhum álbum encontrado com este catálogo no Discogs.']);
    exit;
}

// Pega o ID do primeiro resultado da busca
$releaseId = $searchData['results'][0]['id'];

// 3. BUSCA DETALHES DO ÁLBUM (TRACKLIST)
$releaseUrl = "https://api.discogs.com/database/releases/" . $releaseId . "?token=" . $token;
curl_setopt($ch, CURLOPT_URL, $releaseUrl);
$releaseResponse = curl_exec($ch);
curl_close($ch);

$releaseData = json_decode($releaseResponse, true);

// 4. FORMATA A RESPOSTA PARA O SEU JS
$tracks = [];
if (!empty($releaseData['tracklist'])) {
    foreach ($releaseData['tracklist'] as $track) {
        // Ignora faixas de cabeçalho (headings) se houver
        if ($track['type_'] == 'track') {
            $tracks[] = [
                'title' => $track['title'],
                'duration' => $track['duration'] ?: '0:00'
            ];
        }
    }
}

echo json_encode([
    'success' => true,
    'album' => $releaseData['title'],
    'tracklist' => $tracks
]);