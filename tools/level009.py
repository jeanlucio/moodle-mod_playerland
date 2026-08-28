#!/usr/bin/env python3
"""Level 9 - Ruinas do Bosque.

The "everything combined" level before the boss: crawl, moving and crumbling
platforms, the vine shaft (O Poco), the crank gate, a diving eagle. Seeded from
the old map_level3 prototype and kept as a draft to refine.

    python3 tools/level009.py        # writes assets/maps/map_level009.json
"""

from levelkit import build, write

NAME = 'map_level009.json'

SIGN_TEXTS = [
    'Setas movem. ESPACO pula. SHIFT rola por baixo.',
    'Segure BAIXO para agachar e passar raspando.',
    'Desca a hera com BAIXO. As perguntas estao no fundo.',
    'Encoste numa parede no ar e pule de novo para subir.',
    'Fique sob a manivela e segure CIMA para abrir o portao.',
]

CRAWL_MOUNDS = [
    (24, 8, 14),   # Room 1
    (84, 6, 15),   # Room 2
]

# ROOM 1 - A Trilha entrance: crawl mound (stamped by CRAWL_MOUNDS), small pit.
ROOM_1 = [
    "", "", "", "", "", "", "", "", "", "", "", "", "", "", "",  # 0-14
    "            ccc",  # 15
    "            ?       ?",  # 16
    "",  # 17
    "   @",  # 18
    "      1       o      o     cc",  # 19
    "===================================    ===",  # 20  pit cols 35-38
]

# ROOM 2 - O Desfiladeiro: moving platform + crumbling bridge over two pits.
ROOM_2 = [
    "", "", "", "", "", "", "", "",  # 0-7
    "                              e",  # 8
    "", "", "", "", "", "", "", "",  # 9-16
    "                     C  C  C",  # 17
    "",  # 18
    "  S   P                          2         c      ",  # 19
    "=====         =====          =====================",  # 20  pits 5-13, 19-28
]

# ROOM 3 - O Poco: drop the vine shaft into a tunnel of questions, climb out.
ROOM_3 = [
    "", "", "", "", "", "", "", "", "",  # 0-8
    "      H",  # 9
    "      H",  # 10
    "      H",  # 11
    "      H",  # 12
    "      H                          H",  # 13
    "      H                          H",  # 14
    "      H                          H",  # 15
    "      H                          H",  # 16
    "      H                          H",  # 17
    "      H                          H",  # 18
    "  3   H                          H",  # 19
    "===== H                          H==========",  # 20
    "##### H                          H##########",  # 21
    "##### H        ?           ?     H##########",  # 22
    "##### H                          H##########",  # 23
    "##### H                          H##########",  # 24
    "##### Hg    f     xxxxx      f   H##########",  # 25
    "############################################",  # 26
    "############################################",  # 27
]

# ROOM 4 - A Porta da Guarda: climb to the crank, open the gate, small arena.
ROOM_4 = [
    "", "", "", "", "", "", "", "",  # 0-7
    "              W          e         ",  # 8
    "              W                   ",  # 9
    "              W                   ",  # 10
    "     K   5    W                   ",  # 11
    "    H=====    W                   ",  # 12
    "    H         W                   ",  # 13
    "    H         W                   ",  # 14
    "    H         W          g        ",  # 15
    "    H         W         ---       ",  # 16
    "    H         W                   ",  # 17
    "    H         W                   ",  # 18
    " 4  H         D   S    o     o    ",  # 19
    "==================================",  # 20
]

# ROOM 5 - A Toca: crumbling bridge over spikes with a diving eagle, then out.
ROOM_5 = [
    "", "", "", "", "", "", "", "",  # 0-7
    "                e                   ",  # 8
    "", "", "", "", "", "",  # 9-14
    "     S                              ",  # 15
    "   ======  C   C   C   C    ? g     ",  # 16
    "                             -----  ",  # 17
    "",  # 18
    "            x x x x x x          E  ",  # 19
    "====================================",  # 20
]

ROOMS = [ROOM_1, ROOM_2, ROOM_3, ROOM_4, ROOM_5]
HOLLOW_ROOMS = {2}


def make():
    return build(ROOMS, hollow_rooms=HOLLOW_ROOMS,
                 crawl_mounds=CRAWL_MOUNDS, sign_texts=SIGN_TEXTS)


if __name__ == '__main__':
    write(NAME, make())
