# 🔎 Divulgação de Serviço de Terceiros

**Não aplicável hoje.** O PlayerLand não chama nenhum serviço externo — toda pergunta vem do
banco de perguntas interno da própria atividade (veja [Blocos e Mini-Lições](#questions)), e
nenhuma requisição de rede sai do servidor como parte da jogabilidade. Nenhuma funcionalidade de
IA existe no plugin.

A única **biblioteca** de terceiros publicada (não um serviço) é o [Phaser](https://phaser.io/),
o motor do jogo — declarado em `thirdpartylibs.xml` e carregado dinamicamente da própria pasta
`javascript/` do plugin, nunca de um CDN.

## Planejado

Puxar perguntas do próprio Banco de Questões do Moodle em vez do banco interno por atividade é
uma ideia pós-v1 — veja [Funcionalidades](#features). Isso também não introduziria nenhum serviço
externo, já que o Banco de Questões é dado nativo do Moodle.
