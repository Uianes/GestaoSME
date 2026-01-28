# GestaoSME

Aplicacao web em PHP para gestao de sistemas da SME, com login, painel de administrador
e controle de acesso por sistema.

## Principais recursos
- Autenticacao por usuario/senha
- Sidebar com menu dinamico por permissao
- Modo Administrador para liberar acesso aos sistemas
- Modulo Patrimonio integrado via iframe

## Requisitos
- PHP 8.x
- MySQL/MariaDB
- Servidor web (Apache/Nginx)

## Como rodar
1) Configure o banco em `config/config.php`
2) Aponte o servidor web para a pasta do projeto
3) Acesse `app.php`

## Banco de dados
O projeto usa a tabela `usuarios` (campos principais como `matricula`, `nome`, `email`,
`senha`, `ativo`, `ADM`).

Para controle de acesso por sistema, crie a tabela:
```sql
CREATE TABLE usuarios_sistemas (
  matricula INT NOT NULL,
  sistema VARCHAR(64) NOT NULL,
  PRIMARY KEY (matricula, sistema)
);
```

## Permissoes
- `ADM = 1` no usuario libera o Modo Administrador
- Usuarios nao admin acessam somente os sistemas liberados em `usuarios_sistemas`

## Estrutura basica
- `app.php`: roteamento simples por `?page=...`
- `views/`: paginas
- `componentes/`: layout (sidebar, nav, etc)
- `auth/`: login, sessao e permissoes
- `config/`: configuracao e links dos sistemas
- `patrimonio/`: modulo Patrimonio

## Sistemas disponiveis
Os sistemas ficam em `config/links.php`. O painel admin usa essa lista para liberar
acesso por usuario.

## Observacoes
- Se `usuarios_sistemas` nao existir, o sistema libera o acesso por padrao.
- Para aplicar novas permissoes, o usuario deve relogar.
