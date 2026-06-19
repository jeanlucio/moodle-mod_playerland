# Moodle Activity PlayerLand

[![Moodle Plugin CI](https://github.com/jeanlucio/moodle-mod_playerland/actions/workflows/ci.yml/badge.svg)](https://github.com/jeanlucio/moodle-mod_playerland/actions/workflows/ci.yml)
![Moodle](https://img.shields.io/badge/Moodle-4.5%2B-orange?style=flat-square&logo=moodle&logoColor=white)
![License](https://img.shields.io/badge/License-GPLv3-blue?style=flat-square)
![Status](https://img.shields.io/badge/Status-Alpha-yellow?style=flat-square)
[![PlayerGames Ecosystem](https://img.shields.io/badge/PlayerGames-Ecosystem-6f42c1?style=flat-square&logo=gamepad&logoColor=white)](https://moodle.org/plugins/browse.php?list=contributor&id=3970322)

[English](#english) | [Português](#português)

---

## English

**PlayerLand** is a Moodle activity module that embeds a playable **2D platformer game** directly inside a course. Students control **Huddy** the fox — running, jumping and exploring a level — and answer questions by hitting **question blocks**, just like the classic platformers they grew up with.

The game runs on the **Phaser** game engine inside the activity page. Levels are designed visually with the free **Tiled** editor, and all the educational logic (questions, answers, progress) is handled by Moodle web services.

> ⚠️ **Status: Alpha.** PlayerLand is under active development. Core gameplay and the question-block loop are working; expect changes and additional features (multiple levels, grading, sound) in upcoming releases.

---

### ✨ Features

* 🦊 **Platformer gameplay:** Run, jump and fall through a tile-based level with smooth camera follow and parallax backgrounds.
* ❓ **Question blocks:** Hit a block from below to open a Moodle question modal. Answer with instant **correct/incorrect feedback**, the right option highlighted, and a *Continue* button. Answered blocks stay on the map as spent (turn brown).
* 🍒 **Collectibles & score:** Gather cherries (10 pts) and gems (50 pts); a live score HUD updates as you play.
* 🦫 **Enemies:** Patrolling opossums that turn at walls and platform edges. **Stomp** them from above to defeat them; a **side touch** hurts the player (hurt animation + brief invulnerability before respawn).
* 🕳️ **Hazards & respawn:** Fall into a pit and respawn automatically; a manual respawn key (**R**) is available too.
* 🏁 **Level goal:** Reach the exit flag to complete the level, see your final score, and restart.
* 🗺️ **Tiled level design:** Build levels by painting tiles and dropping object markers (`cherry`, `gem`, `question`, `opossum`, `exit`) in Tiled. Collision travels with the map via a tile property — no code editing to create new levels.
* 🧩 **Internal question bank:** Manage multiple-choice questions per activity through a dedicated *Manage questions* screen.
* ⚡ **Web-service driven:** Question fetching, answer checking and progress saving use Moodle's `core/ajax`.
* 🌍 **Bilingual:** English and Brazilian Portuguese language packs.

---

### 🎮 Controls

| Action | Key |
|--------|-----|
| Move left / right | Arrow keys or `A` / `D` |
| Jump | `Spacebar` |
| Respawn (escape if stuck) | `R` |
| Restart after finishing | `Enter` |

---

### 🧱 How it works (level design)

A PlayerLand level is a pair of files in `assets/maps/`:

* a **Tiled map** (`map.json`) — the grid of tiles plus an **object layer** named `objects` holding the markers;
* the shared **tileset image** (`assets/environment/tileset.png`) — the "box of pieces".

In Tiled you **paint** the terrain and mark which tiles are solid with a boolean `collides` property (read in code via `setCollisionByProperty`). Game elements are **point markers** on the `objects` layer, identified by name/type:

| Marker | Becomes |
|--------|---------|
| `cherry` / `gem` | Collectible worth 10 / 50 points |
| `question` | A question block (hit from below) |
| `opossum` | A patrolling enemy |
| `exit` | The level-completion flag |

The teacher (or a designer) places markers in Tiled; the behaviour lives in the plugin's AMD modules. No code changes are needed to ship a new level.

---

### 🎓 Educational Purpose

PlayerLand turns formative questions into a game loop, designed to:

* Increase engagement through play
* Reward correct answers with in-game progress
* Make quizzes feel like exploration rather than a form
* Support younger and game-oriented learners

---

### 🕹️ PlayerGames Ecosystem

PlayerLand stars **Huddy**, the mascot of the **PlayerGames** gamification ecosystem, and is designed to sit alongside the other plugins:

* **PlayerHUD (Block):** XP, levels, inventory and ranking HUD inside courses.
  👉 https://github.com/jeanlucio/moodle-block_playerhud
* **PlayerGroup (Activity):** Students form their own groups autonomously.
  👉 https://github.com/jeanlucio/moodle-mod_playergroup

---

### 📦 Requirements

| Component | Version |
|-----------|---------|
| Moodle    | 4.5+    |
| PHP       | 8.1+    |

To **author levels** you also need the free [Tiled Map Editor](https://www.mapeditor.org/).

---

### 🛠️ Installation

1. Download the `.zip` file or clone this repository.
2. Extract the folder into your Moodle `mod/` directory.
3. Rename the folder to `playerland` (if necessary).
   Final path: `your-moodle/mod/playerland/`
4. Visit **Site administration > Notifications** to complete installation.
5. Add a **PlayerLand** activity to a course.

---

### 📖 Usage

1. Add the **PlayerLand** activity to your course.
2. As a teacher, open **Manage questions** (link shown at the top of the activity) and add your multiple-choice questions.
3. Students open the activity and play: hitting a question block shows one of your questions.
4. (Optional) Design your own levels in **Tiled** and replace `assets/maps/map.json`.

---

### 🔐 Security & Compliance

* Capability-based access control (`mod/playerland:view`, `mod/playerland:manage`)
* Instance-scoped web services with context validation and capability checks
* Question and option text formatted server-side with `format_string`
* Moodle External API compliant

---

### 🎨 Credits & Third-Party Assets

* **Game engine:** [Phaser](https://phaser.io/) — MIT License (declared in `thirdpartylibs.xml`).
* **Pixel art:** *Sunny Land* asset pack by **Ansimuz** (public-domain license). See the bundled license files for terms. You are free to replace the art with any compatible CC0/CC-BY platformer tileset.

---

## 📄 License

This project is licensed under the **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

---

## Português

O **PlayerLand** é um módulo de atividade do Moodle que coloca um **jogo de plataforma 2D** jogável diretamente dentro de um curso. Os estudantes controlam a raposa **Huddy** — correndo, pulando e explorando uma fase — e respondem perguntas ao bater nos **blocos de pergunta**, no melhor estilo dos plataformas clássicos.

O jogo roda no motor **Phaser** na página da atividade. As fases são desenhadas visualmente no editor gratuito **Tiled**, e toda a lógica educacional (perguntas, respostas, progresso) é tratada por web services do Moodle.

> ⚠️ **Status: Alpha.** O PlayerLand está em desenvolvimento ativo. A jogabilidade principal e o ciclo dos blocos de pergunta já funcionam; espere mudanças e novos recursos (múltiplas fases, nota, som) nas próximas versões.

---

### ✨ Funcionalidades

* 🦊 **Jogabilidade de plataforma:** Corra, pule e caia por uma fase baseada em tiles, com câmera que segue o jogador e fundos em parallax.
* ❓ **Blocos de pergunta:** Bata num bloco por baixo para abrir o modal de pergunta do Moodle. Responda com **feedback imediato de certo/errado**, a alternativa correta destacada e um botão *Continuar*. Blocos respondidos permanecem no mapa como "gastos" (ficam marrons).
* 🍒 **Colecionáveis e placar:** Junte cerejas (10 pts) e gemas (50 pts); um placar (HUD) atualiza em tempo real.
* 🦫 **Inimigos:** Opossums que patrulham e viram nas paredes e bordas de plataforma. **Pule em cima** para derrotá-los; **encostar de lado** causa dano (animação de dano + breve invulnerabilidade antes de renascer).
* 🕳️ **Perigos e renascimento:** Caia num buraco e renasça automaticamente; também há uma tecla de renascimento manual (**R**).
* 🏁 **Objetivo da fase:** Chegue na bandeira de saída para concluir a fase, ver a pontuação final e recomeçar.
* 🗺️ **Design de fases no Tiled:** Crie fases pintando tiles e soltando marcadores de objetos (`cherry`, `gem`, `question`, `opossum`, `exit`) no Tiled. A colisão viaja junto com o mapa via uma propriedade de tile — sem editar código para criar novas fases.
* 🧩 **Banco de perguntas interno:** Gerencie perguntas de múltipla escolha por atividade numa tela dedicada de *Gerenciar perguntas*.
* ⚡ **Movido a web services:** Busca de perguntas, verificação de respostas e salvamento de progresso usam o `core/ajax` do Moodle.
* 🌍 **Bilíngue:** Pacotes de idioma em inglês e português do Brasil.

---

### 🎮 Controles

| Ação | Tecla |
|------|-------|
| Mover esquerda / direita | Setas ou `A` / `D` |
| Pular | `Barra de espaço` |
| Renascer (escapar se travar) | `R` |
| Recomeçar ao terminar | `Enter` |

---

### 🧱 Como funciona (design de fases)

Uma fase do PlayerLand é um par de arquivos em `assets/maps/`:

* um **mapa do Tiled** (`map.json`) — a grade de tiles mais uma **camada de objetos** chamada `objects` com os marcadores;
* a **imagem do tileset** (`assets/environment/tileset.png`) — a "caixa de peças".

No Tiled você **pinta** o terreno e marca quais tiles são sólidos com uma propriedade booleana `collides` (lida no código via `setCollisionByProperty`). Os elementos do jogo são **marcadores de ponto** na camada `objects`, identificados por nome/tipo:

| Marcador | Vira |
|----------|------|
| `cherry` / `gem` | Colecionável de 10 / 50 pontos |
| `question` | Um bloco de pergunta (batido por baixo) |
| `opossum` | Um inimigo que patrulha |
| `exit` | A bandeira de conclusão da fase |

O professor (ou um designer) posiciona os marcadores no Tiled; o comportamento vive nos módulos AMD do plugin. Nenhuma alteração de código é necessária para publicar uma nova fase.

---

### 🎓 Finalidade Educacional

O PlayerLand transforma perguntas formativas em um ciclo de jogo, projetado para:

* Aumentar o engajamento por meio da brincadeira
* Recompensar respostas corretas com progresso no jogo
* Fazer os quizzes parecerem exploração, e não um formulário
* Apoiar estudantes mais jovens e orientados a jogos

---

### 🕹️ Ecossistema PlayerGames

O PlayerLand é estrelado pelo **Huddy**, mascote do ecossistema de gamificação **PlayerGames**, e foi pensado para conviver com os outros plugins:

* **PlayerHUD (Bloco):** HUD de XP, níveis, inventário e ranking dentro dos cursos.
  👉 https://github.com/jeanlucio/moodle-block_playerhud
* **PlayerGroup (Atividade):** Estudantes formam seus próprios grupos de forma autônoma.
  👉 https://github.com/jeanlucio/moodle-mod_playergroup

---

### 📦 Requisitos

| Componente | Versão |
|------------|--------|
| Moodle     | 4.5+   |
| PHP        | 8.1+   |

Para **criar fases** você também precisa do editor gratuito [Tiled Map Editor](https://www.mapeditor.org/).

---

### 🛠️ Instalação

1. Baixe o arquivo `.zip` ou clone este repositório.
2. Extraia a pasta no diretório `mod/` do seu Moodle.
3. Renomeie para `playerland` (se necessário).
   Caminho final: `seu-moodle/mod/playerland/`
4. Acesse **Administração do site > Notificações** para concluir a instalação.
5. Adicione uma atividade **PlayerLand** a um curso.

---

### 📖 Como Usar

1. Adicione a atividade **PlayerLand** ao seu curso.
2. Como professor, abra **Gerenciar perguntas** (link no topo da atividade) e cadastre suas perguntas de múltipla escolha.
3. Os estudantes abrem a atividade e jogam: ao bater num bloco, aparece uma de suas perguntas.
4. (Opcional) Desenhe suas próprias fases no **Tiled** e substitua o `assets/maps/map.json`.

---

### 🔐 Segurança e Conformidade

* Controle de acesso por capabilities (`mod/playerland:view`, `mod/playerland:manage`)
* Web services escopados por instância, com validação de contexto e checagem de capability
* Texto de pergunta e alternativas formatado no servidor com `format_string`
* Compatível com a API externa do Moodle

---

### 🎨 Créditos e Recursos de Terceiros

* **Motor do jogo:** [Phaser](https://phaser.io/) — Licença MIT (declarada em `thirdpartylibs.xml`).
* **Pixel art:** pacote *Sunny Land* de **Ansimuz** (licença de domínio público). Consulte os arquivos de licença incluídos. Você pode substituir a arte por qualquer tileset de plataforma compatível (CC0/CC-BY).

---

## 📄 Licença

Este projeto é licenciado sob a **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio
