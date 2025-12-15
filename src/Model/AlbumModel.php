<?php
// Arquivo: src/Model/AlbumModel.php
// Versão FINAL (v5): Pluralização, Mapeamento de Formatos e Contagem de Gravadoras.

class AlbumModel {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // ===============================================
    // DADOS PARA O DASHBOARD (Controller: dashboard.php)
    // ===============================================

    /**
     * Busca todas as estatísticas principais do dashboard.
     * @param int $userId ID do usuário
     * @return array
     */
    public function getDashboardStats(int $userId): array {
        
        $sql = "
            SELECT 
                -- Mapeamento de formatos conforme fornecido
                SUM(CASE WHEN formato_id = 5 THEN 1 ELSE 0 END) AS count_lp,
                SUM(CASE WHEN formato_id = 7 THEN 1 ELSE 0 END) AS count_cd,
                SUM(CASE WHEN formato_id = 6 THEN 1 ELSE 0 END) AS count_k7,
                SUM(CASE WHEN formato_id = 12 THEN 1 ELSE 0 END) AS count_digital,
                
                -- Adicionado: Contagem de Gravadoras (usando a coluna direta da coleção)
                COUNT(DISTINCT c.gravadora_id) AS count_gravadoras, 
                
                COUNT(DISTINCT t1.artista_id) AS count_artistas, 
                
                MIN(YEAR(c.data_lancamento)) AS min_year,
                MAX(YEAR(c.data_lancamento)) AS max_year,
                (SELECT COUNT(id) FROM colecao WHERE user_id = :user_id_total AND ativo = 1) AS count_total
            FROM colecao c
            LEFT JOIN colecao_artista t1 ON c.id = t1.colecao_id
            LEFT JOIN artistas a ON t1.artista_id = a.id 
            
            WHERE c.user_id = :user_id AND c.ativo = 1
        ";
        
        $stats = [];
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id_total', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Adiciona a contagem específica de CD-Rs (formato_id = 8)
            $stats['count_cdr'] = $this->getCDRCount($userId);
            
            // Popula as métricas específicas
            $stats['count_lp'] = $stats['count_lp'] ?? 0;
            $stats['count_cd'] = $stats['count_cd'] ?? 0;
            $stats['count_k7'] = $stats['count_k7'] ?? 0; 
            $stats['count_digital'] = $stats['count_digital'] ?? 0; 
            
            // Calcula a abrangência em anos
            $stats['years_span'] = ($stats['max_year'] && $stats['min_year']) 
                                    ? ($stats['max_year'] - $stats['min_year']) + 1 
                                    : 0;
            
            // Popula variáveis usadas no HTML
            $stats['total_lps'] = $stats['count_lp'];
            $stats['total_cds'] = $stats['count_cd'];
            $stats['total_albuns'] = $stats['count_total'] ?? 0;
            $stats['total_artistas'] = $stats['count_artistas'] ?? 0;
            $stats['total_gravadoras'] = $stats['count_gravadoras'] ?? 0; // ATUALIZADO
            $stats['total_generos'] = 0; // Mantido como 0 conforme sua decisão
            $stats['min_year'] = $stats['min_year'] ?? 'N/A';
            $stats['max_year'] = $stats['max_year'] ?? 'N/A';
            $stats['anos_cobertos'] = $stats['years_span'] ?? 0;

            // Busca os dados de aniversário e últimos álbuns separadamente
            $stats['aniversariantes'] = $this->getAnniversaryAlbums($userId, 1);
            $stats['ultimos_albuns'] = $this->getRecentAlbums($userId, 5);
            
            $stats['erro_db'] = '';
            
        } catch (PDOException $e) {
            error_log("Erro no getDashboardStats: " . $e->getMessage());
            $stats['erro_db'] = 'Erro fatal no SQL: ' . $e->getMessage(); 
        }

        return $stats;
    }

    /**
     * Busca a contagem específica de CD-Rs (formato_id = 8).
     * @param int $userId ID do usuário
     * @return int
     */
    public function getCDRCount(int $userId): int {
        $sql = "
            SELECT COUNT(id) 
            FROM colecao 
            WHERE user_id = :user_id 
            AND ativo = 1 
            AND formato_id = 8
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        return (int) $stmt->fetchColumn();
    }


    /**
     * Busca os álbuns mais recentes (Últimas Aquisições).
     * @param int $userId ID do usuário
     * @param int $limit Limite de resultados.
     * @return array
     */
    public function getRecentAlbums(int $userId, int $limit = 5): array {
        $sql = "
            SELECT 
                c.id, 
                c.titulo, 
                c.capa_url, 
                YEAR(c.data_lancamento) as ano_lancamento,
                c.numero_catalogo,
                f.descricao AS formato_descricao,
                g.nome AS gravadora_nome,
                GROUP_CONCAT(DISTINCT a.nome SEPARATOR ', ') AS artista_nome,
                GROUP_CONCAT(DISTINCT ge.descricao SEPARATOR ', ') AS genero_nome
            FROM colecao c
            JOIN formatos f ON c.formato_id = f.id
            LEFT JOIN gravadoras g ON c.gravadora_id = g.id
            LEFT JOIN colecao_artista ca ON c.id = ca.colecao_id
            LEFT JOIN artistas a ON ca.artista_id = a.id
            LEFT JOIN colecao_genero cg ON c.id = cg.colecao_id
            LEFT JOIN generos ge ON cg.genero_id = ge.id
            WHERE c.user_id = :user_id AND c.ativo = 1
            GROUP BY c.id
            ORDER BY c.data_aquisicao DESC
            LIMIT :limite
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':limite', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca álbuns que fazem aniversário de Lançamento ou Aquisição hoje (Exemplo: 1 resultado).
     * @param int $userId ID do usuário
     * @param int $limit Limite de resultados.
     * @return array
     */
    public function getAnniversaryAlbums(int $userId, int $limit = 1): array {
        $sql = "
            SELECT 
                c.id, 
                c.titulo, 
                c.capa_url,
                TIMESTAMPDIFF(YEAR, c.data_aquisicao, CURDATE()) AS idade_aquisicao,
                TIMESTAMPDIFF(YEAR, c.data_lancamento, CURDATE()) AS idade_lancamento,
                
                CASE 
                    WHEN MONTH(c.data_lancamento) = MONTH(CURDATE()) AND DAY(c.data_lancamento) = DAY(CURDATE()) THEN 'L'
                    WHEN MONTH(c.data_aquisicao) = MONTH(CURDATE()) AND DAY(c.data_aquisicao) = DAY(CURDATE()) THEN 'A'
                    ELSE NULL
                END AS aniversario_tipo,
                
                GROUP_CONCAT(a.nome SEPARATOR ', ') AS artista_nome
            FROM colecao c
            LEFT JOIN colecao_artista ca ON c.id = ca.colecao_id
            LEFT JOIN artistas a ON ca.artista_id = a.id 
            WHERE c.user_id = :user_id AND c.ativo = 1
            AND (
                (MONTH(c.data_lancamento) = MONTH(CURDATE()) AND DAY(c.data_lancamento) = DAY(CURDATE()) AND c.data_lancamento IS NOT NULL)
                OR 
                (MONTH(c.data_aquisicao) = MONTH(CURDATE()) AND DAY(c.data_aquisicao) = DAY(CURDATE()))
            )
            GROUP BY c.id
            ORDER BY aniversario_tipo DESC, idade_aquisicao DESC
            LIMIT :limite
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':limite', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as &$row) {
            $row['aniversario_info'] = [];
            if ($row['aniversario_tipo'] === 'L') {
                $row['idade'] = $row['idade_lancamento'];
                $row['aniversario_info'][] = ['type' => 'release', 'text' => "Lançamento: {$row['idade']} anos"];
            } elseif ($row['aniversario_tipo'] === 'A') {
                $row['idade'] = $row['idade_aquisicao'];
                $row['aniversario_info'][] = ['type' => 'acquisition', 'text' => "Aquisição: {$row['idade']} anos"];
            }
        }

        return $results;
    }
    
    // ===============================================
    // DADOS PARA FORMULÁRIOS DE LISTA/DROPDOWN
    // ===============================================

    public function getGeneros(): array {
        // CORREÇÃO: Tabela 'generos' no plural
        $sql = "SELECT id, descricao FROM generos ORDER BY descricao";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFormatos(): array {
        // CORREÇÃO: Tabela 'formatos' no plural
        $sql = "SELECT id, descricao FROM formatos ORDER BY descricao";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getArtistas(): array {
        // Corrigido: Tabela 'artistas' no plural
        $sql = "SELECT id, nome FROM artistas ORDER BY nome";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getGravadoras(): array {
        // CORREÇÃO: Tabela 'gravadoras' no plural
        $sql = "SELECT id, nome FROM gravadoras ORDER BY nome";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ===============================================
    // LÓGICA DE INSERÇÃO (Controller: adicionar_colecao.php)
    // ===============================================

    /**
     * Insere um novo álbum na coleção e suas relações.
     * @param array $dadosAlbum Dados do álbum.
     * @return bool Sucesso ou falha.
     */
    public function insertAlbum(array $dadosAlbum): bool {
        $sql_colecao = "
            INSERT INTO colecao (
                titulo, 
                data_lancamento, 
                formato_id, 
                gravadora_id, 
                numero_catalogo, 
                data_aquisicao, 
                capa_url, 
                condicao, 
                preco,
                observacoes, 
                user_id 
            ) VALUES (
                :titulo, 
                :data_lancamento, 
                :formato_id, 
                :gravadora_id, 
                :numero_catalogo, 
                :data_aquisicao, 
                :capa_url, 
                :condicao,
                :preco,
                :observacoes, 
                :user_id
            )
        ";

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare($sql_colecao);
            
            $gravadoraId = $dadosAlbum['gravadora_id'] ?? null;
            
            $stmt->bindValue(':titulo', $dadosAlbum['titulo']);
            $stmt->bindValue(':data_lancamento', $dadosAlbum['data_lancamento']);
            $stmt->bindValue(':formato_id', $dadosAlbum['formato_id'], PDO::PARAM_INT);
            $stmt->bindValue(':gravadora_id', $gravadoraId, is_null($gravadoraId) ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':numero_catalogo', $dadosAlbum['numero_catalogo']);
            $stmt->bindValue(':data_aquisicao', $dadosAlbum['data_aquisicao']);
            $stmt->bindValue(':capa_url', $dadosAlbum['capa_url']);
            
            $stmt->bindValue(':condicao', $dadosAlbum['condicao']);
            $stmt->bindValue(':preco', $dadosAlbum['preco']); 
            
            $stmt->bindValue(':observacoes', $dadosAlbum['observacoes']);
            $stmt->bindValue(':user_id', $dadosAlbum['user_id'], PDO::PARAM_INT);

            $stmt->execute();

            $colecaoId = $this->pdo->lastInsertId();

            if ($colecaoId) {
                // Aqui as tabelas de junção 'colecao_artista' e 'colecao_genero' 
                // permanecem no singular se essa for a convenção para tabelas N:N.
                $this->insertRelations($colecaoId, $dadosAlbum['artistas'], 'colecao_artista', 'artista_id');
                $this->insertRelations($colecaoId, $dadosAlbum['generos'], 'colecao_genero', 'genero_id');
            }

            $this->pdo->commit();
            return true;

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Erro de Inserção de Álbum: " . $e->getMessage());
            return false;
        }
    }
    
    private function insertRelations(int $colecaoId, array $relationIds, string $table, string $column): void {
        if (empty($relationIds)) {
            return;
        }

        $values = [];
        foreach ($relationIds as $id) {
            $values[] = "($colecaoId, " . (int)$id . ")";
        }

        $sql = "INSERT INTO {$table} (colecao_id, {$column}) VALUES " . implode(', ', $values);
        $this->pdo->exec($sql);
    }

/**
     * Busca uma lista paginada de álbuns da coleção do usuário, com filtros.
     * @param int $userId ID do usuário
     * @param int $limit Limite de registros.
     * @param int $offset Deslocamento (para paginação).
     * @param string|null $filterFormat Filtro de formato (ex: 'LP', 'CD').
     * @param int $viewStatus Status de Ativo (1) ou Lixeira (0).
     * @return array
     */
    public function getAlbumsListFiltered(int $userId, int $limit, int $offset, ?string $filterFormat, int $viewStatus): array {
        
        // Cláusula WHERE base: user_id e status ativo/lixeira
        $whereStatus = "c.user_id = :user_id AND c.ativo = :view_status";
        
        $sql = "
            SELECT 
                c.id, 
                c.titulo, 
                c.capa_url, 
                c.ativo,
                YEAR(c.data_lancamento) AS ano_lancamento,
                f.descricao AS formato_descricao,
                
                -- Usa subquery para Artista Principal (copiando a lógica do seu código original)
                (
                    SELECT a.nome 
                    FROM colecao_artista AS ca
                    JOIN artistas AS a ON ca.artista_id = a.id
                    WHERE ca.colecao_id = c.id
                    ORDER BY a.nome ASC 
                    LIMIT 1
                ) AS artista_principal
            FROM colecao AS c
            LEFT JOIN formatos AS f ON c.formato_id = f.id
            WHERE {$whereStatus} 
        ";
        
        // Adiciona o filtro de formato, se fornecido
        if ($filterFormat) {
            $sql .= " AND f.descricao = :formato ";
        }
        
        $sql .= " 
            ORDER BY c.data_aquisicao DESC, c.titulo ASC
            LIMIT :limite OFFSET :offset
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':view_status', $viewStatus, PDO::PARAM_INT);
            $stmt->bindParam(':limite', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            
            if ($filterFormat) {
                $stmt->bindParam(':formato', $filterFormat, PDO::PARAM_STR);
            }
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Erro no getAlbumsListFiltered: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Conta o total de álbuns com filtros de formato e status.
     * @param int $userId ID do usuário
     * @param string|null $filterFormat Filtro de formato (ex: 'LP', 'CD').
     * @param int $viewStatus Status de Ativo (1) ou Lixeira (0).
     * @return int
     */
    public function countTotalAlbumsFiltered(int $userId, ?string $filterFormat, int $viewStatus): int {
        
        $whereStatus = "c.user_id = :user_id AND c.ativo = :view_status";
        $sql_select = "SELECT COUNT(c.id) FROM colecao AS c ";
        $sql_join = "";
        
        // Se houver filtro por formato, precisamos do JOIN
        if ($filterFormat) {
            $sql_join = "LEFT JOIN formatos AS f ON c.formato_id = f.id";
            $whereStatus .= " AND f.descricao = :formato";
        }
        
        $sql = $sql_select . $sql_join . " WHERE " . $whereStatus;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':view_status', $viewStatus, PDO::PARAM_INT);
            
            if ($filterFormat) {
                $stmt->bindParam(':formato', $filterFormat, PDO::PARAM_STR);
            }
            
            $stmt->execute();
            return (int) $stmt->fetchColumn();

        } catch (PDOException $e) {
            error_log("Erro no countTotalAlbumsFiltered: " . $e->getMessage());
            return 0;
        }
    }
}
