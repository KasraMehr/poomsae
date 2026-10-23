<?php

return [
    'age_table_year' => 1405,
    'forms' => [
        1 => 'Taegeuk 1',
        2 => 'Taegeuk 2',
        3 => 'Taegeuk 3',
        4 => 'Taegeuk 4',
        5 => 'Taegeuk 5',
        6 => 'Taegeuk 6',
        7 => 'Taegeuk 7',
        8 => 'Taegeuk 8',
        9 => 'Koryo',
        10 => 'Keumgang',
        11 => 'Taebaek',
        12 => 'Pyongwon',
        13 => 'Shipjin',
        14 => 'Jitae',
        15 => 'Chonkwon',
        16 => 'Hansu',
        17 => 'Ilyo',
    ],
    'default_timing' => [
        'recognized' => [
            'minimum_duration_seconds' => null,
            'maximum_duration_seconds' => 90,
            'minimum_rest_seconds' => 30,
            'time_penalty_hundredths' => 30,
        ],
        'freestyle' => [
            'minimum_duration_seconds' => 90,
            'maximum_duration_seconds' => 100,
            'minimum_rest_seconds' => 30,
            'time_penalty_hundredths' => 30,
        ],
    ],
    'freestyle_scoring' => [
        'technical' => [
            'max' => 600,
            'component_count' => 6,
            'component_max' => 100,
            'components' => [
                ['key' => 'side_kick', 'name' => 'Side Kick', 'abbr' => 'S', 'max' => 100],
                ['key' => 'front_kick', 'name' => 'Front Kick', 'abbr' => 'F', 'max' => 100],
                ['key' => 'spin_gradient', 'name' => 'Spin Gradient', 'abbr' => 'G', 'max' => 100],
                ['key' => 'sparring', 'name' => 'Sparring', 'abbr' => 'S', 'max' => 100],
                ['key' => 'acrobatic', 'name' => 'Acrobatic', 'abbr' => 'A', 'max' => 100],
                ['key' => 'basic_movements', 'name' => 'Basic Movements', 'abbr' => 'B', 'max' => 100],
            ],
        ],
        'presentation' => [
            'max' => 400,
            'component_count' => 4,
            'component_max' => 100,
            'components' => [
                ['key' => 'creativity', 'name' => 'Creativity', 'abbr' => 'C', 'max' => 100],
                ['key' => 'harmony', 'name' => 'Harmony', 'abbr' => 'H', 'max' => 100],
                ['key' => 'expression_of_energy', 'name' => 'Expression of Energy', 'abbr' => 'E', 'max' => 100],
                ['key' => 'music', 'name' => 'Music', 'abbr' => 'M', 'max' => 100],
            ],
        ],
    ],
    'age_categories' => [
        'recognized_individual_under_12' => [
            'label' => 'Individual Under 12', 'discipline' => 'recognized', 'entry_type' => 'individual', 'minimum_age' => null, 'maximum_age' => 11,
            'birth_date_label' => 'متولدین ۱۳۹۳/۱۰/۱۱ به بالا', 'birth_date_min' => '2015-01-01', 'birth_date_max' => null,
            'forms' => [2, 3, 4, 5, 6, 7, 8, 9],
        ],
        'recognized_individual_12_14' => [
            'label' => 'Individual Cadet (12-14)', 'discipline' => 'recognized', 'entry_type' => 'individual', 'minimum_age' => 12, 'maximum_age' => 14,
            'birth_date_label' => 'متولدین ۱۳۹۰/۱۰/۱۱ الی ۱۳۹۳/۱۰/۱۰', 'birth_date_min' => '2012-01-01', 'birth_date_max' => '2014-12-31',
            'forms' => [4, 5, 6, 7, 8, 9, 10, 11],
        ],
        'recognized_pair_12_14' => [
            'label' => 'Pair Cadet (12-14)', 'discipline' => 'recognized', 'entry_type' => 'pair', 'minimum_age' => 12, 'maximum_age' => 14,
            'birth_date_label' => 'متولدین ۱۳۹۰/۱۰/۱۱ الی ۱۳۹۳/۱۰/۱۰', 'birth_date_min' => '2012-01-01', 'birth_date_max' => '2014-12-31',
            'forms' => [4, 5, 6, 7, 8, 9, 10, 11],
        ],
        'recognized_team_under_14' => [
            'label' => 'Team Cadet (12-14)', 'discipline' => 'recognized', 'entry_type' => 'team', 'minimum_age' => null, 'maximum_age' => 14,
            'birth_date_label' => 'متولدین ۱۳۹۰/۱۰/۱۱ به بالا', 'birth_date_min' => '2012-01-01', 'birth_date_max' => null,
            'forms' => [4, 5, 6, 7, 8, 9, 10, 11],
        ],
        'recognized_individual_15_17' => [
            'label' => 'Individual Junior (15-17)', 'discipline' => 'recognized', 'entry_type' => 'individual', 'minimum_age' => 15, 'maximum_age' => 17,
            'birth_date_label' => 'متولدین ۱۳۸۷/۱۰/۱۱ الی ۱۳۹۰/۱۰/۱۰', 'birth_date_min' => '2008-12-31', 'birth_date_max' => '2011-12-31',
            'forms' => [5, 6, 7, 8, 9, 10, 11, 12],
        ],
        'recognized_pair_15_17' => [
            'label' => 'Pair Junior (15-17)', 'discipline' => 'recognized', 'entry_type' => 'pair', 'minimum_age' => 15, 'maximum_age' => 17,
            'birth_date_label' => 'متولدین ۱۳۸۷/۱۰/۱۱ الی ۱۳۹۰/۱۰/۱۰', 'birth_date_min' => '2008-12-31', 'birth_date_max' => '2011-12-31',
            'forms' => [5, 6, 7, 8, 9, 10, 11, 12],
        ],
        'recognized_team_15_17' => [
            'label' => 'Team Junior (15-17)', 'discipline' => 'recognized', 'entry_type' => 'team', 'minimum_age' => 15, 'maximum_age' => 17,
            'birth_date_label' => 'متولدین ۱۳۸۷/۱۰/۱۱ الی ۱۳۹۰/۱۰/۱۰', 'birth_date_min' => '2008-12-31', 'birth_date_max' => '2011-12-31',
            'forms' => [5, 6, 7, 8, 9, 10, 11, 12],
        ],
        'recognized_individual_18_30' => [
            'label' => 'Individual Under 30 (18-30)', 'discipline' => 'recognized', 'entry_type' => 'individual', 'minimum_age' => 18, 'maximum_age' => 30,
            'birth_date_label' => 'متولدین ۱۳۷۴/۱۰/۱۱ الی ۱۳۸۷/۱۰/۱۰', 'birth_date_min' => '1996-01-01', 'birth_date_max' => '2008-12-30',
            'forms' => [6, 7, 8, 9, 10, 11, 12, 13, 14],
        ],
        'recognized_individual_31_40' => [
            'label' => 'Individual Under 40 (31-40)', 'discipline' => 'recognized', 'entry_type' => 'individual', 'minimum_age' => 31, 'maximum_age' => 40,
            'birth_date_label' => 'متولدین ۱۳۶۴/۱۰/۱۱ الی ۱۳۷۴/۱۰/۱۰', 'birth_date_min' => '1986-01-01', 'birth_date_max' => '1995-12-31',
            'forms' => [6, 7, 8, 9, 10, 11, 12, 13, 14],
        ],
        'recognized_pair_18_30' => [
            'label' => 'Pair Under 30 (18-30)', 'discipline' => 'recognized', 'entry_type' => 'pair', 'minimum_age' => 18, 'maximum_age' => 30,
            'birth_date_label' => 'متولدین ۱۳۷۴/۱۰/۱۱ الی ۱۳۸۷/۱۰/۱۰', 'birth_date_min' => '1996-01-01', 'birth_date_max' => '2008-12-30',
            'forms' => [6, 7, 8, 9, 10, 11, 12, 13, 14],
        ],
        'recognized_team_18_30' => [
            'label' => 'Team Under 30 (18-30)', 'discipline' => 'recognized', 'entry_type' => 'team', 'minimum_age' => 18, 'maximum_age' => 30,
            'birth_date_label' => 'متولدین ۱۳۷۴/۱۰/۱۱ الی ۱۳۸۷/۱۰/۱۰', 'birth_date_min' => '1996-01-01', 'birth_date_max' => '2008-12-30',
            'forms' => [6, 7, 8, 9, 10, 11, 12, 13, 14],
        ],
        'recognized_individual_41_50' => [
            'label' => 'Individual Under 50 (41-50)', 'discipline' => 'recognized', 'entry_type' => 'individual', 'minimum_age' => 41, 'maximum_age' => 50,
            'birth_date_label' => 'متولدین ۱۳۵۴/۱۰/۱۱ الی ۱۳۶۴/۱۰/۱۰', 'birth_date_min' => '1976-01-01', 'birth_date_max' => '1985-12-31',
            'forms' => [8, 9, 10, 11, 12, 13, 14, 15],
        ],
        'recognized_pair_31_50' => [
            'label' => 'Pair Under 50 (31-50)', 'discipline' => 'recognized', 'entry_type' => 'pair', 'minimum_age' => 31, 'maximum_age' => 50,
            'birth_date_label' => 'متولدین ۱۳۵۴/۱۰/۱۱ الی ۱۳۷۴/۱۰/۱۰', 'birth_date_min' => '1976-01-01', 'birth_date_max' => '1995-12-31',
            'forms' => [8, 9, 10, 11, 12, 13, 14, 15],
        ],
        'recognized_team_31_50' => [
            'label' => 'Team Under 50 (31-50)', 'discipline' => 'recognized', 'entry_type' => 'team', 'minimum_age' => 31, 'maximum_age' => 50,
            'birth_date_label' => 'متولدین ۱۳۵۴/۱۰/۱۱ الی ۱۳۷۴/۱۰/۱۰', 'birth_date_min' => '1976-01-01', 'birth_date_max' => '1995-12-31',
            'forms' => [8, 9, 10, 11, 12, 13, 14, 15],
        ],
        'recognized_individual_51_60' => [
            'label' => 'Individual Under 60 (51-60)', 'discipline' => 'recognized', 'entry_type' => 'individual', 'minimum_age' => 51, 'maximum_age' => 60,
            'birth_date_label' => 'متولدین ۱۳۴۴/۱۰/۱۱ الی ۱۳۵۴/۱۰/۱۰', 'birth_date_min' => '1966-01-01', 'birth_date_max' => '1975-12-31',
            'forms' => [9, 10, 11, 12, 13, 14, 15, 16],
        ],
        'recognized_individual_61_65' => [
            'label' => 'Individual Under 65 (61-65)', 'discipline' => 'recognized', 'entry_type' => 'individual', 'minimum_age' => 61, 'maximum_age' => 65,
            'birth_date_label' => 'متولدین ۱۳۳۹/۱۰/۱۱ الی ۱۳۴۴/۱۰/۱۰', 'birth_date_min' => '1961-01-01', 'birth_date_max' => '1965-12-31',
            'forms' => [9, 10, 11, 12, 13, 14, 15, 16],
        ],
        'recognized_individual_over_65' => [
            'label' => 'Individual Over 65', 'discipline' => 'recognized', 'entry_type' => 'individual', 'minimum_age' => 66, 'maximum_age' => null,
            'birth_date_label' => 'متولدین ۱۳۳۹/۱۰/۱۰ به پایین', 'birth_date_min' => null, 'birth_date_max' => '1960-12-31',
            'forms' => [9, 10, 11, 12, 13, 14, 15, 16],
        ],
        'recognized_pair_under_60' => [
            'label' => 'Pair Under 60 (51-60)', 'discipline' => 'recognized', 'entry_type' => 'pair', 'minimum_age' => 51, 'maximum_age' => 60,
            'birth_date_label' => 'متولدین ۱۳۴۴/۱۰/۱۱ الی ۱۳۵۴/۱۰/۱۰', 'birth_date_min' => '1966-01-01', 'birth_date_max' => '1975-12-31',
            'forms' => [9, 10, 11, 12, 13, 14, 15, 16],
        ],
        'recognized_pair_over_60' => [
            'label' => 'Pair Over 60', 'discipline' => 'recognized', 'entry_type' => 'pair', 'minimum_age' => 61, 'maximum_age' => null,
            'birth_date_label' => 'متولدین ۱۳۴۴/۱۰/۱۰ به پایین', 'birth_date_min' => null, 'birth_date_max' => '1965-12-31',
            'forms' => [9, 10, 11, 12, 13, 14, 15, 16],
        ],
        'recognized_team_under_60' => [
            'label' => 'Team Under 60 (51-60)', 'discipline' => 'recognized', 'entry_type' => 'team', 'minimum_age' => 51, 'maximum_age' => 60,
            'birth_date_label' => 'متولدین ۱۳۴۴/۱۰/۱۱ الی ۱۳۵۴/۱۰/۱۰', 'birth_date_min' => '1966-01-01', 'birth_date_max' => '1975-12-31',
            'forms' => [9, 10, 11, 12, 13, 14, 15, 16],
        ],
        'recognized_team_over_60' => [
            'label' => 'Team Over 60', 'discipline' => 'recognized', 'entry_type' => 'team', 'minimum_age' => 61, 'maximum_age' => null,
            'birth_date_label' => 'متولدین ۱۳۴۴/۱۰/۱۰ به پایین', 'birth_date_min' => null, 'birth_date_max' => '1965-12-31',
            'forms' => [9, 10, 11, 12, 13, 14, 15, 16],
        ],
        'recognized_team_over_50' => [
            'label' => 'Team Over 50', 'discipline' => 'recognized', 'entry_type' => 'team', 'minimum_age' => 51, 'maximum_age' => null,
            'birth_date_label' => 'متولدین ۱۳۵۴/۱۰/۱۰ به پایین', 'birth_date_min' => null, 'birth_date_max' => '1975-12-31',
            'forms' => [9, 10, 11, 12, 13, 14, 15, 16],
        ],
        'freestyle_individual_under_14' => [
            'label' => 'Freestyle Individual Under 14', 'discipline' => 'freestyle', 'entry_type' => 'individual', 'minimum_age' => null, 'maximum_age' => 13,
            'birth_date_label' => 'متولدین ۱۳۹۰/۱۰/۱۱ به بالا', 'birth_date_min' => '2012-01-01', 'birth_date_max' => null,
            'forms' => [],
        ],
        'freestyle_individual_15_17' => [
            'label' => 'Freestyle Individual (15-17)', 'discipline' => 'freestyle', 'entry_type' => 'individual', 'minimum_age' => 15, 'maximum_age' => 17,
            'birth_date_label' => 'متولدین ۱۳۸۷/۱۰/۱۱ الی ۱۳۹۰/۱۰/۱۰', 'birth_date_min' => '2008-12-31', 'birth_date_max' => '2011-12-31',
            'forms' => [],
        ],
        'freestyle_individual_over_18' => [
            'label' => 'Freestyle Individual Over 18', 'discipline' => 'freestyle', 'entry_type' => 'individual', 'minimum_age' => 18, 'maximum_age' => null,
            'birth_date_label' => 'متولدین ۱۳۸۷/۱۰/۱۰ به پایین', 'birth_date_min' => null, 'birth_date_max' => '2008-12-30',
            'forms' => [],
        ],
        'freestyle_pair_under_18' => [
            'label' => 'Freestyle Pair Under 18', 'discipline' => 'freestyle', 'entry_type' => 'pair', 'minimum_age' => null, 'maximum_age' => 17,
            'birth_date_label' => 'متولدین ۱۳۸۷/۱۰/۱۱ به بالا', 'birth_date_min' => '2008-12-31', 'birth_date_max' => null,
            'forms' => [],
        ],
        'freestyle_pair_over_18' => [
            'label' => 'Freestyle Pair Over 18', 'discipline' => 'freestyle', 'entry_type' => 'pair', 'minimum_age' => 18, 'maximum_age' => null,
            'birth_date_label' => 'متولدین ۱۳۸۷/۱۰/۱۰ به پایین', 'birth_date_min' => null, 'birth_date_max' => '2008-12-30',
            'forms' => [],
        ],
    ],
];
