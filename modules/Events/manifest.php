<?php

declare(strict_types=1);

/**
 * MANIFEST MODUL EVENTS — kontrak frontend.
 *
 * Widget-only (belum ada halaman penuh): modul ini menyediakan widget
 * 'calendar' utk dashboard (paritas legacy dashboard/widgets/calendar.php,
 * default area left-8). Halaman/menu + detail_tabs menyusul saat halaman
 * Calendar penuh di-front-end-kan.
 *
 * @return array{widgets: list<array{id: string, area: string, title: string, api: string}>}
 */
return [
    'widgets' => [
        [
            'id'    => 'calendar',
            'area'  => 'left-8',
            'title' => 'Calendar',
            'api'   => '/api/v1/events',
        ],
    ],
];
