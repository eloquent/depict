<?php

/*
 * This file is part of the Depict package.
 *
 * Copyright © 2016 Erin Millard
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

use Eloquent\Depict\InlineExporter;

describe('InlineExporter', function () {
    beforeEach(function () {
        $this->exporter = InlineExporter::create();
    });

    $data = [
        'null'                        => [null,                                            'null'],
        'boolean true'                => [true,                                            'true'],
        'boolean false'               => [false,                                           'false'],
        'integer 0'                   => [0,                                               '0'],
        'integer -0'                  => [-0,                                              '0'],
        'integer 1'                   => [1,                                               '1'],
        'integer -1'                  => [-1,                                              '-1'],
        'float 0.0'                   => [0.0,                                             '0.000000e+0'],
        'float -0.0'                  => [-0.0,                                            '0.000000e+0'],
        'float 1.0'                   => [1.0,                                             '1.000000e+0'],
        'float -1.0'                  => [-1.0,                                            '-1.000000e+0'],
        'resource STDIN'              => [STDIN,                                           'resource#1'],
        'resource STDOUT'             => [STDOUT,                                          'resource#2'],
        'string "a\nb"'               => ["a\nb",                                          '"a\nb"'],
        'array []'                    => [[],                                         '#0[]'],
        'array [1]'                   => [[1],                                        '#0[1]'],
        'array [1, 1]'                => [[1, 1],                                     '#0[1, 1]'],
        'array [1: 1]'                => [[1 => 1],                                   '#0[1: 1]'],
        'array [1: 1, 2: 2]'          => [[1 => 1, 2 => 2],                           '#0[1: 1, 2: 2]'],
        'array [1, [1, 1]]'           => [[1, [1, 1]],                           '#0[1, #1[1, 1]]'],
        'array [[1, 1], [1, 1]]'      => [[[1, 1], [1, 1]],                 '#0[#1[1, 1], #2[1, 1]]'],
        'generic object {a: 0}'       => [(object) ['a' => 0],                        '#0{a: 0}'],
        'generic object {a: 0, b: 1}' => [(object) ['a' => 0, 'b' => 1],              '#0{a: 0, b: 1}'],
        'generic object {a: {a: 0}}'  => [(object) ['a' => (object) ['a' => 0]], '#0{a: #1{a: 0}}'],
        'generic object {a: []}'      => [(object) ['a' => []],                  '#0{a: #0[]}'],
        'non-generic object'          => [new TestClass(),                                 'TestClass#0{}'],
    ];

    foreach ($data as $label => $row) {
        it(sprintf('should be able to export %s', $label), function () use ($row) {
            $value = $row[0];
            $expected = $row[1];
            $copy = $value;

            expect($this->exporter->export($value))->to->equal($expected);
            expect($copy)->to->equal($value);
        });
    }
});
