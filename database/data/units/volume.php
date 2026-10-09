<?php

/*
|--------------------------------------------------------------------------
| Volume units
|--------------------------------------------------------------------------
|
| The base unit is one millilitre. Sizes follow the US gallon and pint; adjust
| them from the Units page if you trade in local measures.
|
*/

return [

    ['ml', 'Millilitre', 'ml', 'ml', 1, true, false, 10],
    ['cl', 'Centilitre', 'cl', 'cl', 10, true, false, 20],
    ['dl', 'Decilitre', 'dl', 'dl', 100, true, false, 30],
    ['l', 'Litre', 'l', 'l', 1000, true, false, 40],
    ['half_l', 'Half litre', 'half l', 'half l', 500, true, true, 50],
    ['quarter_l', 'Quarter litre', 'quarter l', 'quarter l', 250, true, true, 60],
    ['teaspoon', 'Teaspoon', 'tsp', 'tsp', 5, true, false, 70],
    ['tablespoon', 'Tablespoon', 'tbsp', 'tbsp', 15, true, false, 80],
    ['cup', 'Cup', 'cup', 'cups', 236.588, true, false, 90],
    ['pint', 'Pint', 'pint', 'pints', 473.176, true, false, 100],
    ['quart', 'Quart', 'qt', 'qts', 946.353, true, false, 110],
    ['gallon', 'Gallon', 'gal', 'gal', 3785.41, true, false, 120],
    ['jerrycan_20l', '20L Jerrycan', '20L jerrycan', '20L jerrycans', 20000, false, false, 130],
    ['jug', 'Jug', 'jug', 'jugs', 1000, false, false, 140],
    ['scoop', 'Scoop', 'scoop', 'scoops', 1000, false, false, 150],

];
