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
// *** Verifique se o caminho abaixo está correto para sua instalação do getID3 ***
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
// *** Ajuste esta variável para a pasta RAIZ dos seus áudios (ex: '../audio/') ***
$base_audio_path = '../audio/'; 
$full_path = realpath($base_audio_path . $folder_path);

// Verifica se o caminho é válido e, crucialmente, se ele está DENTRO do diretório base
if ($full_path === false || strpos($full_path, realpath($base_audio_path)) !== 0 || !is_dir($full_path)) {
    http_response_code(400);
    echo json_encode(['status' => 'erro', 'message' => 'Caminho de pasta inválido ou não encontrado. Verifique se a pasta existe e se o caminho está correto.']);
    exit();
}

// 4. Lista arquivos
$allowed_extensions = '{mp3,flac,ogg,wav,m4a}'; // Formatos comuns de áudio
// Usa GLOB_BRACE e SORT_NATURAL para ordenar corretamente (01, 02, 10, etc.)
// Adicione o separador de diretório no final do $full_path, se não houver
$search_path = rtrim($full_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.{' . $allowed_extensions . '}';
$files = glob($search_path, GLOB_BRACE | SORT_NATURAL);

$faixas_encontradas = [];
$getID3 = new getID3; // Instancia a biblioteca

foreach ($files as $file) {
    $filename = basename($file);
    
    // Calcula o URL relativo limpo para o <audio> tag (ex: /audio/sepultura/roots_1996/01_roots_bloody_roots.mp3)
    // O str_replace abaixo usa realpath('../') (que é a raiz do seu projeto) para obter o caminho relativo.
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
        // Adiciona a barra inicial e remove barras duplicadas, substituindo a barra de diretório por URL-friendly
        'audio_url' => '/' . ltrim(str_replace(DIRECTORY_SEPARATOR, '/', $relative_url), '/'), 
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