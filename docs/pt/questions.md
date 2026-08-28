# 🧩 Blocos e Mini-Lições

## Blocos de Pergunta (amarelo "?")

Bater num bloco de pergunta por baixo abre um diálogo `core/modal` nativo do Moodle com uma
pergunta de múltipla escolha sorteada do banco interno da própria atividade (veja
[Gerenciando Perguntas](#usage)). A opção correta nunca é enviada ao cliente até uma resposta ser
submetida; o servidor a valida, registra no máximo uma resposta distinta correta por pergunta por
estudante, e devolve feedback imediato de certo/errado com a alternativa correta destacada.
Blocos respondidos escurecem permanentemente e contam para a cota de saída — nunca são
perguntados de novo.

## Blocos de Mini-Lição (azul "!")

Um professor pode preencher até três mini-lições curtas em texto puro (máximo de 400 caracteres
cada, sem formatação, imagens ou vídeo) no formulário de configurações da atividade. Cada lição
não vazia é mostrada pelo seu próprio bloco posicionado no mapa. Diferente de um bloco de
pergunta, um bloco de lição **nunca é gasto** — bater nele de novo sempre remostra o mesmo texto,
então um estudante pode revisitá-lo quantas vezes precisar.

## Vinculando uma Pergunta a uma Mini-Lição

Uma pergunta pode opcionalmente ser vinculada a uma das três mini-lições. Um bloco de pergunta
posicionado logo depois de um bloco de lição puxa primeiro das perguntas vinculadas a essa lição,
então a prática que um estudante vê é realmente sobre o que ele acabou de ler — não um sorteio
aleatório entre todas as perguntas da atividade. A seleção segue uma cadeia de fallback em quatro
camadas, então um bloco **nunca** fica sem pergunta mesmo que o tópico vinculado se esgote:

1. Uma pergunta não respondida vinculada ao tópico solicitado.
2. Qualquer pergunta vinculada ao tópico solicitado (mesmo já respondida) — é isso que mantém a
   pergunta de uma lição aparecendo em vez de cair para um conteúdo não relacionado, assim que
   sua única pergunta já foi respondida uma vez.
3. Qualquer pergunta não respondida no pool geral da atividade.
4. Qualquer pergunta da atividade.

Uma pergunta deixada sem tópico (o padrão) pertence ao pool geral e pode aparecer em qualquer
bloco de pergunta, vinculado ou não.

## Gerenciando Perguntas

Professores gerenciam as perguntas da atividade numa tela dedicada de **Gerenciar perguntas**
(link no topo da atividade, para quem tem a capability `mod/playerland:manage`): adicionar,
editar ou apagar perguntas de múltipla escolha, e opcionalmente escolher a qual mini-lição cada
uma está vinculada. Este é um banco interno por atividade, não o Banco de Questões do Moodle —
veja [Funcionalidades](#features) para isso como uma integração planejada.
