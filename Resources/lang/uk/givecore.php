<?php

return [
    'replace_group' => 'Замінити групу :group на :newGroup?',
    'add_time' => 'Додати час до поточної групи на сервері :server?',
    'update_admin' => 'Оновити адміністратора :name, групу :group?',

    'no_servers' => 'Немає серверів з підключенням :key',

    'drivers' => [
        'vip' => ['name' => 'VIP Core', 'description' => 'Видача/перевірка VIP привілеїв через VIP Core'],
        'fabius' => ['name' => 'Fabius VIP', 'description' => 'Видача/перевірка VIP привілеїв через Fabius VIP'],
        'adminsystem' => [
            'name' => 'AdminSystem',
            'description' => 'Видача/перевірка адмін-привілеїв через AdminSystem (MateSystem)',
        ],
        'sourcebans' => [
            'name' => 'SourceBans',
            'description' => 'Видача/перевірка адмін-привілеїв через SourceBans/SourceBans++',
        ],
        'rcon' => ['name' => 'RCON', 'description' => 'Виконання RCON команд на сервері'],
        'k4system' => [
            'name' => 'K4-System',
            'description' => 'Видача очок через K4-System (CS2)',
        ],
        'k4system_deliver' => [
            'points' => 'Кількість очок',
        ],
        'litebans_deliver' => [
            'name' => 'LiteBans',
            'description' => 'Розбан/розмут через LiteBans (Minecraft)',
            'action' => 'Дія',
            'action_unban' => 'Розбанити',
            'action_unmute' => 'Розмутити',
            'no_active_punishment' => 'Немає активних покарань для цього гравця',
        ],
        'sourcebans_ban' => [
            'name' => 'SourceBans Ban',
            'description' => 'Перевірка активного бану в SourceBans/SourceBans++',
            'no_servers' => 'Немає серверів з підключенням SourceBans',
        ],
        'iks_ban' => [
            'name' => 'IKS Ban',
            'description' => 'Перевірка активного бану в IKS Admin',
            'no_servers' => 'Немає серверів з підключенням IKS',
        ],
        'adminsystem_ban' => [
            'name' => 'AdminSystem Ban',
            'description' => 'Перевірка активного бану в AdminSystem (MateSystem)',
            'no_servers' => 'Немає серверів з підключенням AdminSystem',
        ],
        'simpleadmin_ban' => [
            'name' => 'SimpleAdmin Ban',
            'description' => 'Перевірка активного бану в SimpleAdmin',
            'no_servers' => 'Немає серверів з підключенням SimpleAdmin',
        ],
        'gmbans_ban' => [
            'name' => 'GMBans Ban',
            'description' => 'Перевірка активного бану в GM Bans',
            'no_servers' => 'Немає серверів з підключенням GMBans',
        ],
        'amx_ban' => [
            'name' => 'AMX Bans Ban',
            'description' => 'Перевірка активного бану в AMX Bans',
            'no_servers' => 'Немає серверів з підключенням AMX Bans',
        ],
        'litebans_ban' => [
            'name' => 'LiteBans Ban',
            'description' => 'Перевірка активного бану в LiteBans (Minecraft)',
            'no_servers' => 'Немає серверів з підключенням LiteBans',
        ],
        'advancedban_ban' => [
            'name' => 'AdvancedBan Ban',
            'description' => 'Перевірка активного бану в AdvancedBan (Minecraft)',
            'no_servers' => 'Немає серверів з підключенням AdvancedBan',
        ],
        'zenithbans_ban' => [
            'name' => 'Zenith Bans Ban',
            'description' => 'Перевірка активного бану в Zenith Bans (CS2)',
            'no_servers' => 'Немає серверів з підключенням ZenithBans',
        ],
        'iks_admin' => [
            'name' => 'IKS Admin',
            'description' => 'Перевірка статусу адміністратора в IKS Admin',
            'no_servers' => 'Немає серверів з підключенням IKS',
        ],
        'simpleadmin_admin' => [
            'name' => 'SimpleAdmin Admin',
            'description' => 'Перевірка статусу адміністратора в SimpleAdmin',
            'no_servers' => 'Немає серверів з підключенням SimpleAdmin',
        ],
        'gmbans_admin' => [
            'name' => 'GMBans Admin',
            'description' => 'Перевірка статусу адміністратора в GM Bans',
            'no_servers' => 'Немає серверів з підключенням GMBans',
        ],
        'amx_admin' => [
            'name' => 'AMX Bans Admin',
            'description' => 'Перевірка статусу адміністратора в AMX Bans',
            'no_servers' => 'Немає серверів з підключенням AMX Bans',
        ],
        'levelranks_stats' => [
            'name' => 'Level Ranks',
            'description' => 'Перевірка статистики в Level Ranks',
            'no_servers' => 'Немає серверів з підключенням Level Ranks',
        ],
        'rankme_stats' => [
            'name' => 'RankMe',
            'description' => 'Перевірка статистики в RankMe',
            'no_servers' => 'Немає серверів з підключенням RankMe',
        ],
        'fpsstats_stats' => [
            'name' => 'FPS Stats',
            'description' => 'Перевірка статистики в FPS Stats',
            'no_servers' => 'Немає серверів з підключенням FPS Stats',
        ],
        'csstats_stats' => [
            'name' => 'CsStats',
            'description' => 'Перевірка статистики в CsStats',
            'no_servers' => 'Немає серверів з підключенням CsStats',
        ],
        'csstatsxsql_stats' => [
            'name' => 'CsStats x SQL',
            'description' => 'Перевірка статистики в CsStats x SQL',
            'no_servers' => 'Немає серверів з підключенням CsStats x SQL',
        ],
        'hlstatsce_stats' => [
            'name' => 'HLStatsX:CE',
            'description' => 'Перевірка статистики в HLStatsX:CE',
            'no_servers' => 'Немає серверів з підключенням HLStatsX:CE',
        ],
        'aru_stats' => [
            'name' => 'Army Ranks Ultimate',
            'description' => 'Перевірка статистики в Army Ranks Ultimate',
            'no_servers' => 'Немає серверів з підключенням Army Ranks Ultimate',
        ],
        'qranks_stats' => [
            'name' => 'QRanks',
            'description' => 'Перевірка статистики в QRanks',
            'no_servers' => 'Немає серверів з підключенням QRanks',
        ],
        'k4system_stats' => [
            'name' => 'K4-System',
            'description' => 'Перевірка статистики в K4-System (CS2)',
            'no_servers' => 'Немає серверів з підключенням K4',
        ],
        'k4system' => [
            'name' => 'K4-System',
            'description' => 'Перевірка статистики в K4-System (CS2)',
            'no_servers' => 'Немає серверів з підключенням K4',
        ],
    ],

    'fields' => [
        'server' => 'Сервер',
        'any_server' => 'Будь-який сервер',
        'group' => 'Група',
        'group_placeholder' => 'Введіть назву групи',
        'group_any_placeholder' => 'Залиште порожнім для будь-якої групи',
        'admin_group' => 'Група адміністратора',
        'immunity' => 'Імунітет',
        'min_immunity' => 'Мінімальний імунітет',
        'flags' => 'Прапори',
        'required_flags' => 'Обов\'язкові прапори',
        'flags_placeholder' => 'Наприклад: abcz',
        'comment' => 'Коментар',
        'password' => 'Пароль',
        'password_placeholder' => 'Буде згенеровано автоматично',
        'command' => 'RCON команда',
        'command_placeholder' => 'Введіть команду',
        'command_help' => 'Плейсхолдери: {steam32}, {steam64}, {accountId}, {login}, {name}, {email}, {uri}, {days}, {hours}, {minutes}, {seconds}, {unix}, {nickname}',
        'check_type' => 'Тип перевірки',
        'has_ban' => 'Є бан',
        'no_ban' => 'Немає бану',
        'metric' => 'Метрика',
        'operator' => 'Оператор порівняння',
        'value' => 'Значення',
    ],

    'metrics' => [
        'kills' => 'Вбивства',
        'deaths' => 'Смерті',
        'score' => 'Очки / Досвід',
        'experience' => 'Досвід',
        'playtime' => 'Час гри (сек)',
        'headshots' => 'Хедшоти',
        'kd' => 'K/D співвідношення',
        'rank' => 'Позиція в рейтингу',
        'round_win' => 'Виграні раунди',
        'wins' => 'Перемоги',
    ],

    'operator' => [
        'gte' => 'більше або дорівнює',
        'gt' => 'більше',
        'eq' => 'дорівнює',
        'lt' => 'менше',
        'lte' => 'менше або дорівнює',
    ],

    'categories' => [
        'vip' => 'VIP',
        'admin' => 'Адміністрування',
        'ban' => 'Бани',
        'stats' => 'Статистика',
        'rcon' => 'RCON',
        'other' => 'Інше',
    ],

    'admin' => [
        'title' => 'Видача привілеїв',
        'description' => 'Видача привілеїв користувачам на серверах',
        'menu' => ['title' => 'GiveCore'],
        'give_privilege' => 'Видати привілей',
        'select_driver' => 'Оберіть драйвер',
        'select_server' => 'Оберіть сервер',
        'select_user' => 'Оберіть користувача',
        'time' => 'Час (секунди)',
        'time_help' => '0 = назавжди',
        'deliver_success' => 'Привілей успішно видано',
        'deliver_error' => 'Помилка видачі: :error',
        'available_drivers' => 'Доступні драйвери',
        'driver_params' => 'Параметри: :driver',
    ],

    'settings' => [
        'sid' => 'ID сервера',
        'sid_placeholder' => 'Введіть ID сервера',
    ],
];
