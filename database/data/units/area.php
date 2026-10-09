<?php

/*
|--------------------------------------------------------------------------
| Area units
|--------------------------------------------------------------------------
|
| The base unit is one square centimetre, for land and surface measurements.
|
| Columns: code, label, symbol, plural, multiplier, is_decimal, is_fraction, sort
|
*/

return [
    ['sqcm', 'Square centimetre', 'sqcm', 'sqcm', 1, true, false, 10],
    ['sqm', 'Square metre', 'sqm', 'sqm', 10000, true, false, 20],
    ['sqft', 'Square foot', 'sqft', 'sqft', 929.03, true, false, 30],
    ['sqyd', 'Square yard', 'sqyd', 'sqyd', 8361.27, true, false, 40],
    ['acre', 'Acre', 'acre', 'acres', 404685.64, true, false, 50],
    ['hectare', 'Hectare', 'ha', 'ha', 1000000, true, false, 60],
];
