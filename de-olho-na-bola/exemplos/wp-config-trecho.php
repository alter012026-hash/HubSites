<?php
// Cole ANTES da linha "That's all, stop editing!" do wp-config.php.
// Gere o token com:  python3 -c "import secrets; print(secrets.token_urlsafe(48))"
define( 'BEC_REST_TOKEN', 'REPLACE_WITH_PRIVATE_TOKEN' );
define( 'BEC_REST_AUTHOR_ID', 1 );               // usuário que será autor dos rascunhos da API
// define( 'BEC_OPENAI_API_KEY', 'sua-chave-openai' );
// define( 'BEC_GROQ_API_KEY', 'sua-chave-groqcloud' );
// define( 'BEC_API_FOOTBALL_KEY', 'sua-chave' ); // opcional: dados de classificação e jogos
