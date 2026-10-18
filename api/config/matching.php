<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Confidence thresholds (0-100)
    |--------------------------------------------------------------------------
    |
    | At or above "auto" a receipt line is matched without asking. Between "suggest" and "auto"
    | the best product is suggested for the user to confirm. Below "suggest" the user picks or
    | creates the product. Never auto-match when the runner-up is within "ambiguity_margin".
    |
    */

    'auto' => (int) env('MATCH_AUTO', 95),
    'suggest' => (int) env('MATCH_SUGGEST', 70),
    'ambiguity_margin' => 5,

    /*
    |--------------------------------------------------------------------------
    | Receipt abbreviations
    |--------------------------------------------------------------------------
    |
    | Whole words expanded before matching, so "PAULS FC MILK" reads as "PAULS FULL CREAM MILK".
    |
    */

    'abbreviations' => [
        'FC' => 'FULL CREAM',
        'F/C' => 'FULL CREAM',
        'F/CREAM' => 'FULL CREAM',
        'LITE' => 'LIGHT',
        'LT' => 'LIGHT',
        'R/FAT' => 'REDUCED FAT',
        'W/MEAL' => 'WHOLEMEAL',
        'WMEAL' => 'WHOLEMEAL',
        'M/GRAIN' => 'MULTIGRAIN',
        'CHOC' => 'CHOCOLATE',
        'STRAWB' => 'STRAWBERRY',
        'STRAW' => 'STRAWBERRY',
        'VAN' => 'VANILLA',
        'ORIG' => 'ORIGINAL',
        'NAT' => 'NATURAL',
        'S/FREE' => 'SUGAR FREE',
        'NO SUGAR' => 'ZERO SUGAR',
        'G/FREE' => 'GLUTEN FREE',
        'L/FREE' => 'LACTOSE FREE',
        'F/RANGE' => 'FREE RANGE',
        'BTL' => 'BOTTLE',
        'PKT' => 'PACKET',
        'WW' => 'WOOLWORTHS',
    ],

    /*
    |--------------------------------------------------------------------------
    | Variant markers
    |--------------------------------------------------------------------------
    |
    | Words that make a different product. If one side has a marker the other doesn't, they
    | are not the same product ("COCA COLA ZERO SUGAR" vs "COCA COLA"; "LIGHT MILK" vs
    | "FULL CREAM MILK") and the score is capped well below the suggest threshold.
    |
    */

    'variant_markers' => [
        'FULL CREAM', 'LIGHT', 'SKIM', 'REDUCED FAT', 'LACTOSE FREE', 'A2',
        'ZERO SUGAR', 'ZERO', 'DIET', 'SUGAR FREE', 'MAX',
        'GLUTEN FREE', 'DECAF', 'ORGANIC', 'FREE RANGE', 'CAGED', 'BARN LAID',
        'UNSALTED', 'SALTED', 'CRUNCHY', 'SMOOTH',
        'WHOLEMEAL', 'WHITE', 'MULTIGRAIN', 'SOURDOUGH', 'RYE',
        'CHOCOLATE', 'STRAWBERRY', 'VANILLA', 'BANANA', 'MANGO', 'LEMON', 'LIME', 'ORANGE', 'APPLE',
        'BEEF', 'CHICKEN', 'PORK', 'LAMB', 'VEGETABLE',
    ],

];
