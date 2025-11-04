-- Tabela para armazenar as faixas de cada item da coleção.
-- O ideal é que estas faixas sejam ligadas ao item da COLEÇÃO e não do STORE,
-- pois o áudio (a prévia) é específico da sua cópia.
CREATE TABLE `faixas_colecao` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `colecao_id` INT NOT NULL COMMENT 'ID do álbum na tabela colecao (Mestre)',
    `numero_faixa` INT NOT NULL COMMENT 'Número sequencial da faixa no álbum (1, 2, 3...)',
    `titulo` VARCHAR(255) NOT NULL COMMENT 'Título da música',
    `duracao` VARCHAR(10) DEFAULT NULL COMMENT 'Duração da faixa (Ex: 3:45)',
    `audio_url` VARCHAR(512) DEFAULT NULL COMMENT 'Caminho ou URL do arquivo de áudio (MP3/FLAC) para preview.',
    PRIMARY KEY (`id`),
    -- A chave estrangeira amarra a faixa ao item da sua coleção.
    KEY `fk_faixas_colecao_colecao` (`colecao_id`),
    -- Índice para garantir a unicidade da faixa dentro do álbum
    UNIQUE KEY `idx_faixa_unica` (`colecao_id`, `numero_faixa`),
    CONSTRAINT `fk_faixas_colecao_colecao` FOREIGN KEY (`colecao_id`) REFERENCES `colecao` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Remove o campo obsoleto 'spotify_embed_url' da tabela mestre.
ALTER TABLE colecao
DROP COLUMN spotify_embed_url;