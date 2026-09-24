<?php

/*
| SHIELD role metadata: human label for each role plus its sidebar navigation.
| The sidebar renders only items whose route already exists (Route::has).
| icon    = Heroicon name (Tailwind pages).
| skyicon = SkyDash icon class (simple-line-icons / themify) for the ported theme.
*/

return [
    'jurisdiction' => [
        'province' => 'Davao del Sur',
    ],
    'shared_nav' => [
        ['label' => 'Messages', 'route' => 'chat.index', 'icon' => 'chat-bubble-left-right', 'skyicon' => 'icon-bubbles', 'active' => ['chat.*'], 'group' => 'Communication'],
    ],
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
                ['label' => 'Dashboard',     'route' => 'mblrc.dashboard', 'icon' => 'squares-2x2', 'skyicon' => 'icon-grid',   'group' => 'Overview'],
                ['label' => 'Former Rebels', 'route' => 'mblrc.fr.index',  'icon' => 'user-group',  'skyicon' => 'icon-people', 'group' => 'Reintegration'],
            ],
        ],
        '39th_ib' => [
            'label' => '39th IB',
            'nav' => [
                ['label' => 'Dashboard',       'route' => 'ib39.dashboard',          'icon' => 'squares-2x2',    'skyicon' => 'icon-grid',         'group' => 'Overview'],
                ['label' => 'FR Profiles',     'route' => 'ib39.fr-profiles.index',  'icon' => 'users',          'skyicon' => 'icon-people',       'group' => 'Former Rebels'],
                ['label' => 'FEA Processing',  'route' => 'ib39.fea.index',          'icon' => 'document-text',  'skyicon' => 'icon-docs',         'group' => 'Former Rebels'],
                ['label' => 'Area Management', 'route' => 'ib39.areas.index',        'icon' => 'map-pin',        'skyicon' => 'icon-map',          'group' => 'Area Monitoring'],
                ['label' => 'Area Map',        'route' => 'ib39.map',                'icon' => 'map',            'skyicon' => 'icon-location-pin', 'group' => 'Area Monitoring'],
                ['label' => 'Boundary Editor', 'route' => 'ib39.boundaries.editor',  'icon' => 'pencil-square',  'skyicon' => 'icon-pencil',       'group' => 'Area Monitoring'],
                ['label' => 'Map Legend',      'route' => 'ib39.rules.index',         'icon' => 'swatch',         'skyicon' => 'icon-layers',       'group' => 'Area Monitoring'],
            ],
        ],
        'afp' => [
            'label' => 'AFP',
            'nav' => [
                ['label' => 'Dashboard',      'route' => 'afp.dashboard',   'icon' => 'squares-2x2', 'skyicon' => 'icon-grid'],
                ['label' => 'RCSP Barangays', 'route' => 'afp.rcsp.index',  'icon' => 'map',         'skyicon' => 'icon-map'],
            ],
        ],
        'japic' => [
            'label' => 'JAPIC',
            'nav' => [
                ['label' => 'Dashboard', 'route' => 'japic.dashboard', 'icon' => 'squares-2x2', 'skyicon' => 'icon-grid'],
                ['label' => 'FRs for Certification', 'route' => 'japic.certifications.index', 'icon' => 'document-check', 'skyicon' => 'icon-docs'],
            ],
        ],
        'pswdo' => [
            'label' => 'PSWDO',
            'nav' => [
                ['label' => 'Dashboard', 'route' => 'pswdo.dashboard', 'icon' => 'squares-2x2', 'skyicon' => 'icon-grid', 'group' => 'Overview'],
                ['label' => 'FRs for Enrollment', 'route' => 'pswdo.enrollments.index', 'icon' => 'document-check', 'skyicon' => 'icon-docs', 'group' => 'Enrollment'],
            ],
        ],
    ],
];
