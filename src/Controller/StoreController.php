<?php
// Arquivo: src/Controller/StoreController.php

require_once dirname(__DIR__) . '/Model/StoreModel.php';

class StoreController {
    private $storeModel;

    public function __construct() {
        $this->storeModel = new StoreModel();
    }

    public function index() {
        // 1. CAPTURA E SANITIZAÇÃO (Exatamente os seus 6 filtros)
        $filtros = [
            'titulo'      => filter_input(INPUT_GET, 'search_titulo', FILTER_DEFAULT) ?? '',
            'artista_id'  => filter_input(INPUT_GET, 'filter_artista', FILTER_VALIDATE_INT) ?: null,
            'tipo_id'     => filter_input(INPUT_GET, 'filter_tipo', FILTER_VALIDATE_INT) ?: null,
            'situacao'    => filter_input(INPUT_GET, 'filter_situacao', FILTER_VALIDATE_INT) ?: null,
            'formato_id'  => filter_input(INPUT_GET, 'filter_formato', FILTER_VALIDATE_INT) ?: null,
            'deletado'    => filter_input(INPUT_GET, 'filter_deletado', FILTER_VALIDATE_INT) ?? 0
        ];

        // 2. PAGINAÇÃO
        $limite = 25;
        $pagina_atual = filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT) ?: 1;
        $offset = ($pagina_atual - 1) * $limite;

        // 3. BUSCA OS DADOS NO MODEL
        $albuns = $this->storeModel->listar($filtros, $limite, $offset);
        $total_registros = $this->storeModel->contarTotal($filtros);
        $total_paginas = ceil($total_registros / $limite);

        // 4. PREPARA O LINK PARA A PAGINAÇÃO
        $params_url = $_GET;
        unset($params_url['p']); // Remove a página atual para o link_base
        $link_base = 'store.php?' . http_build_query($params_url);

        // 5. ENVIA TUDO PARA A VIEW (Onde o HTML vai brilhar)
        return [
            'albuns'          => $albuns,
            'total_registros' => $total_registros,
            'total_paginas'   => $total_paginas,
            'pagina_atual'    => $pagina_atual,
            'link_base'       => $link_base,
            'filtros'         => $filtros
        ];
    }
}