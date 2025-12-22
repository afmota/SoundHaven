<?php
// Arquivo: detalhes_album.php (Raiz)
require_once '../src/Model/StoreModel.php';
require_once '../src/functions/funcoes.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$model = new StoreModel();
$album = $id ? $model->getDetalhes($id) : false;

// Carrega apenas o conteúdo do modal
require_once '../views/store/detalhes.php';