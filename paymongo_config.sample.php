<?php
/**
 * TEMPLATE LANG ITO - hindi ito binabasa ng app.
 *
 * SETUP (isang beses lang):
 *   Kopyahin ang file na ito papuntang:
 *       C:\xampp\secure_config\chubs_paymongo.php
 *   tapos ilagay dun ang totoong keys.
 *
 * Sa PowerShell:
 *   New-Item -ItemType Directory -Force C:\xampp\secure_config
 *   Copy-Item C:\xampp\htdocs\Chubs\Chubs\paymongo_config.sample.php C:\xampp\secure_config\chubs_paymongo.php
 *
 * BAKIT SA LABAS NG HTDOCS: ang C:/xampp/htdocs ang DocumentRoot ng Apache.
 * Kahit anong file sa loob nito ay pwedeng buksan ng kahit sino sa browser.
 * Ang secret key mo ay hindi dapat nandun kahit kailan.
 */
return [
    // Safety latch. Habang false ito, TEST mode pa rin kahit anong piliin sa
    // Admin > Store Settings. Dalawang hakbang bago makasingil ng totoong pera.
    'allow_live' => false,

    'base_url' => 'https://api.paymongo.com/v1',

    'test' => [
        'secret' => 'sk_test_xxxxxxxxxxxxxxxxxxxxxxxx',
        'public' => 'pk_test_xxxxxxxxxxxxxxxxxxxxxxxx',
    ],

    'live' => [
        'secret' => 'sk_live_xxxxxxxxxxxxxxxxxxxxxxxx',
        'public' => 'pk_live_xxxxxxxxxxxxxxxxxxxxxxxx',
    ],

    // Palitan ito ng sarili mong random na string (64 hex characters).
    'return_salt' => 'palitan-mo-ako-ng-random-na-mahabang-string',
];

