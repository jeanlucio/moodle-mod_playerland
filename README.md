# Moodle Activity PlayerLand

![Moodle](https://img.shields.io/badge/Moodle-4.5%2B-orange?style=flat&logo=moodle&logoColor=white)
![License](https://img.shields.io/badge/License-GPLv3-blue?style=flat)
![Status](https://img.shields.io/badge/Status-Alpha-red?style=flat)
[![Latest Release](https://img.shields.io/github/v/release/jeanlucio/moodle-mod_playerland?style=flat)](https://github.com/jeanlucio/moodle-mod_playerland/releases)
[![PlayerGames Ecosystem](https://img.shields.io/badge/PlayerGames-Ecosystem-6f42c1?style=flat&logo=gamepad&logoColor=white)](https://jeanlucio.github.io/playergames/)
[![Author](https://img.shields.io/badge/by-Jean_Lucio-6f42c1?style=flat)](https://github.com/jeanlucio/)

[![Moodle Plugin CI](https://github.com/jeanlucio/moodle-mod_playerland/actions/workflows/ci.yml/badge.svg)](https://github.com/jeanlucio/moodle-mod_playerland/actions/workflows/ci.yml)
[![Last Commit](https://img.shields.io/github/last-commit/jeanlucio/moodle-mod_playerland?style=flat)](https://github.com/jeanlucio/moodle-mod_playerland/commits)
[![Open Issues](https://img.shields.io/github/issues/jeanlucio/moodle-mod_playerland?style=flat)](https://github.com/jeanlucio/moodle-mod_playerland/issues)

> ⚠️ **This plugin is under active development.** It is not yet published on the Moodle Plugin
> Directory. Some features described in the full documentation are planned and not yet
> implemented.

[English](#english) | [Português](#português)

---

## English

**PlayerLand** is a Moodle activity module that embeds a playable **2D platformer game** directly
inside a course. Students control **Huddy** the fox — running, jumping, dashing, climbing and
exploring a level — and answer questions by hitting **question blocks**, with topic-linked
**mini-lesson blocks** placed right where a student needs them.

📚 **[Full documentation](https://jeanlucio.github.io/moodle-mod_playerland/)** — features
(implemented vs. planned), movement and level design, how blocks and mini-lessons work,
accessibility, security, the automated test suite with coverage, and third-party service
disclosure.

### 🔎 Third-party Service Disclosure

Not applicable today. PlayerLand does not call any external service — every question comes from
the activity's own internal question bank.

Full disclosure:
[Third-party Service Disclosure](https://jeanlucio.github.io/moodle-mod_playerland/#third-party).

### 📦 Requirements

| Component | Version |
|-----------|---------|
| Moodle    | 4.5 – 5.2 |
| PHP       | 8.2+    |

### 🛠️ Installation & Configuration

> ⚠️ This plugin is not yet published on the Moodle Plugin Directory. Install manually from this repository.

1. Download the `.zip` file or clone this repository.
2. Extract the folder into your Moodle `mod/` directory.
3. Rename the folder to `playerland` (if necessary).
   Final path:
   `your-moodle/mod/playerland/`
4. Visit **Site administration > Notifications** to complete installation.
5. Add a PlayerLand activity to any course, pick a map, and set how many questions unlock the
   exit.

### 🆘 Support

Found a bug or have a question? Open an issue on the
[issue tracker](https://github.com/jeanlucio/moodle-mod_playerland/issues).

### 📄 License

This project is licensed under the **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

### 👤 Maintainer

Maintained by [Jean Lúcio](https://github.com/jeanlucio).

[⬆️ Back to top](#english)

---

## Português

> ⚠️ **Este plugin está em desenvolvimento ativo.** Ainda não foi publicado no Diretório de Plugins do Moodle. Algumas funcionalidades descritas na documentação completa são planejadas e ainda não estão implementadas.

O **PlayerLand** é um módulo de atividade do Moodle que embute um **jogo de plataforma 2D**
jogável diretamente dentro de um curso. Os estudantes controlam a raposa **Huddy** — correndo,
pulando, arrancando, escalando e explorando uma fase — e respondem perguntas ao bater em
**blocos de pergunta**, com **blocos de mini-lição** vinculados por tópico posicionados
exatamente onde o estudante precisa deles.

📚 **[Documentação completa](https://jeanlucio.github.io/moodle-mod_playerland/pt.html)** —
funcionalidades (implementadas vs. planejadas), movimento e design de fases, como blocos e
mini-lições funcionam, acessibilidade, segurança, a suíte de testes automatizados com cobertura,
e a divulgação de serviço de terceiros.

### 🔎 Divulgação de Serviço de Terceiros

Não aplicável hoje. O PlayerLand não chama nenhum serviço externo — toda pergunta vem do banco
de perguntas interno da própria atividade.

Divulgação completa:
[Divulgação de Serviço de Terceiros](https://jeanlucio.github.io/moodle-mod_playerland/pt.html#third-party).

### 📦 Requisitos

| Componente | Versão |
|------------|--------|
| Moodle     | 4.5 – 5.2 |
| PHP        | 8.2+   |

### 🛠️ Instalação e Configuração

> ⚠️ Este plugin ainda não está publicado no Diretório de Plugins do Moodle. Instale manualmente a partir deste repositório.

1. Baixe o arquivo `.zip` ou clone este repositório.
2. Extraia na pasta `mod/` do seu Moodle.
3. Renomeie para `playerland` (se necessário).
   Caminho final:
   `seu-moodle/mod/playerland/`
4. Acesse **Administração do site > Notificações** para concluir a instalação.
5. Adicione uma atividade PlayerLand a qualquer curso, escolha um mapa, e defina quantas
   perguntas destravam a saída.

### 🆘 Suporte

Encontrou um bug ou tem alguma dúvida? Abra uma issue no
[rastreador de issues](https://github.com/jeanlucio/moodle-mod_playerland/issues).

### 📄 Licença

Este projeto é licenciado sob a **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

### 👤 Mantenedor

Mantido por [Jean Lúcio](https://github.com/jeanlucio).
