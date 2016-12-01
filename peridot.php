<?php

use Evenement\EventEmitterInterface;
use Peridot\Console\Environment;
use Peridot\Reporter\CodeCoverageReporters;

return function (EventEmitterInterface $emitter) {
    $emitter->on('peridot.start', function (Environment $environment) {
        $environment->getDefinition()
            ->getArgument('path')->setDefault('test/suite');
    });

    $coverage = new CodeCoverageReporters($emitter);
    $coverage->register();
};
