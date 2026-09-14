<?php
// Public feed matches the existing Veegame SaaS client default. No credentials sent.
return [
    'draw_base_url' => 'https://draw.ar-lottery06.com',
    'request_timeout_seconds' => 5,
    'history_page_size' => 10,
    'bet_lock_seconds' => 5,
    'maximum_stake' => 1000000,
    'games' => [
        'WinGo_30S' => ['name'=>'WinGo 30sec','lottery'=>'WinGo','interval'=>0.5,'sort'=>44],
        'WinGo_1M' => ['name'=>'WinGo 1 Min','lottery'=>'WinGo','interval'=>1,'sort'=>43],
        'WinGo_3M' => ['name'=>'WinGo 3 Min','lottery'=>'WinGo','interval'=>3,'sort'=>42],
        'WinGo_5M' => ['name'=>'WinGo 5 Min','lottery'=>'WinGo','interval'=>5,'sort'=>41],
    ],
];
