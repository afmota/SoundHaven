<?php
// Arquivo: processar_aquisicao.php (Raiz)
require_once '../src/config/config.php';
/** @var PDO $pdo */
require_once '../src/Model/StoreModel.php';

// 1. Captura o ID do álbum da loja
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($id) {
    $model = new StoreModel();
    
    // 2. Buscamos os dados do álbum no Model para popular o formulário de destino
    $album = $model->getDetalhes($id);

    if ($album) {
        /**
         * NOTA TÉCNICA: 
         * Removemos o UPDATE que alterava a situação para 4 aqui.
         * O status só será alterado no salvar_colecao_action.php após o sucesso do INSERT.
         */

        // 3. Montamos os parâmetros para preencher o formulário de Adicionar Coleção
        $params = http_build_query([
            'from_store' => $album['id'],
            'titulo'     => $album['titulo'],
            'artista_id' => $album['artista_id'] ?? '', 
            'formato_id' => $album['formato_id'] ?? '',
            'capa_url'   => $album['capa_url'] ?? '',
            'data_lanc'  => $album['data_lancamento'] ?? ''
        ]);

        // 4. Redirecionamos para o formulário da coleção levando os dados da loja
        header("Location: ../colecao/adicionar_colecao.php?" . $params);
        exit;
    }
}

// Caso o ID seja inválido ou o álbum não exista, retorna para a loja com erro
header("Location: store.php?status=erro&mensagem=album_nao_encontrado");
exit;