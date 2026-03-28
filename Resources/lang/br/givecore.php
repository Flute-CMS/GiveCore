<?php

return [
    'replace_group' => 'Substituir o grupo :group por :newGroup?',
    'add_time' => 'Adicionar tempo ao grupo atual no servidor :server?',
    'update_admin' => 'Atualizar admin :name, grupo :group?',

    'no_servers' => 'Nenhum servidor com conexão :key',

    'drivers' => [
        'vip' => [
            'name' => 'VIP Core',
            'description' => 'Conceder/verificar privilégios VIP via VIP Core',
        ],
        'fabius' => [
            'name' => 'Fabius VIP',
            'description' => 'Conceder/verificar privilégios VIP via Fabius VIP',
        ],
        'adminsystem' => [
            'name' => 'AdminSystem',
            'description' => 'Conceder/verificar privilégios de admin via AdminSystem (MateSystem)',
        ],
        'sourcebans' => [
            'name' => 'SourceBans',
            'description' => 'Conceder/verificar privilégios de admin via SourceBans/SourceBans++',
        ],
        'rcon' => [
            'name' => 'RCON',
            'description' => 'Executar comandos RCON no servidor',
        ],
        'luckperms' => [
            'name' => 'LuckPerms',
            'description' => 'Entregar grupos via LuckPerms (Minecraft)',
        ],
        'amxmod' => [
            'name' => 'AMX Mod X',
            'description' => 'Conceder privilégios de admin via AMXBans (CS 1.6)',
        ],
        'pex' => [
            'name' => 'PermissionsEx',
            'description' => 'Entregar grupos via PermissionsEx (Minecraft)',
        ],
        'k4system' => [
            'name' => 'K4-System',
            'description' => 'Conceder pontos via K4-System (CS2)',
        ],
        'k4system_deliver' => [
            'points' => 'Quantidade de pontos',
        ],
        'litebans_deliver' => [
            'name' => 'LiteBans',
            'description' => 'Desbanir/desmutar via LiteBans (Minecraft)',
            'action' => 'Ação',
            'action_unban' => 'Desbanir',
            'action_unmute' => 'Desmutar',
            'no_active_punishment' => 'Nenhuma punição ativa para este jogador',
        ],
        'sourcebans_ban' => [
            'name' => 'Ban SourceBans',
            'description' => 'Verificar banimento ativo no SourceBans/SourceBans++',
            'no_servers' => 'Nenhum servidor com conexão SourceBans',
        ],
        'iks_ban' => [
            'name' => 'Ban IKS',
            'description' => 'Verificar banimento ativo no IKS Admin',
            'no_servers' => 'Nenhum servidor com conexão IKS',
        ],
        'adminsystem_ban' => [
            'name' => 'Ban AdminSystem',
            'description' => 'Verificar banimento ativo no AdminSystem (MateSystem)',
            'no_servers' => 'Nenhum servidor com conexão AdminSystem',
        ],
        'simpleadmin_ban' => [
            'name' => 'Ban SimpleAdmin',
            'description' => 'Verificar banimento ativo no SimpleAdmin',
            'no_servers' => 'Nenhum servidor com conexão SimpleAdmin',
        ],
        'gmbans_ban' => [
            'name' => 'Ban GMBans',
            'description' => 'Verificar banimento ativo no GM Bans',
            'no_servers' => 'Nenhum servidor com conexão GMBans',
        ],
        'amx_ban' => [
            'name' => 'Ban AMX Bans',
            'description' => 'Verificar banimento ativo no AMX Bans',
            'no_servers' => 'Nenhum servidor com conexão AMX Bans',
        ],
        'litebans_ban' => [
            'name' => 'Ban LiteBans',
            'description' => 'Verificar banimento ativo no LiteBans (Minecraft)',
            'no_servers' => 'Nenhum servidor com conexão LiteBans',
        ],
        'advancedban_ban' => [
            'name' => 'Ban AdvancedBan',
            'description' => 'Verificar banimento ativo no AdvancedBan (Minecraft)',
            'no_servers' => 'Nenhum servidor com conexão AdvancedBan',
        ],
        'zenithbans_ban' => [
            'name' => 'Ban Zenith Bans',
            'description' => 'Verificar banimento ativo no Zenith Bans (CS2)',
            'no_servers' => 'Nenhum servidor com conexão ZenithBans',
        ],
        'iks_admin' => [
            'name' => 'IKS Admin',
            'description' => 'Verificar status de admin no IKS Admin',
            'no_servers' => 'Nenhum servidor com conexão IKS',
        ],
        'simpleadmin_admin' => [
            'name' => 'SimpleAdmin Admin',
            'description' => 'Verificar status de admin no SimpleAdmin',
            'no_servers' => 'Nenhum servidor com conexão SimpleAdmin',
        ],
        'gmbans_admin' => [
            'name' => 'GMBans Admin',
            'description' => 'Verificar status de admin no GM Bans',
            'no_servers' => 'Nenhum servidor com conexão GMBans',
        ],
        'amx_admin' => [
            'name' => 'Admin AMX Bans',
            'description' => 'Verificar status de admin no AMX Bans',
            'no_servers' => 'Nenhum servidor com conexão AMX Bans',
        ],
        // (mantive padrões repetitivos abaixo iguais, só traduzindo descrições)

        'levelranks_stats' => [
            'name' => 'Level Ranks',
            'description' => 'Verificar estatísticas no Level Ranks',
            'no_servers' => 'Nenhum servidor com conexão Level Ranks',
        ],
        'levelranks' => [
            'name' => 'Level Ranks',
            'description' => 'Verificar estatísticas no Level Ranks',
            'no_servers' => 'Nenhum servidor com conexão Level Ranks',
        ],
        'rankme_stats' => [
            'name' => 'RankMe',
            'description' => 'Verificar estatísticas no RankMe',
            'no_servers' => 'Nenhum servidor com conexão RankMe',
        ],
        'rankme' => [
            'name' => 'RankMe',
            'description' => 'Verificar estatísticas no RankMe',
            'no_servers' => 'Nenhum servidor com conexão RankMe',
        ],
        'fpsstats_stats' => [
            'name' => 'FPS Stats',
            'description' => 'Verificar estatísticas no FPS Stats',
            'no_servers' => 'Nenhum servidor com conexão FPS Stats',
        ],
        'fpsstats' => [
            'name' => 'FPS Stats',
            'description' => 'Verificar estatísticas no FPS Stats',
            'no_servers' => 'Nenhum servidor com conexão FPS Stats',
        ],
        'csstats_stats' => [
            'name' => 'CsStats',
            'description' => 'Verificar estatísticas no CsStats',
            'no_servers' => 'Nenhum servidor com conexão CsStats',
        ],
        'csstats' => [
            'name' => 'CsStats',
            'description' => 'Verificar estatísticas no CsStats',
            'no_servers' => 'Nenhum servidor com conexão CsStats',
        ],
        'csstatsxsql_stats' => [
            'name' => 'CsStats x SQL',
            'description' => 'Verificar estatísticas no CsStats x SQL',
            'no_servers' => 'Nenhum servidor com conexão CsStats x SQL',
        ],
        'csstatsxsql' => [
            'name' => 'CsStats x SQL',
            'description' => 'Verificar estatísticas no CsStats x SQL',
            'no_servers' => 'Nenhum servidor com conexão CsStats x SQL',
        ],
        'hlstatsce_stats' => [
            'name' => 'HLStatsX:CE',
            'description' => 'Verificar estatísticas no HLStatsX:CE',
            'no_servers' => 'Nenhum servidor com conexão HLStatsX:CE',
        ],
        'hlstatsce' => [
            'name' => 'HLStatsX:CE',
            'description' => 'Verificar estatísticas no HLStatsX:CE',
            'no_servers' => 'Nenhum servidor com conexão HLStatsX:CE',
        ],
        'aru_stats' => [
            'name' => 'Army Ranks Ultimate',
            'description' => 'Verificar estatísticas no Army Ranks Ultimate',
            'no_servers' => 'Nenhum servidor com conexão Army Ranks Ultimate',
        ],
        'aru' => [
            'name' => 'Army Ranks Ultimate',
            'description' => 'Verificar estatísticas no Army Ranks Ultimate',
            'no_servers' => 'Nenhum servidor com conexão Army Ranks Ultimate',
        ],
        'qranks_stats' => [
            'name' => 'QRanks',
            'description' => 'Verificar estatísticas no QRanks',
            'no_servers' => 'Nenhum servidor com conexão QRanks',
        ],
        'qranks' => [
            'name' => 'QRanks',
            'description' => 'Verificar estatísticas no QRanks',
            'no_servers' => 'Nenhum servidor com conexão QRanks',
        ],
        'k4system_stats' => [
            'name' => 'K4-System',
            'description' => 'Verificar estatísticas no K4-System (CS2)',
            'no_servers' => 'Nenhum servidor com conexão K4',
        ],
        'k4system' => [
            'name' => 'K4-System',
            'description' => 'Verificar estatísticas no K4-System (CS2)',
            'no_servers' => 'Nenhum servidor com conexão K4',
        ],
    ],

    'fields' => [
        'server' => 'Servidor',
        'any_server' => 'Qualquer servidor',
        'group' => 'Grupo',
        'group_placeholder' => 'Digite o nome do grupo',
        'group_any_placeholder' => 'Deixe vazio para qualquer grupo',
        'admin_group' => 'Grupo de Admin',
        'immunity' => 'Imunidade',
        'min_immunity' => 'Imunidade mínima',
        'flags' => 'Flags',
        'required_flags' => 'Flags obrigatórias',
        'flags_placeholder' => 'ex: abcz',
        'group_optional_placeholder' => 'Opcional se flags estiverem definidas',
        'comment' => 'Comentário',
        'password' => 'Senha',
        'password_placeholder' => 'Será gerada automaticamente',
        'command' => 'Comando RCON',
        'command_placeholder' => 'Digite o comando',
        'check_type' => 'Tipo de verificação',
        'has_ban' => 'Possui banimento',
        'no_ban' => 'Sem banimento',
        'metric' => 'Métrica',
        'operator' => 'Operador de comparação',
        'value' => 'Valor',
    ],

    'metrics' => [
        'kills' => 'Abates',
        'deaths' => 'Mortes',
        'score' => 'Pontuação / Experiência',
        'experience' => 'Experiência',
        'playtime' => 'Tempo de jogo (seg)',
        'headshots' => 'Headshots',
        'kd' => 'Relação K/D',
        'rank' => 'Posição no ranking',
        'round_win' => 'Rounds vencidos',
        'wins' => 'Vitórias',
    ],

    'operator' => [
        'gte' => 'maior ou igual',
        'gt' => 'maior que',
        'eq' => 'igual',
        'lt' => 'menor que',
        'lte' => 'menor ou igual',
    ],

    'categories' => [
        'vip' => 'VIP',
        'admin' => 'Administração',
        'ban' => 'Banimentos',
        'stats' => 'Estatísticas',
        'rcon' => 'RCON',
        'other' => 'Outros',
    ],

    'buttons' => [
        'save' => 'Salvar',
        'cancel' => 'Cancelar',
        'delete' => 'Excluir',
        'edit' => 'Editar',
        'add' => 'Adicionar',
    ],
];