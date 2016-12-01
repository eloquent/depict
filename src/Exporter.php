<?php

/*
 * This file is part of the Depict package.
 *
 * Copyright © 2016 Erin Millard
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Eloquent\Depict;

/**
 * The interface implemented by exporters.
 */
interface Exporter
{
    /**
     * Export the supplied value.
     *
     * @param mixed &$value The value.
     *
     * @return string The exported value.
     */
    public function export(&$value);
}
