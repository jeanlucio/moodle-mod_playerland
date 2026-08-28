# ♿ Acessibilidade

## O que Está Implementado Hoje

* ⌨️ **Jogo só de teclado:** toda ação — mover, pular, arrancada, agachar, escalar, tela cheia —
  tem uma tecla vinculada. Nenhum mouse é necessário para jogar uma fase.
* 🗨️ **Diálogos acessíveis:** tanto o desafio de pergunta quanto o texto da mini-lição usam o
  componente `core/modal` do próprio Moodle, em vez de um overlay feito à mão — captura de foco,
  `aria-modal` e fechar com ESC vêm todos do core.
* 👋 **O overlay "Como jogar" do primeiro carregamento** é um elemento real
  `role="dialog" aria-modal="true" aria-labelledby="..."`, e seu botão de dispensar recebe foco
  programático assim que aparece.

## Uma Limitação Conhecida e Honesta

A jogabilidade de plataforma em si — posição do jogador, pontuação, perigos, movimento de
inimigos — é renderizada num elemento `<canvas>` e **não** é exposta para tecnologia assistiva
hoje. Essa é uma limitação compartilhada pela maioria dos plataformas baseados em navegador, e um
problema materialmente mais difícil do que espelhar um tabuleiro por turnos: um plataforma em
tempo real e em movimento não tem um design de camada-paralela-acessível estabelecido como um
tabuleiro Match-3 estático tem. Essa camada não existe para o PlayerLand ainda, e não está
desenhada no momento — sinalizado aqui honestamente em vez de deixado sem menção.

O HUD do jogo (contadores de cereja/gema, o botão de tela cheia) é desenhado como objetos de
texto/gráficos do Phaser, não elementos DOM reais, então nenhum deles carrega `aria-label` ou é
alcançável via `Tab`.

## Planejado

* ⏱️ Um **modo prática** opcional (sem timer, pressão de perigos reduzida) é recomendado
  internamente para estudantes afetados por pressão de tempo, ansiedade ou exigência motora —
  ainda não implementado, veja [Funcionalidades](#features).

`speechSynthesis` não é usado pelo plugin.
