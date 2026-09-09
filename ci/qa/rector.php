<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/../../library'])
    ->withPhpSets()
    ->withSkip([
        \Rector\Php84\Rector\MethodCall\NewMethodCallWithoutParenthesesRector::class,
    ]);
