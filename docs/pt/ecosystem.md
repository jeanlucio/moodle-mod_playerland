# 🕹️ Ecossistema PlayerGames

O PlayerLand é estrelado pela raposa **Huddy** e faz parte do ecossistema de gamificação
**[PlayerGames](https://jeanlucio.github.io/playergames/)** para o Moodle. Nenhum dos plugins
abaixo é obrigatório — o PlayerLand funciona totalmente standalone hoje — mas uma integração de
economia com o PlayerHUD é uma direção planejada, veja [Funcionalidades](#features).

* **PlayerGames:** Hub central do ecossistema — XP em todo o site, temporadas, mini-jogos
  diários, e o Painel do Ecossistema que conecta todo plugin Player instalado.
  👉 [github.com/jeanlucio/moodle-local_playergames](https://github.com/jeanlucio/moodle-local_playergames)

* **PlayerHUD (Bloco):** XP, níveis, inventário, drops, missões, classes de RPG e ranking dentro
  de cada curso. Uma futura versão do PlayerLand pode conceder moedas/itens através dele em vez
  de construir sua própria economia — veja [Funcionalidades](#features).
  👉 [github.com/jeanlucio/moodle-block_playerhud](https://github.com/jeanlucio/moodle-block_playerhud)

* **PlayerPuzzle:** um módulo de atividade irmão (RPG Match-3 por turnos) que compartilha com o
  PlayerLand o mesmo padrão de carregamento dinâmico de script para embutir um motor de jogo de
  terceiros (Phaser) sem a corrida de uma tag `<script>` estática.
  👉 [github.com/jeanlucio/moodle-mod_playerpuzzle](https://github.com/jeanlucio/moodle-mod_playerpuzzle)

* **PlayerWords:** um módulo de atividade irmão (adivinhação de palavras) no mesmo ecossistema,
  usando o padrão de preferências de usuário do Moodle que o próprio overlay de primeiro
  carregamento do PlayerLand segue para estado entre dispositivos.
  👉 [github.com/jeanlucio/moodle-mod_playerwords](https://github.com/jeanlucio/moodle-mod_playerwords)

* **PlayerGroup:** deixa os estudantes formarem seus próprios grupos autonomamente direto da
  página da atividade — sem intervenção do professor.
  👉 [github.com/jeanlucio/moodle-mod_playergroup](https://github.com/jeanlucio/moodle-mod_playergroup)
