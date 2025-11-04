<?php
// Arquivo: api/importar_faixas_pasta.php
// Endpoint para importar automaticamente faixas lendo tags ID3 de arquivos MP3/FLAC.

// Configurações de segurança e acesso
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Método não permitido
    echo json_encode(['status' => 'erro', 'message' => 'Método não permitido.']);
    exit();
}

// 1. Inclusão da Biblioteca de Tags ID3 (ASSUME A LOCALIZAÇÃO EM ../vendor/getid3/)
require_once '../vendor/getid3/getid3.php';

// Inclusão da conexão com o banco de dados e funções (para consistência)
require_once '../db/conexao.php';
require_once '../funcoes.php'; 

// 2. Coleta e validação de dados
$data = json_decode(file_get_contents("php://input"), true);
// O ID da coleção é apenas para fins de contexto, mas precisamos do caminho
$colecao_id = filter_var($data['colecao_id'] ?? null, FILTER_VALIDATE_INT); 
$folder_path = trim($data['folder_path'] ?? ''); // Caminho da pasta fornecido pelo usuário (ex: sepultura/roots_1996)

if (empty($folder_path)) {
    http_response_code(400);
    echo json_encode(['status' => 'erro', 'message' => 'O caminho da pasta de áudio é obrigatório.']);
    exit();
}

// 3. Verifica a segurança do caminho da pasta
// CRÍTICO: Define o caminho base onde você armazena seus arquivos.
// O ideal é que o usuário insira o caminho RELATIVO a esta pasta.
$base_audio_path = '../audio/'; // EX: Crie uma pasta 'audio' na raiz do seu projeto.
$full_path = realpath($base_audio_path . $folder_path);

// Verifica se o caminho é válido e, crucialmente, se ele está DENTRO do diretório base
if ($full_path === false || strpos($full_path, realpath($base_audio_path)) !== 0) {
    http_response_code(400);
    echo json_encode(['status' => 'erro', 'message' => 'Caminho de pasta inválido ou fora da área permitida.']);
    exit();
}

// 4. Lista arquivos
$allowed_extensions = '{mp3,flac,ogg,wav,m4a}'; // Formatos comuns de áudio
// Usa GLOB_BRACE e SORT_NATURAL para ordenar corretamente (01, 02, 10, etc.)
$files = glob($full_path . '/*.{' . $allowed_extensions . '}', GLOB_BRACE | SORT_NATURAL);

$faixas_encontradas = [];
$getID3 = new getID3; // Instancia a biblioteca

foreach ($files as $file) {
    $filename = basename($file);
    // Cria o URL relativo limpo para o <audio> tag
    $relative_url = str_replace(realpath('../'), '', $file); 
    
    // Tenta analisar o arquivo
    $file_info = $getID3->analyze($file);
    
    // Extrai Título (preferindo ID3v2, depois ID3v1, e caindo para o nome do arquivo)
    $titulo = '';
    if (isset($file_info['tags']['id3v2']['title'][0])) {
        $titulo = $file_info['tags']['id3v2']['title'][0];
    } elseif (isset($file_info['tags']['id3v1']['title'][0])) {
        $titulo = $file_info['tags']['id3v1']['title'][0];
    } else {
        $titulo = pathinfo($filename, PATHINFO_FILENAME);
    }

    // Duração formatada (MM:SS)
    $duracao = isset($file_info['playtime_string']) ? $file_info['playtime_string'] : '';
    
    $faixas_encontradas[] = [
        'titulo' => trim($titulo),
        'duracao' => trim($duracao),
        'audio_url' => '/' . ltrim($relative_url, '/\\'), // Garante uma URL relativa limpa
    ];
}

if (empty($faixas_encontradas)) {
    http_response_code(404);
    echo json_encode(['status' => 'alerta', 'message' => 'Nenhum arquivo de áudio encontrado na pasta especificada. Certifique-se de que o caminho está correto e as extensões são suportadas.', 'faixas' => []]);
    exit();
}

http_response_code(200);
echo json_encode(['status' => 'sucesso', 'message' => 'Faixas importadas com sucesso. Verifique os dados.', 'faixas' => $faixas_encontradas]);
?>