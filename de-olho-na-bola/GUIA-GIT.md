# Guia de Git e GitHub

## O que versionar

Este diretório é a raiz do projeto. Versione os arquivos-fonte e a documentação
mantendo a estrutura existente:

- `wp-content/plugins/bola-em-campo-core/` — plugin WordPress.
- `wp-content/themes/bola-em-campo/` — tema WordPress.
- `plataforma-central/` — aplicação Laravel; mantenha o código, migrations,
  testes e arquivos de exemplo de configuração.
- `exemplos/`, `estrutura-site/`, `instalar-site.py` e os guias da raiz.

Não copie o projeto para uma segunda pasta apenas para criar o repositório.
Trabalhe nesta raiz e mantenha tema, plugin e plataforma central no mesmo
repositório para que as versões compatíveis permaneçam juntas.

## O que não enviar

O `.gitignore` da raiz exclui configurações locais e arquivos gerados, incluindo:

- `.env`, `wp-config.php`, chaves, senhas, tokens e certificados privados.
- uploads, cache, backups e bancos de dados do WordPress ou locais.
- `.venv`, `vendor`, `node_modules`, builds e arquivos temporários.
- ZIPs de instalação em `instalar-pelo-painel/`.

Os arquivos-fonte são a referência. Gere ou obtenha os ZIPs de instalação
separadamente quando for preparar uma instalação WordPress. Antes de cada commit,
revise a lista de arquivos staged e confirme que não há segredos nem dados reais.

## Criar o repositório com GitHub Desktop

1. Abra o GitHub Desktop e entre na sua conta.
2. Se esta pasta ainda não estiver sob Git, escolha **File > Add Local Repository**
   e selecione a pasta do projeto. Se solicitado, crie o repositório local nela.
3. Revise **Changes**. Confirme que `plataforma-central/.env`, bancos de dados,
   uploads, dependências e ZIPs não aparecem.
4. Faça o commit inicial com uma mensagem como `Organiza documentação e projeto`.
5. Selecione **Publish repository**. Use um repositório privado enquanto não
   tiver revisado se o conteúdo pode ser público.

Se o repositório remoto já existir, clone-o e coloque os arquivos do projeto
na raiz clonada, ou adicione o remoto existente ao repositório local correto.
Não publique um segundo repositório por engano.

## Enviar alterações

1. Revise os arquivos alterados em **Changes**.
2. Confirme que nenhum segredo, arquivo de ambiente ou dado de usuário está staged.
3. Faça um commit com uma mensagem curta que descreva a alteração.
4. Use **Push origin** para enviar os commits ao GitHub.

Commit salva uma versão no histórico local; push envia commits ao remoto. O Git
não sincroniza o banco, uploads ou conteúdo de um site WordPress.

## Fluxo opcional pelo terminal

Execute os comandos na raiz do projeto. Só inicialize um repositório se esta
pasta ainda não tiver histórico Git:

```powershell
git init -b main
git add .
git status --short
git diff --cached --stat
git commit -m "Organiza documentação e projeto"
```

Após conferir o commit e confirmar a URL do repositório remoto:

```powershell
git remote add origin <URL-DO-REPOSITORIO>
git push -u origin main
```

Para atualizações futuras, use `git status`, revise o diff, faça commit e então
execute `git push`. Se `git remote -v` já listar um remoto, não o adicione de
novo: confirme que ele aponta para o repositório esperado.
