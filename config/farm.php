<?php

return [
    // গরুর ধরন (dashboard-এর donut-এর রং এখান থেকেই আসে)
    'categories' => [
        'milking'   => ['label' => 'দুধ দিচ্ছে', 'tag' => 'green',  'color' => '#1f8a4f'],
        'dry'       => ['label' => 'শুকনো',      'tag' => 'orange', 'color' => '#f0a04b'],
        'heifer'    => ['label' => 'বকনা',       'tag' => 'purple', 'color' => '#9a7bd8'],
        'calf'      => ['label' => 'বাছুর',      'tag' => 'blue',   'color' => '#4b9be0'],
        'bull'      => ['label' => 'ষাঁড়',      'tag' => 'red',    'color' => '#e5707c'],
        'fattening' => ['label' => 'মোটাতাজা',   'tag' => 'gray',   'color' => '#8a7a5c'],
    ],

    'breeds' => [
        'deshi'            => 'দেশি',
        'holstein_cross'   => 'হলস্টেইন ক্রস',
        'sahiwal_cross'    => 'শাহিওয়াল ক্রস',
        'sindhi_cross'     => 'সিন্ধি ক্রস',
        'jersey_cross'     => 'জার্সি ক্রস',
        'brahman_cross'    => 'ব্রাহমা ক্রস',
        'red_chittagong'   => 'রেড চিটাগং',
        'pabna'            => 'পাবনা',
        'north_bengal_grey' => 'উত্তরবঙ্গ গ্রে',
        'other'            => 'অন্যান্য',
    ],

    'sexes' => ['female' => 'মাদি', 'male' => 'নর'],

    'statuses' => [
        'active' => 'সক্রিয়',
        'sold' => 'বিক্রিত',
        'dead' => 'মৃত',
        'transferred' => 'সরানো হয়েছে',
    ],

    'acquisitions' => [
        'born' => 'খামারে জন্ম',
        'purchased' => 'কেনা',
        'existing' => 'আগে থেকে ছিল',
    ],

    'expense_categories' => [
        'feed'      => ['label' => 'খাবার',            'hint' => 'ঘাস, খড়, ভুসি, দানা',  'icon' => 'bi-basket',          'tone' => 'mint',  'color' => '#1f8a4f'],
        'medicine'  => ['label' => 'চিকিৎসা ও ওষুধ',   'hint' => 'ডাক্তার, ওষুধ, টিকা',   'icon' => 'bi-heart-pulse',     'tone' => 'rose',  'color' => '#e5707c'],
        'labor'     => ['label' => 'শ্রমিক',           'hint' => 'বেতন ও মজুরি',          'icon' => 'bi-people',          'tone' => 'sky',   'color' => '#4b9be0'],
        'utility'   => ['label' => 'বিদ্যুৎ ও পানি',    'hint' => '',                      'icon' => 'bi-lightning-charge', 'tone' => 'sand',  'color' => '#e0b43a'],
        'transport' => ['label' => 'পরিবহন',           'hint' => '',                      'icon' => 'bi-truck',           'tone' => 'peach', 'color' => '#f0a04b'],
        'shed'      => ['label' => 'গোয়ালঘর ও মেরামত', 'hint' => '',                      'icon' => 'bi-tools',           'tone' => 'lilac', 'color' => '#9a7bd8'],
        'breeding'  => ['label' => 'প্রজনন',           'hint' => 'বীজ, কৃত্রিম প্রজনন',   'icon' => 'bi-hearts',          'tone' => 'rose',  'color' => '#d65a8a'],
        'other'     => ['label' => 'অন্যান্য',          'hint' => '',                      'icon' => 'bi-three-dots',      'tone' => 'sand',  'color' => '#8a9c93'],
    ],

    'payment_methods' => [
        'cash' => 'নগদ',
        'bkash' => 'bKash',
        'nagad' => 'Nagad',
        'rocket' => 'Rocket',
        'bank' => 'ব্যাংক',
        'other' => 'অন্যান্য',
    ],

    'payment_statuses' => ['unpaid' => 'বকেয়া', 'partial' => 'আংশিক', 'paid' => 'পরিশোধিত'],

    'sale_types' => [
        'milk'         => ['label' => 'দুধ বিক্রি',          'unit' => 'লিটার', 'icon' => 'bi-droplet-half', 'tone' => 'sky',   'color' => '#4b9be0'],
        'cow'          => ['label' => 'গরু বা বাছুর বিক্রি', 'unit' => null,    'icon' => 'bi-collection',   'tone' => 'peach', 'color' => '#f0a04b'],
        'manure'       => ['label' => 'গোবর বিক্রি',         'unit' => 'কেজি',  'icon' => 'bi-recycle',      'tone' => 'sand',  'color' => '#c9a227'],
        'other_income' => ['label' => 'অন্যান্য আয়',        'unit' => null,    'icon' => 'bi-cash-stack',   'tone' => 'mint',  'color' => '#1f8a4f'],
    ],

    'health_kinds' => [
        'treatment'   => ['label' => 'চিকিৎসা',          'icon' => 'bi-bandaid',         'tone' => 'rose',  'pill' => 'red'],
        'vaccination' => ['label' => 'টিকা',              'icon' => 'bi-shield-plus',     'tone' => 'sky',   'pill' => 'blue'],
        'deworming'   => ['label' => 'কৃমিনাশক',          'icon' => 'bi-capsule',         'tone' => 'lilac', 'pill' => 'purple'],
        'checkup'     => ['label' => 'স্বাস্থ্য পরীক্ষা', 'icon' => 'bi-clipboard-pulse', 'tone' => 'mint',  'pill' => 'green'],
    ],

    // interval_days শুধু ফর্মে পরের তারিখের একটা সাধারণ ধারণা। টিকার নির্দেশনা ও পশু চিকিৎসকের কথাই মানবে।
    'vaccines' => [
        'fmd'     => ['label' => 'ক্ষুরা রোগ (FMD)',          'interval_days' => 180],
        'anthrax' => ['label' => 'তড়কা (অ্যানথ্রাক্স)',      'interval_days' => 365],
        'bq'      => ['label' => 'ব্ল্যাক কোয়ার্টার (BQ)',   'interval_days' => 365],
        'hs'      => ['label' => 'গলাফুলা (HS)',             'interval_days' => 365],
        'lsd'     => ['label' => 'লাম্পি স্কিন ডিজিজ (LSD)', 'interval_days' => 365],
        'rabies'  => ['label' => 'জলাতঙ্ক (র‍্যাবিস)',        'interval_days' => 365],
        'other'   => ['label' => 'অন্যান্য',                 'interval_days' => null],
    ],

    // সাধারণ গড় ধরে দেওয়া। জাত ও পশু চিকিৎসকের পরামর্শ অনুযায়ী বদলে নিয়ো।
    'breeding' => [
        'gestation_days' => 283,
        'check_after_days' => 60,
    ],

    'breeding_methods' => [
        'ai' => 'কৃত্রিম প্রজনন (AI)',
        'natural' => 'প্রাকৃতিক (ষাঁড় দিয়ে)',
    ],

    'breeding_statuses' => [
        'served'   => ['label' => 'সেবা হয়েছে, ফল বাকি', 'pill' => 'blue'],
        'pregnant' => ['label' => 'গর্ভবতী',              'pill' => 'green'],
        'open'     => ['label' => 'গর্ভ হয়নি',           'pill' => 'gray'],
        'aborted'  => ['label' => 'গর্ভপাত',              'pill' => 'red'],
        'calved'   => ['label' => 'প্রসব হয়েছে',         'pill' => 'purple'],
    ],

    'feed_units' => [
        'kg' => 'কেজি',
        'bundle' => 'আঁটি',
        'bag' => 'বস্তা',
        'liter' => 'লিটার',
        'piece' => 'টি',
    ],

    'feed_targets' => [
        'all' => 'পুরো পাল',
        'category' => 'গরুর ধরন অনুযায়ী',
        'cow' => 'নির্দিষ্ট গরু',
    ],

    // "সাধারণ ধরন যোগ করুন" বাটনে এগুলো তৈরি হয়। দাম নিজে বসাবে।
    'feed_defaults' => [
        ['name' => 'কাঁচা ঘাস', 'unit' => 'kg'],
        ['name' => 'খড়', 'unit' => 'kg'],
        ['name' => 'ভুসি', 'unit' => 'kg'],
        ['name' => 'দানাদার খাদ্য', 'unit' => 'kg'],
        ['name' => 'খৈল', 'unit' => 'kg'],
    ],
];
