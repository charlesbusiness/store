<?php

/*
|--------------------------------------------------------------------------
| Weight units
|--------------------------------------------------------------------------
|
| The base unit is one gram. "Half" and "quarter" exist here too, so a sack
| sold in halves or quarters converts cleanly.
|
*/

return [

    ['mg', 'Milligram', 'mg', 'mg', 0.001, true, false, 10],
    ['g', 'Gram', 'g', 'g', 1, true, false, 20],
    ['half_g', 'Half gram', 'half g', 'half g', 0.5, true, true, 30],
    ['quarter_g', 'Quarter gram', 'quarter g', 'quarter g', 0.25, true, true, 40],
    ['oz', 'Ounce', 'oz', 'oz', 28.349523, true, false, 50],
    ['kg', 'Kilogram', 'kg', 'kg', 1000, true, false, 60],
    ['lb', 'Pound', 'lb', 'lb', 453.59237, true, false, 70],
    ['half_kg', 'Half kilogram', 'half kg', 'half kg', 500, true, true, 80],
    ['quarter_kg', 'Quarter kilogram', 'quarter kg', 'quarter kg', 250, true, true, 90],
    ['bag_1kg', '1kg Bag', '1kg bag', '1kg bags', 1000, false, false, 100],
    ['bag_5kg', '5kg Bag', '5kg bag', '5kg bags', 5000, false, false, 110],
    ['bag_10kg', '10kg Bag', '10kg bag', '10kg bags', 10000, false, false, 120],
    ['bag_25kg', '25kg Bag', '25kg bag', '25kg bags', 25000, false, false, 130],
    ['sack_50kg', '50kg Sack', '50kg sack', '50kg sacks', 50000, false, false, 140],
    ['cwt', 'Cwt', 'cwt', 'cwt', 50802, true, false, 150],
    ['quintal', 'Quintal', 'quintal', 'quintals', 100000, false, false, 160],
    ['tonne', 'Tonne', 't', 'tonnes', 1000000, true, false, 170],

];
