# Plataforma central — WordPress Automatizado

Aplicação Laravel independente do WordPress. O MVP cadastra instalações WordPress independentes, testa autenticação/permissões e envia conteúdo ao destino somente como rascunho. O plugin De Olho na Bola Core funciona como conector idempotente; o tema permanece instalado nos sites de destino.

## Requisitos

- PHP 8.3 ou superior, com `curl`, `mbstring`, `openssl`, `pdo_mysql` e `fileinfo`.
- Composer 2.
- MySQL 8 ou MariaDB compatível.
- Node.js não é necessário para esta interface MVP.

No XAMPP, inicie Apache e MySQL. Crie um banco vazio chamado `wordpress_automatizado` no phpMyAdmin.

## Instalação local

No terminal, entre nesta pasta e execute:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Edite `.env` com `APP_URL`, banco e credenciais locais. A chave `APP_KEY` cifra as senhas de aplicação armazenadas no banco: proteja-a fora do banco e não a troque sem migrar as credenciais cifradas.

Depois:

```powershell
php artisan migrate
php artisan platform:create-admin
```

O comando inicial pede nome, e-mail e senha sem gravá-los em arquivos ou logs. Ele só pode criar o primeiro usuário.

Em dois terminais, execute o servidor web e o worker:

```powershell
php artisan serve
php artisan queue:work --tries=3 --timeout=30
```

Abra `http://127.0.0.1:8000`. A fila de banco deve permanecer em execução para concluir testes de conexão e envios. Os jobs podem ser repetidos sem criar rascunhos remotos duplicados.

## Conectar um WordPress

1. Instale e ative o plugin De Olho na Bola Core **3.2.0 ou superior** no site de destino. Multisite é opcional.
2. Crie um usuário técnico por site com a permissão de editor necessária para criar rascunhos; conceda permissões de mídia/categorias apenas se forem necessárias.
3. Crie uma senha de aplicação para esse usuário no painel WordPress. Use HTTPS em sites públicos. HTTP é permitido apenas quando a plataforma roda em `APP_ENV=local` ou `testing`.
4. No painel central, abra **Sites > Adicionar site**, informe URL base, usuário e senha de aplicação. A credencial é cifrada e não é exibida novamente.
5. Aguarde o job de teste e confirme as permissões exibidas antes de enviar conteúdo.

Não reutilize senha pessoal de administrador. Em produção, o conector rejeita HTTP, endereços privados/reservados e redirecionamentos; em desenvolvimento local permite endereços privados, necessários ao XAMPP. Não exponha o XAMPP local à internet.

## Enviar um rascunho

Abra **Rascunhos > Novo rascunho**, selecione um site conectado e salve título/conteúdo. O texto fica inicialmente só na plataforma. A ação **Enviar como rascunho** usa a API REST do plugin e mantém o post remoto com status `draft`; publicação pública não é automática.

## API

- `GET /api/v1/health` retorna a versão da API.
- O painel usa sessão autenticada e proteção CSRF. As ações sensíveis derivam o espaço de trabalho do usuário autenticado, nunca de um `organization_id` recebido do navegador.

## Estado do MVP

Implementa o início da Fase 1 do documento técnico: cadastro e revalidação de sites, isolamento por espaço de trabalho, credenciais cifradas, jobs de teste/envio, rascunhos remotos idempotentes e trilha de auditoria. Ainda não implementa ingestão central de RSS, geração de IA central, comparação de versões, calendário, notificações, backup/restore operacional, MFA ou cobrança. O fluxo editorial que já existia no plugin continua local a cada WordPress até as fases seguintes.

## Testes

```powershell
php artisan test
```

Os testes automatizados usam SQLite em memória e HTTP fakes; não fazem chamadas a sites reais nem publicam conteúdo.
