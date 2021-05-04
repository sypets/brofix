<?php

/**
 * @todo Once TYPO3 9 support is dropped, this can be removed and added to Configuration/Service.yaml
 */

return [
    'brofix:checklinks' => [
        'class' => \Sypets\Brofix\Command\CheckLinksCommand::class,
    ]
];
