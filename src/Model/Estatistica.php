<?php
// Arquivo: src/Model/Estatistica.php

class Estatistica {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function gerarRelatorioCompleto() {
        return [
            'geral'               => $this->getGeral(),
            'formatos'            => $this->getFormatos(),
            'top_artistas'        => $this->getTopArtistas(),
            'top_artistas_faixas' => $this->getTopArtistasFaixas(),
            'top_generos'         => $this->getTopGeneros(),
            'top_estilos'         => $this->getTopEstilos(),
            'top_gravadoras'      => $this->getTopGravadoras(),
            'faixas'              => $this->getEstatisticasFaixas(),
            'anos'                => $this->getDistribuicaoAnos(),
        ];
    }

    private function getGeral() {
        $sql = "SELECT 
                    COUNT(id) AS total_itens,
                    SUM(preco) AS soma_precos,
                    AVG(preco) AS media_preco,
                    MIN(data_aquisicao) AS data_mais_antiga,
                    MAX(data_aquisicao) AS data_mais_recente
                FROM colecao WHERE ativo = 1";
        return $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
    }

    private function getFormatos() {
        $sql = "SELECT f.descricao AS formato, COUNT(c.id) AS total
                FROM colecao AS c
                INNER JOIN formatos AS f ON c.formato_id = f.id
                WHERE c.ativo = 1 GROUP BY f.descricao ORDER BY total DESC";
        $dados = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
        $totalGeral = array_sum(array_column($dados, 'total')) ?: 1;
        foreach ($dados as &$item) {
            $item['percentual'] = ($item['total'] / $totalGeral) * 100;
        }
        return $dados;
    }

    private function getTopArtistas() {
        $sql = "SELECT a.nome, COUNT(ca.colecao_id) AS total
                FROM artistas AS a
                JOIN colecao_artista AS ca ON a.id = ca.artista_id
                JOIN colecao AS c ON c.id = ca.colecao_id
                WHERE c.ativo = 1 GROUP BY a.nome ORDER BY total DESC LIMIT 10";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getTopArtistasFaixas() {
        $sql = "SELECT a.nome, COUNT(f.id) AS total
                FROM artistas AS a
                JOIN colecao_artista AS ca ON a.id = ca.artista_id
                JOIN colecao_faixas AS f ON ca.colecao_id = f.colecao_id 
                JOIN colecao AS c ON ca.colecao_id = c.id
                WHERE c.ativo = 1 GROUP BY a.nome ORDER BY total DESC LIMIT 10";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getTopGeneros() {
        $sql = "SELECT g.descricao AS nome, COUNT(cg.colecao_id) AS total
                FROM generos AS g
                JOIN colecao_genero AS cg ON g.id = cg.genero_id
                JOIN colecao AS c ON c.id = cg.colecao_id
                WHERE c.ativo = 1 GROUP BY g.descricao ORDER BY total DESC LIMIT 5";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getTopEstilos() {
        $sql = "SELECT e.descricao AS nome, COUNT(ce.colecao_id) AS total
                FROM estilos AS e
                JOIN colecao_estilo AS ce ON e.id = ce.estilo_id
                JOIN colecao AS c ON c.id = ce.colecao_id
                WHERE c.ativo = 1 GROUP BY e.descricao ORDER BY total DESC LIMIT 5";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getTopGravadoras() {
        $sql = "SELECT g.nome, COUNT(c.id) AS total
                FROM gravadoras AS g
                JOIN colecao AS c ON g.id = c.gravadora_id
                WHERE c.ativo = 1 GROUP BY g.nome ORDER BY total DESC LIMIT 5";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getDistribuicaoAnos() {
        $sql = "SELECT YEAR(data_lancamento) AS ano, COUNT(id) AS total
                FROM colecao WHERE ativo = 1 AND data_lancamento IS NOT NULL
                GROUP BY ano ORDER BY ano ASC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getEstatisticasFaixas() {
        $sql = "SELECT c.id AS colecao_id, c.titulo AS album_titulo, f.titulo AS faixa_titulo, f.duracao
                FROM colecao AS c
                JOIN colecao_faixas AS f ON c.id = f.colecao_id 
                WHERE c.ativo = 1 AND f.duracao IS NOT NULL AND f.duracao <> ''";
        $all_faixas = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        if (empty($all_faixas)) {
            return [
                'total_faixas' => 0, 'tempo_total' => '00:00', 'tempo_medio' => '00:00',
                'faixa_mais_longa' => null, 'faixa_mais_curta' => null,
                'album_mais_longo' => null, 'album_mais_curto' => null
            ];
        }

        $tempo_total_seg = 0;
        $faixa_longa = ['segundos' => 0, 'titulo' => 'N/A', 'album' => 'N/A'];
        $faixa_curta = ['segundos' => PHP_INT_MAX, 'titulo' => 'N/A', 'album' => 'N/A'];
        $albuns_tempo = [];

        foreach ($all_faixas as $faixa) {
            $seg = $this->time_to_seconds($faixa['duracao']);
            $tempo_total_seg += $seg;

            if ($seg > $faixa_longa['segundos']) {
                $faixa_longa = [
                    'segundos' => $seg, 
                    'duracao' => $faixa['duracao'], 
                    'titulo' => $faixa['faixa_titulo'],
                    'album' => $faixa['album_titulo']
                ];
            }
            if ($seg > 0 && $seg < $faixa_curta['segundos']) {
                $faixa_curta = [
                    'segundos' => $seg, 
                    'duracao' => $faixa['duracao'], 
                    'titulo' => $faixa['faixa_titulo'],
                    'album' => $faixa['album_titulo']
                ];
            }

            if (!isset($albuns_tempo[$faixa['colecao_id']])) {
                $albuns_tempo[$faixa['colecao_id']] = ['segundos' => 0, 'titulo' => $faixa['album_titulo']];
            }
            $albuns_tempo[$faixa['colecao_id']]['segundos'] += $seg;
        }

        $album_longo = ['segundos' => 0, 'titulo' => 'N/A'];
        $album_curto = ['segundos' => PHP_INT_MAX, 'titulo' => 'N/A'];

        foreach ($albuns_tempo as $alb) {
            if ($alb['segundos'] > $album_longo['segundos']) $album_longo = $alb;
            if ($alb['segundos'] > 0 && $alb['segundos'] < $album_curto['segundos']) $album_curto = $alb;
        }

        return [
            'total_faixas' => count($all_faixas),
            'tempo_total' => $this->format_seconds($tempo_total_seg),
            'tempo_medio' => $this->format_seconds(floor($tempo_total_seg / count($all_faixas))),
            'faixa_mais_longa' => $faixa_longa,
            'faixa_mais_curta' => $faixa_curta,
            'album_mais_longo' => ['titulo' => $album_longo['titulo'], 'duracao' => $this->format_seconds($album_longo['segundos'])],
            'album_mais_curto' => ['titulo' => $album_curto['titulo'], 'duracao' => $this->format_seconds($album_curto['segundos'])]
        ];
    }

    private function time_to_seconds($time) {
        $parts = explode(':', $time);
        if (count($parts) == 3) return ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2];
        if (count($parts) == 2) return ($parts[0] * 60) + $parts[1];
        return (int)$time;
    }

    private function format_seconds($seconds) {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;
        return $h > 0 ? sprintf("%02d:%02d:%02d", $h, $m, $s) : sprintf("%02d:%02d", $m, $s);
    }
}