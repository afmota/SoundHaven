<?php
// Arquivo: estatisticas.php
require_once '../src/config/config.php';
require_once '../src/Model/Estatistica.php';

// Formatação de data auxiliar (se não estiver no config.php)
function formatar_data($data) {
    return $data ? date('d/m/Y', strtotime($data)) : 'N/A';
}

$model = new Estatistica($pdo);
$stats = $model->gerarRelatorioCompleto();

include_once '../include/header.php';
include_once '../views/estatisticas_view.php';
include_once '../include/footer.php';