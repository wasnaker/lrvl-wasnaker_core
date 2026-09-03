<?php

declare(strict_types=1);

return [
    // Override kebijakan auth platform: registrasi publik DIMATIKAN.
    // User hanya dibuat oleh admin via /api/v1/users.
    'auth' => [
        'allow_register'    => false,
        'super_admin_role'  => 'admin',
    ],

    // Settings khusus yang punya permission (settings:view / settings:edit).
    'settings' => [
        'restrict' => true,
    ],
];
