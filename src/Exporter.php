<?php

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
