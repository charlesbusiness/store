<?php

/*
|--------------------------------------------------------------------------
| Length units
|--------------------------------------------------------------------------
|
| The base unit is one centimetre. Fabric is the usual reason a petty trader
| needs these; "yarda" is the Nigerian name for a yard.
|
| Columns: code, label, symbol, plural, multiplier, is_decimal, is_fraction, sort
|
*/

return [
    ['mm', 'Millimetre', 'mm', 'mm', 0.1, true, false, 10],
    ['cm', 'Centimetre', 'cm', 'cm', 1, true, false, 20],
    ['m', 'Metre', 'm', 'm', 100, true, false, 30],
    ['km', 'Kilometre', 'km', 'km', 100000, true, false, 40],
    ['inch', 'Inch', 'in', 'in', 2.54, true, false, 50],
    ['foot', 'Foot', 'ft', 'ft', 30.48, true, false, 60],
    ['yard', 'Yard', 'yd', 'yd', 91.44, true, false, 70],
    ['yarda', 'Yarda', 'yarda', 'yarda', 91.44, true, false, 80],
    ['six_yard', '6-Yard', '6-yard', '6-yards', 548.64, false, false, 90],
    ['twelve_yard', '12-Yard', '12-yard', '12-yards', 1097.28, false, false, 100],
    ['bolt', 'Bolt of fabric', 'bolt', 'bolts', 4572, false, false, 110],
    ['half_m', 'Half metre', 'half m', 'half m', 50, true, true, 120],
];
