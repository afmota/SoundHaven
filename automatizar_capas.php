<?php

// =========================================================================
// SCRIPT DE AUTOMAÇÃO DE CAPAS VIA API DO DISCOGS
// Executar via linha de comando (terminal): php automatizar_capas.php
// =========================================================================

// --- 1. CONFIGURAÇÃO (MUITO IMPORTANTE!) -----------------------------------

// Chave de Acesso Pessoal (Personal Access Token) do Discogs.
// Você deve gerar esta chave na sua conta do Discogs e NÃO a compartilhe.
const DISCOGS_TOKEN = 'XquypjKpERmGKjMRfgUbbVonxtGjHTggIeFgHxvo'; 

// Tempo de pausa em segundos entre cada requisição para evitar Rate Limit (60/min).
// 1.5 segundos é um valor seguro.
const PAUSE_MICROSECONDS = 1500000;

// URL base da API de busca do Discogs
const DISCOGS_SEARCH_URL = 'https://api.discogs.com/database/search'; 

// Inclui o arquivo de conexão (assumindo que ele tem $pdo)
require_once 'db/conexao.php'; 

// Inclui funções (assumindo que você pode querer usar limitar_texto ou similar)
require_once 'funcoes.php'; 

// =========================================================================
// FUNÇÕES AUXILIARES PARA A BUSCA
// =========================================================================

/**
 * Remove caracteres especiais e formata a string para uma busca mais eficiente.
 * @param string $string
 * @return string
 */
function limpar_string(string $string): string
{
    // Remove tudo que não for letra, número ou espaço e normaliza espaços
    return preg_replace('/\s+/', ' ', preg_replace('/[^a-zA-Z0-9\s]/', '', $string));
}

/**
 * Faz a requisição à API do Discogs.
 * @param string $query A string de busca (e.g., "artista - album").
 * @return array|null O objeto de resposta JSON decodificado ou null em caso de erro.
 */
function buscar_capa_discogs(string $query): ?array
{
    // Limpar e codificar a query para a URL
    $query_limpa = urlencode(limpar_string($query));

    $url = DISCOGS_SEARCH_URL . "?q={$query_limpa}&type=release&per_page=1";
    
    // Configuração do cURL para fazer a requisição HTTP
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    
    // Headers necessários para autenticação e User-Agent (obrigatório pelo Discogs)
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: SoundHavenApp/1.0', // Substitua pelo nome do seu app
        'Authorization: Discogs token=' . DISCOGS_TOKEN
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);

    if ($http_code !== 200) {
        echo " [ERRO HTTP: {$http_code}]";
        return null;
    }

    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo " [ERRO JSON]";
        return null;
    }

    return $data;
}


// =========================================================================
// ROTINA PRINCIPAL DE EXECUÇÃO
// =========================================================================

echo "--- INICIANDO AUTOMAÇÃO DE CAPAS VIA DISCOGS ---\n";

// 1. Selecionar todos os itens que NÃO possuem capa_url preenchida
$sql_select = "SELECT s.id, a.nome as artista, s.titulo, s.capa_url FROM store s INNER JOIN artistas a ON s.artista_id = a.id WHERE s.capa_url IS NULL OR s.capa_url = '' LIMIT 10000"; 
// Limite alto para pegar tudo, mas pode ser ajustado se o banco for muito grande

try {
    $stmt = $pdo->query($sql_select);
    $items_pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_pendentes = count($items_pendentes);

    echo "Total de {$total_pendentes} itens sem capa_url encontrados.\n\n";

    if ($total_pendentes === 0) {
        echo "Nenhum item pendente. Automação finalizada.\n";
        exit;
    }

    $itens_atualizados = 0;
    
    foreach ($items_pendentes as $item) {
        
        $id = $item['id'];
        $artista = $item['artista'];
        $titulo = $item['titulo'];
        
        echo "ID: {$id} | Buscando: '{$artista} - {$titulo}'";

        // Monta a query de busca combinando artista e título
        $query_busca = trim($artista . ' ' . $titulo);

        $resultado = buscar_capa_discogs($query_busca);

        $nova_capa_url = null;

        if (isset($resultado['results']) && !empty($resultado['results'])) {
            // Pega o primeiro e melhor resultado (per_page=1)
            $primeiro_resultado = $resultado['results'][0];
            
            // O Discogs retorna a miniatura (thumb) no resultado da busca
            if (isset($primeiro_resultado['thumb']) && !empty($primeiro_resultado['thumb'])) {
                $nova_capa_url = $primeiro_resultado['thumb'];
                echo " [SUCESSO]";
            } else {
                echo " [NÃO ENCONTRADA IMAGEM]";
            }
        } else {
            echo " [NENHUM RESULTADO]";
        }

        // 2. Se encontrou uma URL, atualiza o banco de dados
        if ($nova_capa_url) {
            $sql_update = "UPDATE store SET capa_url = :capa_url WHERE id = :id";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([':capa_url' => $nova_capa_url, ':id' => $id]);
            $itens_atualizados++;
            echo " | Capa atualizada para: {$nova_capa_url}";
        }
        
        echo "\n";

        // 3. PAUSA (CRÍTICO PARA EVITAR BLOQUEIO)
        usleep(PAUSE_MICROSECONDS);
    }
    
    echo "\n--- FIM DA EXECUÇÃO ---\n";
    echo "Total de itens processados: {$total_pendentes}\n";
    echo "Total de capas atualizadas: {$itens_atualizados}\n";

} catch (PDOException $e) {
    echo "Erro de Banco de Dados: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Erro Geral: " . $e->getMessage() . "\n";
}

?>