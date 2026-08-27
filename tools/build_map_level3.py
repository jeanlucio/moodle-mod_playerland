#!/usr/bin/env python3
"""Builds assets/maps/map_level3.json ("Ruinas do Bosque") from ASCII room art.

Run from the plugin root:

    python3 tools/build_map_level3.py

The level is authored here as ASCII so the design stays reviewable and regenerable.
Each character is either a tile painted into "Tile Layer 1" or a point/rectangle
marker emitted onto the "objects" layer that PlayScene turns into behaviour.

Tile GIDs (firstgid = 1, so GID = tile-id + 1):
    29  grass-topped solid ground   (id 28, collides)
    79  plain dirt fill             (id 78, collides)
    36  wooden beam, one-way        (id 35, collides + oneway)
    418 stone brick (ruins wall)    (id 417, collides)
    412 vine (climbable visual)     (id 411, no collision)

Rooms 0-3 supply rows 0..FLOOR_ROW and let the builder fill solid ground below
the floor line; the poco room is "hollow" and draws all 28 rows itself.

This file is versioned but excluded from the published plugin zip via .gitattributes.
"""

import json
import os

TILE = 16
ROOM_HEIGHT = 28
FLOOR_ROW = 20

GID_GRASS = 29
GID_DIRT = 79
GID_BEAM = 36
GID_BRICK = 418
GID_VINE = 412

TILE_CHARS = {
    '=': GID_GRASS,
    '#': GID_DIRT,
    '-': GID_BEAM,
    'W': GID_BRICK,
    'H': GID_VINE,
}

SIGN_TEXT = [
    'Setas movem. ESPACO pula. SHIFT rola por baixo.',
    'Segure BAIXO para agachar e passar raspando.',
    'Desca a hera com BAIXO. As perguntas estao no fundo.',
    'Encoste numa parede no ar e pule de novo para subir.',
    'Fique sob a manivela e segure CIMA para abrir o portao.',
]

# char -> (kind, anchor, extra properties). anchor: 'bottom' sits on the cell
# floor, 'center' floats in the middle of the cell.
MARKERS = {
    '@': ('spawn', 'bottom', {}),
    'E': ('exit', 'bottom', {}),
    '?': ('question', 'center', {}),
    'c': ('cherry', 'center', {}),
    'g': ('gem', 'center', {}),
    'o': ('opossum', 'bottom', {}),
    'e': ('eagle', 'center', {}),
    'f': ('frog', 'bottom', {}),
    'x': ('spike', 'bottom', {'dir': 'up'}),
    'v': ('spike', 'center', {'dir': 'down'}),
    'P': ('platform', 'bottom', {'dx': 128, 'speed': 45}),
    'p': ('platform', 'bottom', {'dy': 80, 'speed': 40}),
    'C': ('crumble', 'center', {}),
    'B': ('crate', 'bottom', {}),
    'K': ('crank', 'bottom', {'target': 'gate1'}),
    'D': ('door', 'bottom', {'name': 'gate1'}),
    'S': ('checkpoint', 'bottom', {}),
    'i': ('sign', 'bottom', {'text': SIGN_TEXT[0]}),
    'j': ('sign', 'bottom', {'text': SIGN_TEXT[1]}),
    'k': ('sign', 'bottom', {'text': SIGN_TEXT[2]}),
    'l': ('sign', 'bottom', {'text': SIGN_TEXT[3]}),
    'm': ('sign', 'bottom', {'text': SIGN_TEXT[4]}),
}

# --- Rooms ------------------------------------------------------------------
# Row indices are printed for authoring convenience; the builder ignores them.

# ROOM 1 - A Trilha: run, jump a small pit, crawl through a mound at floor level.
# The crawl mound itself is stamped by CRAWL_MOUNDS (below) so its columns cannot
# drift out of alignment; here we only place the markers.
ROOM_1 = [
    "", "", "", "", "", "", "", "", "", "", "", "", "", "", "",  # 0-14
    "            ccc",  # 15
    "            ?       ?",  # 16
    "",  # 17
    "   @",  # 18
    "      i       o      o     cc",  # 19  crawl slot sits at cols 24-31
    "===================================    ===",  # 20  pit cols 35-38
]

# ROOM 2 - O Desfiladeiro: a moving platform and a crumbling bridge over two
# pits, a patrolling eagle, then a crawl mound.
ROOM_2 = [
    "", "", "", "", "", "", "", "",  # 0-7
    "                              e",  # 8
    "", "", "", "", "", "", "", "",  # 9-16
    "                     C  C  C",  # 17
    "",  # 18
    "  S   P                          j         c      ",  # 19  crawl slot at cols 42-47
    "=====         =====          =====================",  # 20  pits 5-13, 19-28
]

# ROOM 3 - O Poco: drop down the vine shaft into a tunnel of questions and
# spikes, then climb the vine ladder on the far side back up. Hollow room.
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
    "  k   H                          H",  # 19
    "===== H                          H==========",  # 20  shaft mouth cols 5-8
    "##### H                          H##########",  # 21
    "##### H        ?           ?     H##########",  # 22
    "##### H                          H##########",  # 23
    "##### H                          H##########",  # 24
    "##### Hg    f     xxxxx      f   H##########",  # 25
    "############################################",  # 26  tunnel floor
    "############################################",  # 27
]

# ROOM 4 - A Porta da Guarda: climb the vine ladder to the crank, pull it to
# open the gate, then a short arena.
ROOM_4 = [
    "", "", "", "", "", "", "", "",  # 0-7
    "              W          e         ",  # 8
    "              W                   ",  # 9
    "              W                   ",  # 10
    "     K   m    W                   ",  # 11
    "    H=====    W                   ",  # 12
    "    H         W                   ",  # 13
    "    H         W                   ",  # 14
    "    H         W          g        ",  # 15
    "    H         W         ---       ",  # 16
    "    H         W                   ",  # 17
    "    H         W                   ",  # 18
    " l  H         D   S    o     o    ",  # 19
    "==================================",  # 20
]

# ROOM 5 - A Toca: a rest ledge, a crumbling bridge over spikes with a diving
# eagle, the last question, then the burrow.
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

# Solid mounds with a one-tile crawl slot at row 19, stamped straight into the
# tile data so their columns can never drift. Each is (global_col, span, top_row):
# tiles fill top_row..18, row 19 stays open, and the floor/fill below closes it
# into a tunnel carved through a hill.
CRAWL_MOUNDS = [
    (24, 8, 14),   # Room 1
    (84, 6, 15),   # Room 2
]


def room_char(room, r, c):
    """Char at (r, c) of a room, space when unspecified."""
    if r < len(room) and c < len(room[r]):
        return room[r][c]
    return ' '


def assemble():
    """Stitches the rooms side by side, auto-filling ground below non-hollow floors."""
    # A non-hollow room's width is defined by its floor row; a hollow room by its
    # widest line. Content that runs past that width is clipped, never widening it.
    widths = []
    for index, room in enumerate(ROOMS):
        if index in HOLLOW_ROOMS:
            widths.append(max((len(x.rstrip()) for x in room), default=0))
        else:
            widths.append(len(room[FLOOR_ROW].rstrip()))
    grid = [[' '] * sum(widths) for _ in range(ROOM_HEIGHT)]

    base = 0
    for index, (room, width) in enumerate(zip(ROOMS, widths)):
        hollow = index in HOLLOW_ROOMS
        for r in range(ROOM_HEIGHT):
            for c in range(width):
                ch = room_char(room, r, c)
                if ch != ' ':
                    grid[r][base + c] = ch
                elif not hollow and r > FLOOR_ROW and grid[FLOOR_ROW][base + c] == '=':
                    grid[r][base + c] = '#'
        base += width

    return [''.join(row) for row in grid]


def prop_entry(name, value):
    kind = 'string'
    if isinstance(value, bool):
        kind = 'bool'
    elif isinstance(value, int):
        kind = 'int'
    elif isinstance(value, float):
        kind = 'float'
    return {'name': name, 'type': kind, 'value': value}


def build():
    grid = assemble()
    height = len(grid)
    width = len(grid[0])

    data = [0] * (width * height)
    objects = []
    next_id = 1
    ladder_cells = set()

    for row, line in enumerate(grid):
        for col, ch in enumerate(line):
            if ch == ' ':
                continue
            if ch in TILE_CHARS:
                data[row * width + col] = TILE_CHARS[ch]
                if ch == 'H':
                    ladder_cells.add((col, row))
                continue
            if ch not in MARKERS:
                continue
            kind, anchor, props = MARKERS[ch]
            obj = {
                'id': next_id,
                'name': kind,
                'type': kind,
                'x': col * TILE + TILE // 2,
                'y': row * TILE + (TILE if anchor == 'bottom' else TILE // 2),
                'width': 0,
                'height': 0,
                'point': True,
                'visible': True,
                'rotation': 0,
            }
            if props:
                obj['properties'] = [prop_entry(k, v) for k, v in props.items()]
            objects.append(obj)
            next_id += 1

    # Stamp the crawl mounds.
    for col, span, top in CRAWL_MOUNDS:
        for r in range(top, FLOOR_ROW - 1):
            for c in range(col, col + span):
                if 0 <= c < width:
                    data[r * width + c] = GID_DIRT

    # Cap every exposed top of solid dirt with a grass tile so a floating or
    # cut-into block reads as terrain instead of a flat maroon slab.
    for row in range(1, height):
        for col in range(width):
            here = data[row * width + col]
            above = data[(row - 1) * width + col]
            if here == GID_DIRT and above == 0:
                data[row * width + col] = GID_GRASS

    # Vertical runs of ladder cells become rectangle markers.
    by_column = {}
    for (col, row) in ladder_cells:
        by_column.setdefault(col, []).append(row)
    for col, rows in by_column.items():
        rows.sort()
        start = prev = rows[0]
        for row in rows[1:] + [None]:
            if row is None or row != prev + 1:
                objects.append({
                    'id': next_id,
                    'name': 'ladder',
                    'type': 'ladder',
                    'x': col * TILE,
                    'y': start * TILE,
                    'width': TILE,
                    'height': (prev - start + 1) * TILE,
                    'visible': True,
                    'rotation': 0,
                })
                next_id += 1
                if row is not None:
                    start = row
            if row is not None:
                prev = row

    return {
        'compressionlevel': -1,
        'infinite': False,
        'orientation': 'orthogonal',
        'renderorder': 'right-down',
        'type': 'map',
        'version': '1.10',
        'tiledversion': '1.10.2',
        'width': width,
        'height': height,
        'tilewidth': TILE,
        'tileheight': TILE,
        'nextlayerid': 3,
        'nextobjectid': next_id,
        'tilesets': [{
            'firstgid': 1,
            'name': 'tileset',
            'image': '../environment/tileset.png',
            'imagewidth': 400,
            'imageheight': 368,
            'tilewidth': 16,
            'tileheight': 16,
            'tilecount': 575,
            'columns': 25,
            'margin': 0,
            'spacing': 0,
            'tiles': [
                {'id': 28, 'properties': [prop_entry('collides', True)]},
                {'id': 78, 'properties': [prop_entry('collides', True)]},
                {'id': 35, 'properties': [prop_entry('collides', True), prop_entry('oneway', True)]},
                {'id': 417, 'properties': [prop_entry('collides', True)]},
            ],
        }],
        'layers': [
            {
                'id': 1,
                'name': 'Tile Layer 1',
                'type': 'tilelayer',
                'x': 0,
                'y': 0,
                'width': width,
                'height': height,
                'opacity': 1,
                'visible': True,
                'data': data,
            },
            {
                'id': 2,
                'name': 'objects',
                'type': 'objectgroup',
                'opacity': 1,
                'visible': True,
                'x': 0,
                'y': 0,
                'draworder': 'topdown',
                'objects': objects,
            },
        ],
    }


def main():
    here = os.path.dirname(os.path.abspath(__file__))
    out = os.path.normpath(os.path.join(here, '..', 'assets', 'maps', 'map_level3.json'))
    tilemap = build()
    with open(out, 'w', encoding='utf-8') as handle:
        json.dump(tilemap, handle, separators=(',', ':'))
        handle.write('\n')
    print('wrote %s (%dx%d, %d objects)' % (
        out, tilemap['width'], tilemap['height'], len(tilemap['layers'][1]['objects'])))


if __name__ == '__main__':
    main()
