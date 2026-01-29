CREATE TABLE eventos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(150) NOT NULL,
  descricao TEXT NULL,
  inicio DATETIME NOT NULL,
  fim DATETIME NULL,
  all_day TINYINT NOT NULL DEFAULT 0,
  local VARCHAR(255) NULL,
  criado_por INT NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE evento_destinatarios (
  evento_id INT NOT NULL,
  matricula INT NOT NULL,
  PRIMARY KEY (evento_id, matricula)
);

CREATE TABLE evento_usuarios (
  evento_id INT NOT NULL,
  matricula INT NOT NULL,
  PRIMARY KEY (evento_id, matricula)
);

CREATE TABLE evento_unidades (
  evento_id INT NOT NULL,
  id_unidade INT NOT NULL,
  PRIMARY KEY (evento_id, id_unidade)
);

CREATE TABLE notificacoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  matricula INT NOT NULL,
  evento_id INT NULL,
  titulo VARCHAR(200) NOT NULL,
  tipo VARCHAR(30) NOT NULL DEFAULT 'evento',
  criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  lida_em DATETIME NULL,
  INDEX idx_notificacoes_matricula (matricula),
  INDEX idx_notificacoes_evento (evento_id)
);
