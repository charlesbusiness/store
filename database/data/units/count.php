<?php

/*
|--------------------------------------------------------------------------
| Count-family units
|--------------------------------------------------------------------------
|
| The base unit is one piece. Discrete containers all sit at a multiplier of 1,
| because stock is tracked per item: a "bag" of rice and a "bottle" of oil are
| each one unit of their own item, never added together. Their physical size
| belongs to the item as pack_size, which keeps this catalogue finite.
|
| Columns: code, label, symbol, plural, multiplier, is_decimal, is_fraction, sort
|
*/

return [

    // Whole pieces
    ['pcs', 'Piece', 'pcs', 'pieces', 1, false, false, 10],
    ['half', 'Half', 'half', 'halves', 0.5, true, true, 20],
    ['quarter', 'Quarter', 'quarter', 'quarters', 0.25, true, true, 30],
    ['third', 'Third', 'third', 'thirds', 0.333333, true, true, 40],
    ['pair', 'Pair', 'pair', 'pairs', 2, false, false, 50],
    ['trio', 'Trio', 'trio', 'trios', 3, false, false, 60],
    ['set', 'Set', 'set', 'sets', 1, false, false, 70],

    // Dozens and other trading counts
    ['half_dozen', 'Half Dozen', 'half dozen', 'half dozens', 6, false, false, 80],
    ['dozen', 'Dozen', 'dozen', 'dozens', 12, false, false, 90],
    ['bakers_dozen', "Baker's Dozen", "baker's dozen", "baker's dozens", 13, false, false, 100],
    ['score', 'Score', 'score', 'scores', 20, false, false, 110],
    ['great_score', 'Great Score', 'great score', 'great scores', 240, false, false, 120],
    ['gross', 'Gross', 'gross', 'gross', 144, false, false, 130],
    ['hundred', 'Hundred', '100', 'hundreds', 100, false, false, 140],

    // Containers, all equivalent to one piece of their own item
    ['bag', 'Bag', 'bag', 'bags', 1, false, false, 200],
    ['sack', 'Sack', 'sack', 'sacks', 1, false, false, 210],
    ['carton', 'Carton', 'carton', 'cartons', 1, false, false, 220],
    ['crate', 'Crate', 'crate', 'crates', 1, false, false, 230],
    ['basket', 'Basket', 'basket', 'baskets', 1, false, false, 240],
    ['bucket', 'Bucket', 'bucket', 'buckets', 1, false, false, 250],
    ['bowl', 'Bowl', 'bowl', 'bowls', 1, false, false, 260],
    ['basin', 'Basin', 'basin', 'basins', 1, false, false, 270],
    ['box', 'Box', 'box', 'boxes', 1, false, false, 280],
    ['tray', 'Tray', 'tray', 'trays', 1, false, false, 290],
    ['tin', 'Tin', 'tin', 'tins', 1, false, false, 300],
    ['packet', 'Packet', 'packet', 'packets', 1, false, false, 310],
    ['pack', 'Pack', 'pack', 'packs', 1, false, false, 320],
    ['sachet', 'Sachet', 'sachet', 'sachets', 1, false, false, 330],
    ['roll', 'Roll', 'roll', 'rolls', 1, false, false, 340],
    ['bundle', 'Bundle', 'bundle', 'bundles', 1, false, false, 350],
    ['bale', 'Bale', 'bale', 'bales', 1, false, false, 360],
    ['bottle', 'Bottle', 'bottle', 'bottles', 1, false, false, 370],
    ['keg', 'Keg', 'keg', 'kegs', 1, false, false, 380],
    ['jerrycan', 'Jerrycan', 'jerrycan', 'jerrycans', 1, false, false, 390],
    ['ladle', 'Ladle', 'ladle', 'ladles', 1, false, false, 400],
    ['pot', 'Pot', 'pot', 'pots', 1, false, false, 410],
    ['trunk', 'Trunk', 'trunk', 'trunks', 1, false, false, 420],
    ['kilometer', 'Kilometre of fabric', 'km', 'km', 1, false, false, 430],
    ['yard_of_fabric', 'Yard of fabric', 'yd', 'yd', 1, false, false, 440],
    ['piece_of_fabric', 'Piece of fabric', 'pc', 'pcs', 1, false, false, 450],
    ['block', 'Block', 'block', 'blocks', 1, false, false, 460],
    ['tank', 'Tank', 'tank', 'tanks', 1, false, false, 470],
    ['keg_of_palm_oil', 'Keg of palm oil', 'keg', 'kegs', 1, false, false, 480],

];
