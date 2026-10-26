<?php

return [
    'age_table_year' => 1405,
    'forms' => [
        1 => 'فرم 1',
        2 => 'فرم 2',
        3 => 'فرم 3',
        4 => 'فرم 4',
        5 => 'فرم 5',
        6 => 'فرم 6',
        7 => 'فرم 7',
        8 => 'فرم 8',
        9 => 'کوریو',
        10 => 'کومگانگ',
        11 => 'ته بک',
        12 => 'پیونگ وان',
        13 => 'شیپ جین',
        14 => 'جی ته',
        15 => 'چون کوان',
        16 => 'هانسو',
        17 => 'ایل یو',
    ],
    'age_categories' => [
        'recognized_individual_under_12' => [
            'label' => 'زیر ۱۲ سال', 'discipline' => 'recognized', 'entry_type' => 'individual', 'minimum_age' => null, 'maximum_age' => 11,
            'birth_date_label' => 'متولدین ۱۳۹۳/۱۰/۱۱ به بالا', 'birth_date_min' => '2015-01-01', 'birth_date_max' => null,
            'forms' => [2, 3, 4, 5, 6, 7, 8, 9],
        ],
        'recognized_individual_12_14' => [
            'label' => 'نونهالان (۱۲ الی ۱۴ سال)', 'discipline' => 'recognized', 'entry_type' => 'individual', 'minimum_age' => 12, 'maximum_age' => 14,
            'birth_date_label' => 'متولدین ۱۳۹۰/۱۰/۱۱ الی ۱۳۹۳/۱۰/۱۰', 'birth_date_min' => '2012-01-01', 'birth_date_max' => '2014-12-31',
            'forms' => [4, 5, 6, 7, 8, 9, 10, 11],
        ],
        'recognized_pair_12_14' => [
            'label' => 'دو نفره نونهالان (۱۲ الی ۱۴ سال)', 'discipline' => 'recognized', 'entry_type' => 'pair', 'minimum_age' => 12, 'maximum_age' => 14,
            'birth_date_label' => 'متولدین ۱۳۹۰/۱۰/۱۱ الی ۱۳۹۳/۱۰/۱۰', 'birth_date_min' => '2012-01-01', 'birth_date_max' => '2014-12-31',
            'forms' => [4, 5, 6, 7, 8, 9, 10, 11],
        ],
        'recognized_team_under_14' => [
            'label' => 'تیمی نونهالان (۱۲ الی ۱۴ سال)', 'discipline' => 'recognized', 'entry_type' => 'team', 'minimum_age' => null, 'maximum_age' => 14,
            'birth_date_label' => 'متولدین ۱۳۹۰/۱۰/۱۱ به بالا', 'birth_date_min' => '2012-01-01', 'birth_date_max' => null,
            'forms' => [4, 5, 6, 7, 8, 9, 10, 11],
        ],
        'recognized_individual_15_17' => [
            'label' => 'نوجوانان (۱۵ الی ۱۷ سال)', 'discipline' => 'recognized', 'entry_type' => 'individual', 'minimum_age' => 15, 'maximum_age' => 17,
            'birth_date_label' => 'متولدین ۱۳۸۷/۱۰/۱۱ الی ۱۳۹۰/۱۰/۱۰', 'birth_date_min' => '2008-12-31', 'birth_date_max' => '2011-12-31',
            'forms' => [5, 6, 7, 8, 9, 10, 11, 12],
        ],
        'recognized_pair_15_17' => [
            'label' => 'دو نفره نوجوانان (۱۵ الی ۱۷ سال)', 'discipline' => 'recognized', 'entry_type' => 'pair', 'minimum_age' => 15, 'maximum_age' => 17,
            'birth_date_label' => 'متولدین ۱۳۸۷/۱۰/۱۱ الی ۱۳۹۰/۱۰/۱۰', 'birth_date_min' => '2008-12-31', 'birth_date_max' => '2011-12-31',
            'forms' => [5, 6, 7, 8, 9, 10, 11, 12],
        ],
        'recognized_team_15_17' => [
            'label' => 'تیمی نوجوانان (۱۵ الی ۱۷ سال)', 'discipline' => 'recognized', 'entry_type' => 'team', 'minimum_age' => 15, 'maximum_age' => 17,
            'birth_date_label' => 'متولدین ۱۳۸۷/۱۰/۱۱ الی ۱۳۹۰/۱۰/۱۰', 'birth_date_min' => '2008-12-31', 'birth_date_max' => '2011-12-31',
            'forms' => [5, 6, 7, 8, 9, 10, 11, 12],
        ],
        'recognized_individual_18_30' => [
            'label' => 'انفرادی ۱۸ الی ۳۰ سال (زیر ۳۰ سال)', 'discipline' => 'recognized', 'entry_type' => 'individual', 'minimum_age' => 18, 'maximum_age' => 30,
            'birth_date_label' => 'متولدین ۱۳۷۴/۱۰/۱۱ الی ۱۳۸۷/۱۰/۱۰', 'birth_date_min' => '1996-01-01', 'birth_date_max' => '2008-12-30',
            'forms' => [6, 7, 8, 9, 10, 11, 12, 13, 14],
        ],
        'recognized_individual_31_40' => [
            'label' => 'انفرادی ۳۱ الی ۴۰ سال (زیر ۴۰ سال)', 'discipline' => 'recognized', 'entry_type' => 'individual', 'minimum_age' => 31, 'maximum_age' => 40,
            'birth_date_label' => 'متولدین ۱۳۶۴/۱۰/۱۱ الی ۱۳۷۴/۱۰/۱۰', 'birth_date_min' => '1986-01-01', 'birth_date_max' => '1995-12-31',
            'forms' => [6, 7, 8, 9, 10, 11, 12, 13, 14],
        ],
        'recognized_pair_18_30' => [
            'label' => 'دو نفره ۱۸ الی ۳۰ سال (زیر ۳۰ سال)', 'discipline' => 'recognized', 'entry_type' => 'pair', 'minimum_age' => 18, 'maximum_age' => 30,
            'birth_date_label' => 'متولدین ۱۳۷۴/۱۰/۱۱ الی ۱۳۸۷/۱۰/۱۰', 'birth_date_min' => '1996-01-01', 'birth_date_max' => '2008-12-30',
            'forms' => [6, 7, 8, 9, 10, 11, 12, 13, 14],
        ],
        'recognized_team_18_30' => [
            'label' => 'تیمی ۱۸ الی ۳۰ سال (زیر ۳۰ سال)', 'discipline' => 'recognized', 'entry_type' => 'team', 'minimum_age' => 18, 'maximum_age' => 30,
            'birth_date_label' => 'متولدین ۱۳۷۴/۱۰/۱۱ الی ۱۳۸۷/۱۰/۱۰', 'birth_date_min' => '1996-01-01', 'birth_date_max' => '2008-12-30',
            'forms' => [6, 7, 8, 9, 10, 11, 12, 13, 14],
        ],
        'recognized_individual_41_50' => [
            'label' => 'انفرادی ۴۱ الی ۵۰ سال (زیر ۵۰ سال)', 'discipline' => 'recognized', 'entry_type' => 'individual', 'minimum_age' => 41, 'maximum_age' => 50,
            'birth_date_label' => 'متولدین ۱۳۵۴/۱۰/۱۱ الی ۱۳۶۴/۱۰/۱۰', 'birth_date_min' => '1976-01-01', 'birth_date_max' => '1985-12-31',
            'forms' => [8, 9, 10, 11, 12, 13, 14, 15],
        ],
        'recognized_pair_31_50' => [
            'label' => 'دو نفره ۳۱ الی ۵۰ سال (زیر ۵۰ سال)', 'discipline' => 'recognized', 'entry_type' => 'pair', 'minimum_age' => 31, 'maximum_age' => 50,
            'birth_date_label' => 'متولدین ۱۳۵۴/۱۰/۱۱ الی ۱۳۷۴/۱۰/۱۰', 'birth_date_min' => '1976-01-01', 'birth_date_max' => '1995-12-31',
            'forms' => [8, 9, 10, 11, 12, 13, 14, 15],
        ],
        'recognized_team_31_50' => [
            'label' => 'تیمی ۳۱ الی ۵۰ سال (زیر ۵۰ سال)', 'discipline' => 'recognized', 'entry_type' => 'team', 'minimum_age' => 31, 'maximum_age' => 50,
            'birth_date_label' => 'متولدین ۱۳۵۴/۱۰/۱۱ الی ۱۳۷۴/۱۰/۱۰', 'birth_date_min' => '1976-01-01', 'birth_date_max' => '1995-12-31',
            'forms' => [8, 9, 10, 11, 12, 13, 14, 15],
        ],
        'recognized_individual_51_60' => [
            'label' => 'انفرادی ۵۱ الی ۶۰ سال (زیر ۶۰ سال)', 'discipline' => 'recognized', 'entry_type' => 'individual', 'minimum_age' => 51, 'maximum_age' => 60,
            'birth_date_label' => 'متولدین ۱۳۴۴/۱۰/۱۱ الی ۱۳۵۴/۱۰/۱۰', 'birth_date_min' => '1966-01-01', 'birth_date_max' => '1975-12-31',
            'forms' => [9, 10, 11, 12, 13, 14, 15, 16],
        ],
        'recognized_individual_61_65' => [
            'label' => 'انفرادی ۶۱ الی ۶۵ سال (زیر ۶۵ سال)', 'discipline' => 'recognized', 'entry_type' => 'individual', 'minimum_age' => 61, 'maximum_age' => 65,
            'birth_date_label' => 'متولدین ۱۳۳۹/۱۰/۱۱ الی ۱۳۴۴/۱۰/۱۰', 'birth_date_min' => '1961-01-01', 'birth_date_max' => '1965-12-31',
            'forms' => [9, 10, 11, 12, 13, 14, 15, 16],
        ],
        'recognized_individual_over_65' => [
            'label' => 'انفرادی بالای ۶۵ سال', 'discipline' => 'recognized', 'entry_type' => 'individual', 'minimum_age' => 66, 'maximum_age' => null,
            'birth_date_label' => 'متولدین ۱۳۳۹/۱۰/۱۰ به پایین', 'birth_date_min' => null, 'birth_date_max' => '1960-12-31',
            'forms' => [9, 10, 11, 12, 13, 14, 15, 16],
        ],
        'recognized_pair_under_60' => [
            'label' => 'دو نفره زیر ۶۰ سال (۵۱ الی ۶۰ سال)', 'discipline' => 'recognized', 'entry_type' => 'pair', 'minimum_age' => 51, 'maximum_age' => 60,
            'birth_date_label' => 'متولدین ۱۳۴۴/۱۰/۱۱ الی ۱۳۵۴/۱۰/۱۰', 'birth_date_min' => '1966-01-01', 'birth_date_max' => '1975-12-31',
            'forms' => [9, 10, 11, 12, 13, 14, 15, 16],
        ],
        'recognized_pair_over_60' => [
            'label' => 'دو نفره بالای ۶۰ سال', 'discipline' => 'recognized', 'entry_type' => 'pair', 'minimum_age' => 61, 'maximum_age' => null,
            'birth_date_label' => 'متولدین ۱۳۴۴/۱۰/۱۰ به پایین', 'birth_date_min' => null, 'birth_date_max' => '1965-12-31',
            'forms' => [9, 10, 11, 12, 13, 14, 15, 16],
        ],
        'recognized_team_under_60' => [
            'label' => 'تیمی زیر ۶۰ سال (۵۱ الی ۶۰ سال)', 'discipline' => 'recognized', 'entry_type' => 'team', 'minimum_age' => 51, 'maximum_age' => 60,
            'birth_date_label' => 'متولدین ۱۳۴۴/۱۰/۱۱ الی ۱۳۵۴/۱۰/۱۰', 'birth_date_min' => '1966-01-01', 'birth_date_max' => '1975-12-31',
            'forms' => [9, 10, 11, 12, 13, 14, 15, 16],
        ],
        'recognized_team_over_60' => [
            'label' => 'تیمی بالای ۶۰ سال', 'discipline' => 'recognized', 'entry_type' => 'team', 'minimum_age' => 61, 'maximum_age' => null,
            'birth_date_label' => 'متولدین ۱۳۴۴/۱۰/۱۰ به پایین', 'birth_date_min' => null, 'birth_date_max' => '1965-12-31',
            'forms' => [9, 10, 11, 12, 13, 14, 15, 16],
        ],
        'recognized_team_over_50' => [
            'label' => 'تیمی بالای ۵۰ سال', 'discipline' => 'recognized', 'entry_type' => 'team', 'minimum_age' => 51, 'maximum_age' => null,
            'birth_date_label' => 'متولدین ۱۳۵۴/۱۰/۱۰ به پایین', 'birth_date_min' => null, 'birth_date_max' => '1975-12-31',
            'forms' => [9, 10, 11, 12, 13, 14, 15, 16],
        ],
        'freestyle_individual_under_14' => [
            'label' => 'ابداعی زیر ۱۴ سال', 'discipline' => 'freestyle', 'entry_type' => 'individual', 'minimum_age' => null, 'maximum_age' => 13,
            'birth_date_label' => 'متولدین ۱۳۹۰/۱۰/۱۱ به بالا', 'birth_date_min' => '2012-01-01', 'birth_date_max' => null,
            'forms' => [],
        ],
        'freestyle_individual_15_17' => [
            'label' => 'ابداعی ۱۵ الی ۱۷ سال', 'discipline' => 'freestyle', 'entry_type' => 'individual', 'minimum_age' => 15, 'maximum_age' => 17,
            'birth_date_label' => 'متولدین ۱۳۸۷/۱۰/۱۱ الی ۱۳۹۰/۱۰/۱۰', 'birth_date_min' => '2008-12-31', 'birth_date_max' => '2011-12-31',
            'forms' => [],
        ],
        'freestyle_individual_over_18' => [
            'label' => 'ابداعی بالای ۱۸ سال', 'discipline' => 'freestyle', 'entry_type' => 'individual', 'minimum_age' => 18, 'maximum_age' => null,
            'birth_date_label' => 'متولدین ۱۳۸۷/۱۰/۱۰ به پایین', 'birth_date_min' => null, 'birth_date_max' => '2008-12-30',
            'forms' => [],
        ],
        'freestyle_pair_under_18' => [
            'label' => 'تیمی ابداعی دو نفره زیر ۱۸ سال', 'discipline' => 'freestyle', 'entry_type' => 'pair', 'minimum_age' => null, 'maximum_age' => 17,
            'birth_date_label' => 'متولدین ۱۳۸۷/۱۰/۱۱ به بالا', 'birth_date_min' => '2008-12-31', 'birth_date_max' => null,
            'forms' => [],
        ],
        'freestyle_pair_over_18' => [
            'label' => 'تیمی ابداعی دو نفره بالای ۱۸ سال', 'discipline' => 'freestyle', 'entry_type' => 'pair', 'minimum_age' => 18, 'maximum_age' => null,
            'birth_date_label' => 'متولدین ۱۳۸۷/۱۰/۱۰ به پایین', 'birth_date_min' => null, 'birth_date_max' => '2008-12-30',
            'forms' => [],
        ],
    ],
];
