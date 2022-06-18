<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;
use Ssch\TYPO3Rector\Set\Typo3SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->sets([Typo3LevelSetList::UP_TO_TYPO3_7, Typo3SetList::TYPO3_87, Typo3SetList::TCA_87]);
};
