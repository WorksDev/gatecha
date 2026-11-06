<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'GateCHA for EXT:form',
    'description' => 'GateCHA (self-hosted ALTCHA) spam protection for TYPO3 forms',
    'category' => 'fe',
    'version' => '1.0.0',
    'state' => 'stable',
    'constraints' => [
        'depends' => [
            'typo3' => '14.0.0-14.3.99',
            'form' => '14.3.0-14.3.99',
        ],
    ],
];
