<?php

/*
| SHIELD role metadata: human label for each role plus its sidebar navigation.
| The sidebar renders only items whose route already exists (Route::has).
| icon    = Heroicon name (Tailwind pages).
| skyicon = SkyDash icon class (simple-line-icons / themify) for the ported theme.
*/

return [
    'roles' => [
        'super_admin' => [
            'label' => 'Super Admin',
            'nav' => [
                ['label' => 'Dashboard',          'route' => 'super_admin.dashboard',      'icon' => 'squares-2x2',              'skyicon' => 'icon-grid'],
                ['label' => 'User Management',     'route' => 'super_admin.users.index',    'icon' => 'users',                    'skyicon' => 'ti-user'],
                ['label' => 'Government Agencies', 'route' => 'super_admin.agencies.index', 'icon' => 'building-office-2',        'skyicon' => 'icon-briefcase'],
            ],
        ],
        'admin' => [
            'label' => 'Katuparan Center',
            'nav' => [
                ['label' => 'Dashboard',  'route' => 'admin.dashboard',       'icon' => 'squares-2x2',              'skyicon' => 'icon-grid'],
                ['label' => 'Clusters',   'route' => 'admin.clusters.index',  'icon' => 'squares-plus',             'skyicon' => 'ti-layout'],
                ['label' => 'Agencies',   'route' => 'admin.agencies.index',  'icon' => 'building-office-2',        'skyicon' => 'icon-briefcase'],
                ['label' => 'Locations',  'route' => 'admin.locations.index', 'icon' => 'map-pin',                  'skyicon' => 'icon-location-pin'],
                ['label' => 'RCSP Forms', 'route' => 'admin.rcsp.index',      'icon' => 'document-check',           'skyicon' => 'icon-drawer'],
                ['label' => 'IMPLAN',     'route' => 'admin.implan.index',    'icon' => 'clipboard-document-list',  'skyicon' => 'icon-doc'],
                ['label' => 'Users',      'route' => 'admin.users.index',     'icon' => 'user-group',               'skyicon' => 'icon-user'],
            ],
        ],
        'lgu' => [
            'label' => 'DILG — LGU',
            'nav' => [
                ['label' => 'Dashboard',         'route' => 'lgu.dashboard',        'icon' => 'squares-2x2',             'skyicon' => 'icon-grid'],
                ['label' => 'RCSP Barangays',    'route' => 'lgu.rcsp.index',       'icon' => 'map',                     'skyicon' => 'icon-map', 'active' => ['lgu.monitoring.*']],
                ['label' => 'Evaluation Status', 'route' => 'lgu.evaluation.index', 'icon' => 'chart-bar',               'skyicon' => 'icon-graph'],
                ['label' => 'IMPLAN',            'route' => 'lgu.implan.index',     'icon' => 'clipboard-document-list', 'skyicon' => 'icon-doc'],
            ],
        ],
        'gov_agency' => [
            'label' => 'Government Agency',
            'nav' => [
                ['label' => 'Dashboard',   'route' => 'gov_agency.dashboard',   'icon' => 'squares-2x2',             'skyicon' => 'icon-grid'],
                ['label' => 'IMPLAN List', 'route' => 'gov_agency.implan.index', 'icon' => 'clipboard-document-list', 'skyicon' => 'icon-doc'],
            ],
        ],
        'mblrc' => [
            'label' => 'MBLRC',
            'nav' => [
                ['label' => 'Dashboard',     'route' => 'mblrc.dashboard', 'icon' => 'squares-2x2', 'skyicon' => 'icon-grid'],
                ['label' => 'Former Rebels', 'route' => 'mblrc.fr.index',  'icon' => 'user-group',  'skyicon' => 'icon-people'],
            ],
        ],
        '39th_ib' => [
            'label' => '39th IB',
            'nav' => [
                ['label' => 'Dashboard', 'route' => 'ib39.dashboard',   'icon' => 'squares-2x2', 'skyicon' => 'icon-grid'],
                ['label' => 'Add Area',  'route' => 'ib39.areas.index', 'icon' => 'map-pin',     'skyicon' => 'icon-map'],
                ['label' => 'Map',       'route' => 'ib39.map',         'icon' => 'map',         'skyicon' => 'icon-location-pin'],
                ['label' => 'Boundary Editor', 'route' => 'ib39.boundaries.editor', 'icon' => 'pencil-square', 'skyicon' => 'icon-pencil'],
                ['label' => 'Map Legend', 'route' => 'ib39.rules.index', 'icon' => 'swatch', 'skyicon' => 'icon-layers'],
            ],
        ],
        'afp' => [
            'label' => 'AFP',
            'nav' => [
                ['label' => 'Dashboard',      'route' => 'afp.dashboard',   'icon' => 'squares-2x2', 'skyicon' => 'icon-grid'],
                ['label' => 'RCSP Barangays', 'route' => 'afp.rcsp.index',  'icon' => 'map',         'skyicon' => 'icon-map'],
            ],
        ],
    ],
];
