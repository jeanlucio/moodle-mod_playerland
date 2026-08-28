#!/usr/bin/env python3
"""Shared machinery for authoring PlayerLand levels as ASCII room art.

Each ``levelNNN.py`` module imports this, defines its rooms plus a few
level-specific constants, and calls ``build(...)``. ``build_all.py`` regenerates
every level at once.

Each character in a room string is either a tile painted into "Tile Layer 1" or
a point / rectangle marker emitted onto the "objects" layer that PlayScene turns
into behaviour.

Tile GIDs (firstgid = 1, so GID = tile-id + 1):
    29  grass-topped solid ground   (id 28, collides)
    79  plain dirt fill             (id 78, collides)
    36  wooden beam, one-way        (id 35, collides + oneway)
    418 stone brick (ruins wall)    (id 417, collides)
    412 vine (climbable visual)     (id 411, no collision)

Tile chars:  =  grass    #  dirt    -  beam (one-way)    W  brick    H  vine/ladder
Marker chars:
    @ spawn      E exit        ? question    L lesson (n=1)   c cherry   g gem
    o opossum    e eagle       f frog        x spike up   v spike down
    P platform (horizontal)    p platform (vertical)      C crumbling platform
    B crate      K crank       D door        S checkpoint
    1-9 sign, text taken from the level's SIGN_TEXTS list (1 -> index 0)

Rooms normally supply rows 0..FLOOR_ROW and let the builder fill solid ground
below the floor line. A room listed in ``hollow_rooms`` draws every row itself.

This directory is versioned but excluded from the published plugin zip via
.gitattributes.
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

# char -> (kind, anchor, extra properties). anchor: 'bottom' sits the object on
# the cell floor, 'center' floats it in the middle of the cell.
MARKERS = {
    '@': ('spawn', 'bottom', {}),
    'E': ('exit', 'bottom', {}),
    '?': ('question', 'center', {}),
    'L': ('lesson', 'center', {'n': 1}),
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
}
SIGN_CHARS = set('123456789')


def _room_char(room, r, c):
    if r < len(room) and c < len(room[r]):
        return room[r][c]
    return ' '


def _assemble(rooms, hollow_rooms):
    """Stitches rooms side by side, auto-filling ground below non-hollow floors."""
    widths = []
    for index, room in enumerate(rooms):
        if index in hollow_rooms:
            widths.append(max((len(x.rstrip()) for x in room), default=0))
        else:
            widths.append(len(room[FLOOR_ROW].rstrip()))
    grid = [[' '] * sum(widths) for _ in range(ROOM_HEIGHT)]

    base = 0
    for index, (room, width) in enumerate(zip(rooms, widths)):
        hollow = index in hollow_rooms
        for r in range(ROOM_HEIGHT):
            for c in range(width):
                ch = _room_char(room, r, c)
                if ch != ' ':
                    grid[r][base + c] = ch
                elif not hollow and r > FLOOR_ROW and grid[FLOOR_ROW][base + c] == '=':
                    grid[r][base + c] = '#'
        base += width

    return [''.join(row) for row in grid]


def _prop_entry(name, value):
    kind = 'string'
    if isinstance(value, bool):
        kind = 'bool'
    elif isinstance(value, int):
        kind = 'int'
    elif isinstance(value, float):
        kind = 'float'
    return {'name': name, 'type': kind, 'value': value}


def build(rooms, *, hollow_rooms=(), crawl_mounds=(), sign_texts=()):
    """Turns a list of ASCII rooms into a Tiled map dict.

    rooms        list of rooms, each a list of row strings
    hollow_rooms iterable of room indices that draw every row themselves
    crawl_mounds list of (global_col, span, top_row): a solid mound with a
                 one-tile crawl slot at row 19, stamped straight into the tiles
                 so its columns cannot drift
    sign_texts   text for the numbered sign chars (char '1' -> sign_texts[0])
    """
    hollow_rooms = frozenset(hollow_rooms)
    grid = _assemble(rooms, hollow_rooms)
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
            if ch in SIGN_CHARS:
                index = int(ch) - 1
                text = sign_texts[index] if index < len(sign_texts) else ''
                kind, anchor, props = 'sign', 'bottom', {'text': text}
            elif ch in MARKERS:
                kind, anchor, props = MARKERS[ch]
            else:
                continue
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
                obj['properties'] = [_prop_entry(k, v) for k, v in props.items()]
            objects.append(obj)
            next_id += 1

    # Stamp the crawl mounds: solid from top_row down to row 18, slot at row 19.
    for col, span, top in crawl_mounds:
        for r in range(top, FLOOR_ROW - 1):
            for c in range(col, col + span):
                if 0 <= c < width:
                    data[r * width + c] = GID_DIRT

    # Cap every exposed top of solid dirt with a grass tile so a mound or a
    # platform reads as terrain instead of a flat maroon slab.
    for row in range(1, height):
        for col in range(width):
            if data[row * width + col] == GID_DIRT and data[(row - 1) * width + col] == 0:
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
                {'id': 28, 'properties': [_prop_entry('collides', True)]},
                {'id': 78, 'properties': [_prop_entry('collides', True)]},
                {'id': 35, 'properties': [_prop_entry('collides', True), _prop_entry('oneway', True)]},
                {'id': 417, 'properties': [_prop_entry('collides', True)]},
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


def maps_dir():
    here = os.path.dirname(os.path.abspath(__file__))
    return os.path.normpath(os.path.join(here, '..', 'assets', 'maps'))


def write(name, tilemap):
    """Writes a built tilemap to assets/maps/<name>."""
    out = os.path.join(maps_dir(), name)
    with open(out, 'w', encoding='utf-8') as handle:
        json.dump(tilemap, handle, separators=(',', ':'))
        handle.write('\n')
    print('wrote %s (%dx%d, %d objects)' % (
        name, tilemap['width'], tilemap['height'],
        len(tilemap['layers'][1]['objects'])))


def render(tilemap, cols=None):
    """Returns an ASCII picture of a built tilemap, for eyeballing in a review."""
    w = tilemap['width']
    h = tilemap['height']
    d = tilemap['layers'][0]['data']
    legend = {GID_GRASS: '"', GID_DIRT: '#', GID_BEAM: '-', GID_BRICK: 'W', GID_VINE: 'H'}
    glyphs = {
        'spawn': '@', 'exit': 'E', 'question': '?', 'cherry': 'c', 'gem': 'g',
        'opossum': 'o', 'eagle': 'e', 'frog': 'f', 'spike': 'x', 'platform': 'P',
        'crumble': 'C', 'crate': 'B', 'crank': 'K', 'door': 'D', 'checkpoint': 'S',
        'sign': 'i', 'lesson': 'L',
    }
    grid = [[legend.get(d[r * w + c], '.') for c in range(w)] for r in range(h)]
    for obj in tilemap['layers'][1]['objects']:
        if obj['type'] == 'ladder':
            continue
        cc = int(obj['x'] // TILE)
        rr = int((obj['y'] - 1) // TILE)
        if 0 <= rr < h and 0 <= cc < w:
            grid[rr][cc] = glyphs.get(obj['type'], '*')
    limit = cols or w
    return '\n'.join('%2d %s' % (r, ''.join(row[:limit])) for r, row in enumerate(grid))
