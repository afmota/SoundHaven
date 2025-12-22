<?php
// Arquivo: src/Model/StoreModel.php

require_once dirname(__DIR__) . '/Database.php'; 

class StoreModel {
    
    private PDO $pdo;

    public function __construct() {
        // Obtém a instância única da conexão através do seu padrão Database
        $this->pdo = Database::getInstance();
    }

    /**
     * Busca álbuns da loja com filtros e paginação (Lógica migrada do store.php original)
     */
    public function listar(array $filtros, int $limite, int $offset): array {
        $params = [];
        $where = $this->construirWhere($filtros, $params);

        $sql = "SELECT 
                    s.id, s.titulo, s.capa_url, s.situacao, s.preco_sugerido, 
                    s.deletado, s.formato_id,
                    DATE_FORMAT(s.data_lancamento, '%Y') AS ano_lancamento,
                    a.nome AS nome_artista, t.descricao AS tipo, 
                    st.descricao AS status, f.descricao AS formato
                FROM store AS s
                LEFT JOIN artistas AS a ON s.artista_id = a.id
                LEFT JOIN tipo_album AS t ON s.tipo_id = t.id
                LEFT JOIN situacao AS st ON s.situacao = st.id
                LEFT JOIN formatos AS f ON s.formato_id = f.id
                WHERE $where
                ORDER BY s.data_lancamento DESC
                LIMIT :limite OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        // Binda os parâmetros dos filtros dinamicamente
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }

        // Binda paginação
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Conta total de registros para controle de paginação
     */
    public function contarTotal(array $filtros): int {
        $params = [];
        $where = $this->construirWhere($filtros, $params);
        
        $sql = "SELECT COUNT(s.id) as total FROM store s WHERE $where";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($res['total'] ?? 0);
    }

    /**
     * Helper para construir a cláusula WHERE mantendo sua lógica original de 6 filtros
     */
    private function construirWhere(array $filtros, array &$params): string {
        $condicoes = [];

        // 1. Título
        if (!empty($filtros['titulo'])) {
            $condicoes[] = "s.titulo LIKE :titulo";
            $params[':titulo'] = '%' . html_entity_decode($filtros['titulo'], ENT_QUOTES, 'UTF-8') . '%';
        }

        // 2. Artista
        if (!empty($filtros['artista_id'])) {
            $condicoes[] = "s.artista_id = :artista_id";
            $params[':artista_id'] = $filtros['artista_id'];
        }

        // 3. Tipo
        if (!empty($filtros['tipo_id'])) {
            $condicoes[] = "s.tipo_id = :tipo_id";
            $params[':tipo_id'] = $filtros['tipo_id'];
        }

        // 4. Situação (Mantendo sua regra de negócio de exibir/ocultar 4 e 5)
        if (!empty($filtros['situacao'])) {
            $condicoes[] = "s.situacao = :situacao";
            $params[':situacao'] = $filtros['situacao'];
        } else {
            $condicoes[] = "s.situacao NOT IN (4, 5)";
        }

        // 5. Formato
        if (isset($filtros['formato_id']) && $filtros['formato_id'] !== '') {
            if ($filtros['formato_id'] == -1) {
                $condicoes[] = "s.formato_id IS NULL";
            } else {
                $condicoes[] = "s.formato_id = :formato_id";
                $params[':formato_id'] = $filtros['formato_id'];
            }
        }

        // 6. Deleção (0 = Ativos, 1 = Lixeira, -1 = Todos)
        if (isset($filtros['deletado']) && $filtros['deletado'] != -1) {
            $condicoes[] = "s.deletado = :deletado";
            $params[':deletado'] = $filtros['deletado'];
        }

        return empty($condicoes) ? '1=1' : implode(' AND ', $condicoes);
    }

    /**
     * Busca detalhes completos de um álbum específico para o Modal
     */
    public function getDetalhes(int $id): array|false {
        $sql = "SELECT 
                    s.id, s.titulo, s.data_lancamento, s.criado_em, s.atualizado_em, s.capa_url,
                    a.nome AS nome_artista,
                    ta.descricao AS descricao_tipo,
                    st.descricao AS descricao_situacao,
                    f.descricao AS descricao_formato,
                    s.situacao AS situacao_id 
                FROM store AS s
                LEFT JOIN artistas AS a ON s.artista_id = a.id
                LEFT JOIN tipo_album AS ta ON s.tipo_id = ta.id
                LEFT JOIN situacao AS st ON s.situacao = st.id
                LEFT JOIN formatos AS f ON s.formato_id = f.id
                WHERE s.id = :id AND s.deletado = 0";
    
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

}
