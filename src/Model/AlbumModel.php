<?php
// Arquivo: src/Model/AlbumModel.php
// Versão ESTÁVEL: Correção de erro de data 1525 e compatibilidade PHP 7.4+

class AlbumModel {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // ===============================================
    // DADOS PARA O DASHBOARD
    // ===============================================

    public function getDashboardStats(int $userId): array {
        $stats = [];
        try {
            // 1. TOTAIS POR FORMATO (Dinâmico)
            $sqlFormatos = "
                SELECT f.descricao, COUNT(c.id) as total
                FROM formatos f
                LEFT JOIN colecao c ON f.id = c.formato_id AND c.user_id = :user_id AND c.ativo = 1
                GROUP BY f.id, f.descricao";
            
            $stmtF = $this->pdo->prepare($sqlFormatos);
            $stmtF->execute([':user_id' => $userId]);
            $rowsF = $stmtF->fetchAll(PDO::FETCH_ASSOC);

            $stats['count_total'] = 0;
            $stats['count_lp'] = 0;
            $stats['count_cd'] = 0;
            $stats['count_k7'] = 0;
            $stats['count_digital'] = 0;
            $stats['count_cdr'] = 0;

            foreach ($rowsF as $row) {
                $desc = strtoupper($row['descricao'] ?? '');
                $qtd = (int)$row['total'];
                $stats['count_total'] += $qtd;

                if (strpos($desc, 'LP') !== false || strpos($desc, 'VINYL') !== false) $stats['count_lp'] += $qtd;
                elseif (strpos($desc, 'CD-R') !== false) $stats['count_cdr'] += $qtd;
                elseif (strpos($desc, 'CD') !== false) $stats['count_cd'] += $qtd;
                elseif (strpos($desc, 'K7') !== false || strpos($desc, 'CASSETTE') !== false) $stats['count_k7'] += $qtd;
                elseif (strpos($desc, 'DIGITAL') !== false) $stats['count_digital'] += $qtd;
            }

            // 2. MÉTRICAS DE ARTISTAS E GRAVADORAS
            $sqlArt = "SELECT COUNT(DISTINCT ca.artista_id) FROM colecao_artista ca 
                       JOIN colecao c2 ON ca.colecao_id = c2.id WHERE c2.user_id = ? AND c2.ativo = 1";
            $stmtA = $this->pdo->prepare($sqlArt);
            $stmtA->execute([$userId]);
            $stats['count_artistas'] = $stmtA->fetchColumn();

            $sqlGrav = "SELECT COUNT(DISTINCT gravadora_id) FROM colecao WHERE user_id = ? AND ativo = 1 AND gravadora_id IS NOT NULL";
            $stmtG = $this->pdo->prepare($sqlGrav);
            $stmtG->execute([$userId]);
            $stats['total_gravadoras'] = $stmtG->fetchColumn();

            // 3. ABRANGÊNCIA DE ANOS (CORREÇÃO DO ERRO 1525)
            // Filtramos ignorando datas nulas ou que comecem com '0000'
            $sqlAnos = "SELECT MIN(YEAR(data_lancamento)) as min_y, MAX(YEAR(data_lancamento)) as max_y 
                        FROM colecao 
                        WHERE user_id = ? AND ativo = 1 
                        AND data_lancamento IS NOT NULL 
                        AND data_lancamento > '0001-01-01'";
            $stmtAnos = $this->pdo->prepare($sqlAnos);
            $stmtAnos->execute([$userId]);
            $anos = $stmtAnos->fetch(PDO::FETCH_ASSOC);

            // 4. MAPEAMENTO PARA O DASHBOARD
            $stats['min_year'] = $anos['min_y'] ?? date('Y');
            $stats['max_year'] = $anos['max_y'] ?? date('Y');
            $stats['years_span'] = ($stats['max_year'] && $stats['min_year']) ? ($stats['max_year'] - $stats['min_year'] + 1) : 0;
            
            $stats['total_albuns'] = $stats['count_total'];
            $stats['total_lps'] = $stats['count_lp'];
            $stats['total_cds'] = $stats['count_cd'];
            $stats['total_cdrs'] = $stats['count_cdr'];
            $stats['total_artistas'] = $stats['count_artistas'];
            $stats['total_generos'] = 0;
            $stats['anos_cobertos'] = $stats['years_span'];

            $stats['aniversariantes'] = $this->getAnniversaryAlbums($userId, 3);
            $stats['ultimos_albuns'] = $this->getRecentAlbums($userId, 5);
            $stats['erro_db'] = '';
            
        } catch (PDOException $e) {
            $stats['erro_db'] = 'Erro SQL: ' . $e->getMessage();
        }
        return $stats;
    }

    public function getRecentAlbums(int $userId, int $limit = 5): array {
        $sql = "SELECT c.id, c.titulo, c.capa_url, YEAR(c.data_lancamento) as ano_lancamento,
                f.descricao AS formato_descricao, g.nome AS gravadora_nome,
                (SELECT a.nome FROM artistas a JOIN colecao_artista ca ON a.id = ca.artista_id WHERE ca.colecao_id = c.id LIMIT 1) as artista_nome,
                (SELECT ge.descricao FROM generos ge JOIN colecao_genero cg ON ge.id = cg.genero_id WHERE cg.colecao_id = c.id LIMIT 1) as genero_nome
                FROM colecao c
                LEFT JOIN formatos f ON c.formato_id = f.id
                LEFT JOIN gravadoras g ON c.gravadora_id = g.id
                WHERE c.user_id = :user_id AND c.ativo = 1
                ORDER BY c.data_aquisicao DESC, c.id DESC LIMIT :limite";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limite', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { return []; }
    }

    public function getAnniversaryAlbums(int $userId, int $limit = 1): array {
        // Correção similar para datas zero no aniversário
        $sql = "SELECT c.id, c.titulo, c.capa_url,
                TIMESTAMPDIFF(YEAR, c.data_aquisicao, CURDATE()) AS idade_aquisicao,
                TIMESTAMPDIFF(YEAR, c.data_lancamento, CURDATE()) AS idade_lancamento,
                CASE 
                    WHEN MONTH(c.data_lancamento) = MONTH(CURDATE()) AND DAY(c.data_lancamento) = DAY(CURDATE()) THEN 'L'
                    ELSE 'A'
                END AS aniversario_tipo,
                (SELECT nome FROM artistas a JOIN colecao_artista ca ON a.id = ca.artista_id WHERE ca.colecao_id = c.id LIMIT 1) as artista_nome
                FROM colecao c
                WHERE c.user_id = :user_id AND c.ativo = 1
                AND (
                    (MONTH(c.data_lancamento) = MONTH(CURDATE()) AND DAY(c.data_lancamento) = DAY(CURDATE()) AND data_lancamento > '0001-01-01')
                    OR 
                    (MONTH(c.data_aquisicao) = MONTH(CURDATE()) AND DAY(c.data_aquisicao) = DAY(CURDATE()) AND data_aquisicao > '0001-01-01')
                )
                LIMIT :limite";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as &$row) {
            $row['aniversario_info'] = [['type' => ($row['aniversario_tipo'] == 'L' ? 'release' : 'acquisition'), 
                'text' => ($row['aniversario_tipo'] == 'L' ? "Lançamento: {$row['idade_lancamento']} anos" : "Aquisição: {$row['idade_aquisicao']} anos")]];
        }
        return $results;
    }

    // --- MÉTODOS DE FILTRO MANTIDOS ---
    public function getGeneros(): array { return $this->pdo->query("SELECT id, descricao FROM generos ORDER BY descricao")->fetchAll(PDO::FETCH_ASSOC); }
    public function getFormatos(): array { return $this->pdo->query("SELECT id, descricao FROM formatos ORDER BY descricao")->fetchAll(PDO::FETCH_ASSOC); }
    public function getArtistas(): array { return $this->pdo->query("SELECT id, nome FROM artistas ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC); }
    public function getGravadoras(): array { return $this->pdo->query("SELECT id, nome FROM gravadoras ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC); }

    public function getAlbumsListFiltered(int $userId, int $limit, int $offset, ?string $filterFormat, int $viewStatus): array {
        $where = "c.user_id = :user_id AND c.ativo = :view_status";
        if ($filterFormat) $where .= " AND f.descricao = :formato";
        $sql = "SELECT c.id, c.titulo, c.capa_url, YEAR(c.data_lancamento) as ano_lancamento, f.descricao as formato_descricao,
                (SELECT a.nome FROM artistas a JOIN colecao_artista ca ON a.id = ca.artista_id WHERE ca.colecao_id = c.id LIMIT 1) as artista_principal
                FROM colecao c LEFT JOIN formatos f ON c.formato_id = f.id WHERE $where
                ORDER BY c.data_aquisicao DESC, c.id DESC LIMIT :limite OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT); $stmt->bindValue(':view_status', $viewStatus, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limit, PDO::PARAM_INT); $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        if ($filterFormat) $stmt->bindValue(':formato', $filterFormat);
        $stmt->execute(); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countTotalAlbumsFiltered(int $userId, ?string $filterFormat, int $viewStatus): int {
        $sql = "SELECT COUNT(c.id) FROM colecao c LEFT JOIN formatos f ON c.formato_id = f.id 
                WHERE c.user_id = :user_id AND c.ativo = :view_status " . ($filterFormat ? " AND f.descricao = :formato" : "");
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT); $stmt->bindValue(':view_status', $viewStatus, PDO::PARAM_INT);
        if ($filterFormat) $stmt->bindValue(':formato', $filterFormat);
        $stmt->execute(); return (int) $stmt->fetchColumn();
    }
}