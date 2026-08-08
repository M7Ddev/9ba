<?php

/**
 * Coffee domain configuration.
 *
 * These are the only values the recipe endpoints accept. Keeping them in config
 * means the validation rules, the brew-ratio guard rails and the prompt all read
 * from one source of truth.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Brew ratio limits (parts of water to 1 part coffee)
    |--------------------------------------------------------------------------
    | Used by BrewRatioCalculator to reject nonsense the model might send — for
    | example a 1:40 espresso. `fallback` is substituted when the requested ratio
    | is out of range, and the substitution is reported back to the model.
    */
    'methods' => [
        'V60' => ['min' => 12.0, 'max' => 18.0, 'fallback' => 16.0],
        'French Press' => ['min' => 12.0, 'max' => 18.0, 'fallback' => 15.0],
        'Espresso' => ['min' => 1.5,  'max' => 3.0,  'fallback' => 2.0],
        'Moka Pot' => ['min' => 7.0,  'max' => 12.0, 'fallback' => 10.0],
        'AeroPress' => ['min' => 10.0, 'max' => 18.0, 'fallback' => 14.0],
    ],

    'roasts' => ['Light', 'Medium', 'Dark'],

    /*
    |--------------------------------------------------------------------------
    | Serving style
    |--------------------------------------------------------------------------
    | "Iced" here means Japanese iced / flash brew: brewing hot directly onto
    | ice, rather than chilling a finished hot brew.
    |
    | The important arithmetic — and the thing people get wrong — is that the
    | coffee dose is set by the TOTAL liquid, while only part of that total is
    | poured as hot water. The rest is ice, which melts into the cup. Brewing a
    | full 300 ml over 150 g of ice yields 450 ml of weak coffee.
    |
    | ice_fraction is the share of the total that arrives as ice.
    | 0.4 is the widely used starting point: 300 ml total -> 120 g ice + 180 ml
    | of brew water.
    |
    | Espresso is deliberately excluded: an iced shot is pulled normally and
    | poured over ice, so the brew ratio itself does not change.
    */
    'serve_styles' => ['Hot', 'Iced'],

    'iced' => [
        'ice_fraction' => 0.4,

        // Methods where ice replaces part of the brew water.
        'dilution_methods' => ['V60', 'French Press', 'AeroPress'],

        // Methods served over ice without altering the brew itself.
        'over_ice_methods' => ['Espresso', 'Moka Pot'],

        // Ice to put in the glass for an over-ice serve, in grams.
        'serving_ice_g' => 120,
    ],

    'tastes' => ['Strong', 'Balanced', 'Light', 'Less sour', 'Less bitter'],

    /** Accepted water / yield range in millilitres. */
    'amount' => ['min' => 20, 'max' => 2000],

    /*
    |--------------------------------------------------------------------------
    | Bean origins
    |--------------------------------------------------------------------------
    | Reference data behind the `get_bean_profile` tool. The model is told to
    | look a bean up here rather than recall origin characteristics from memory,
    | so the brewing advice is grounded in one auditable table.
    |
    | density        -> how hard the bean is; dense beans need finer grind + hotter water
    | acidity / body -> what to expect in the cup
    | base_temp_c    -> starting water temperature before process/roast adjustment
    | base_ratio     -> starting brew ratio before taste-preference adjustment
    */
    'origins' => [
        'Colombia' => [
            'density' => 'medium-high',
            'acidity' => 'medium, soft citric',
            'body' => 'medium to full',
            'typical_notes' => ['caramel', 'red apple', 'cocoa', 'orange'],
            'base_temp_c' => 93,
            'base_ratio' => 16.0,
            'note' => 'Well-rounded and forgiving; a good baseline bean.',
        ],
        'Ethiopia' => [
            'density' => 'high',
            'acidity' => 'high, bright and tea-like',
            'body' => 'light to medium',
            'typical_notes' => ['jasmine', 'bergamot', 'blueberry', 'stone fruit'],
            'base_temp_c' => 94,
            'base_ratio' => 16.0,
            'note' => 'High-grown and dense: needs hotter water and a finer grind to open up, '
                .'otherwise it reads thin and sour.',
        ],
        'Yemen' => [
            'density' => 'high but irregular',
            'acidity' => 'medium-high, winey',
            'body' => 'heavy and syrupy',
            'typical_notes' => ['dried fruit', 'dark chocolate', 'cardamom', 'wild spice'],
            'base_temp_c' => 92,
            'base_ratio' => 15.0,
            'note' => 'Heirloom beans of uneven size, almost always natural processed. Brew slightly '
                .'cooler and stronger to carry the body without turning harsh.',
        ],
        'Brazil' => [
            'density' => 'low',
            'acidity' => 'low',
            'body' => 'heavy',
            'typical_notes' => ['peanut', 'milk chocolate', 'toasted nut'],
            'base_temp_c' => 91,
            'base_ratio' => 15.0,
            'note' => 'Soft, low-grown bean. Cooler water avoids pulling bitterness from the roast.',
        ],
        'Kenya' => [
            'density' => 'high',
            'acidity' => 'very high, blackcurrant-like',
            'body' => 'full',
            'typical_notes' => ['blackcurrant', 'tomato', 'grapefruit', 'brown sugar'],
            'base_temp_c' => 94,
            'base_ratio' => 16.0,
            'note' => 'Intense acidity; needs full extraction or it tastes aggressively sharp.',
        ],
        'Other' => [
            'density' => 'medium',
            'acidity' => 'medium',
            'body' => 'medium',
            'typical_notes' => ['balanced sweetness'],
            'base_temp_c' => 93,
            'base_ratio' => 16.0,
            'note' => 'Unknown origin: neutral SCA starting point, adjust by taste.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Grinders
    |--------------------------------------------------------------------------
    | Reference data behind the `get_grind_setting` tool. "Medium-fine" is not
    | something anyone can act on; "22 clicks" is. Each grinder lists a starting
    | click window per brew method, counted from fully closed (burrs touching)
    | unless noted.
    |
    | These are widely-used starting points, not gospel — burr wear and bean
    | density shift them, which is why the tool returns a range and tells the
    | model to present it as a starting point.
    |
    | `step` is how many clicks equal one perceptible step of adjustment, used
    | when a bean profile asks for "one step coarser".
    */
    'grinders' => [
        'Comandante C40' => [
            'step' => 2,
            'note' => 'Counted from closed. 30 clicks per rotation.',
            'settings' => [
                'Espresso' => [8, 12],
                'Moka Pot' => [12, 16],
                'AeroPress' => [16, 22],
                'V60' => [20, 26],
                'French Press' => [28, 34],
            ],
        ],
        '1Zpresso JX' => [
            'step' => 3,
            'note' => 'Counted from closed. 40 clicks per rotation, external adjustment.',
            'settings' => [
                'Espresso' => [20, 26],
                'Moka Pot' => [26, 32],
                'AeroPress' => [32, 40],
                'V60' => [38, 46],
                'French Press' => [50, 60],
            ],
        ],
        'Timemore C2' => [
            'step' => 2,
            'note' => 'Counted from closed. Roughly 30 clicks per rotation.',
            'settings' => [
                'Espresso' => [8, 12],
                'Moka Pot' => [10, 14],
                'AeroPress' => [14, 18],
                'V60' => [18, 22],
                'French Press' => [24, 30],
            ],
        ],
        'Baratza Encore' => [
            'step' => 2,
            'note' => 'Dial setting, 1-40. Not a hand grinder: read the number on the dial.',
            'settings' => [
                'Espresso' => [4, 8],
                'Moka Pot' => [8, 12],
                'AeroPress' => [12, 18],
                'V60' => [18, 22],
                'French Press' => [28, 32],
            ],
        ],
        'Hario Skerton Pro' => [
            'step' => 1,
            'note' => 'Counted from closed. Few clicks overall, so each one is a big jump.',
            'settings' => [
                'Espresso' => [3, 5],
                'Moka Pot' => [5, 7],
                'AeroPress' => [7, 9],
                'V60' => [8, 11],
                'French Press' => [12, 15],
            ],
        ],
        'Other' => [
            'step' => 1,
            'note' => 'Unknown grinder: no click numbers available, describe the grind instead.',
            'settings' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing methods
    |--------------------------------------------------------------------------
    | Processing changes how fast sugars dissolve, which is why it shifts water
    | temperature and grind. Adjustments are applied on top of the origin's base.
    |
    | temp_adjust_c  -> added to the origin base temperature
    | ratio_adjust   -> added to the origin base ratio (higher = more water = weaker)
    */
    'processes' => [
        'Washed' => [
            'temp_adjust_c' => 0,
            'ratio_adjust' => 0.0,
            'grind_adjust' => 'none',
            'extraction' => 'Clean and even. Extracts at a normal rate, so no compensation is needed.',
        ],
        'Natural' => [
            'temp_adjust_c' => -1,
            'ratio_adjust' => 0.5,
            'grind_adjust' => 'one step coarser',
            'extraction' => 'Dried in the fruit, so the sugars dissolve faster. Extracts quickly and '
                .'over-extracts easily; back off temperature and grind coarser.',
        ],
        'Honey' => [
            'temp_adjust_c' => 0,
            'ratio_adjust' => 0.0,
            'grind_adjust' => 'none',
            'extraction' => 'Between washed and natural. Sweet and syrupy, extracts close to normal.',
        ],
        'Anaerobic' => [
            'temp_adjust_c' => -2,
            'ratio_adjust' => 1.0,
            'grind_adjust' => 'one to two steps coarser',
            'extraction' => 'Fermented under pressure: very soluble and very intense. Brew cooler and '
                .'coarser or the ferment character turns boozy and harsh.',
        ],
    ],

];
