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

use Closure;
use Exception;
use ReflectionFunction;
use SplObjectStorage;
use Throwable;

/**
 * Exports values to inline strings.
 */
class InlineExporter implements Exporter
{
    /**
     * Create an inline exporter.
     *
     * Negative depths are treated as infinite depth.
     *
     * @param string $depth The depth.
     *
     * @return self An inline exporter.
     */
    public static function create($depth = -1)
    {
        return new self($depth);
    }

    /**
     * Export the supplied value.
     *
     * @param mixed &$value The value.
     *
     * @return string The exported value.
     */
    public function export(&$value)
    {
        $final = (object) [];
        $stack = [[&$value, $final, 0, gettype($value)]];
        $results = [];
        $seenObjects = new SplObjectStorage();
        $seenArrays = [];
        $arrayResults = [];
        $arrayId = 0;

        while (!empty($stack)) {
            $entry = array_shift($stack);
            $value = &$entry[0];
            $result = $entry[1];
            $currentDepth = $entry[2];
            $type = $entry[3];
            $results[] = $result;

            switch ($type) {
                case 'NULL':
                    $result->type = 'null';

                    break;

                case 'boolean':
                    if ($value) {
                        $result->type = 'true';
                    } else {
                        $result->type = 'false';
                    }

                    break;

                case 'integer':
                    $result->type = strval($value);

                    break;

                case 'double':
                    $result->type = sprintf('%e', $value);

                    break;

                case 'resource':
                    $result->type = 'resource#' . intval($value);

                    break;

                case 'string':
                    $result->type = json_encode($value, $this->jsonFlags);

                    break;

                case 'array':
                    if (isset($value[self::ARRAY_ID_KEY])) {
                        $id = $value[self::ARRAY_ID_KEY];
                    } else {
                        $id = $value[self::ARRAY_ID_KEY] = $arrayId++;
                    }

                    $seenArrays[$id] = &$value;

                    if (isset($arrayResults[$id])) {
                        $result->type = '&' . $id . '[]';

                        break;
                    }

                    $result->type = '#' . $id;

                    if ($this->depth > -1 && $currentDepth >= $this->depth) {
                        $count = count($value) - 1;

                        if ($count) {
                            $result->type .= '[:' . $count . ']';
                        } else {
                            $result->type .= '[]';
                        }

                        break;
                    }

                    $arrayResults[$id] = $result;

                    $result->children = [];
                    $result->sequence = true;
                    $sequenceKey = 0;

                    foreach ($value as $key => &$childValue) {
                        if (self::ARRAY_ID_KEY === $key) {
                            continue;
                        }

                        if ($result->sequence) {
                            if ($key !== $sequenceKey++) {
                                $result->map = true;
                                $result->sequence = false;
                            }
                        }

                        $keyResult = (object) [];
                        $valueResult = (object) [];
                        $result->children[] = [$keyResult, $valueResult];

                        $stack[] = [
                            $key,
                            $keyResult,
                            $currentDepth + 1,
                            gettype($key),
                        ];
                        $stack[] = [
                            &$childValue,
                            $valueResult,
                            $currentDepth + 1,
                            gettype($childValue),
                        ];
                    }

                    break;

                case 'object':
                    $hash = spl_object_hash($value);

                    if (isset($this->objectIds[$hash])) {
                        $id = $this->objectIds[$hash];
                    } else {
                        $id = $this->objectIds[$hash] = $this->objectSequence++;
                    }

                    if ($seenObjects->contains($value)) {
                        $result->type = '&' . $id . '{}';

                        break;
                    }

                    $isClosure = $value instanceof Closure;
                    $isException =
                        $value instanceof Throwable ||
                        $value instanceof Exception;

                    if ($isClosure) {
                        $result->type = 'Closure';
                    } else {
                        $result->type = get_class($value);
                    }

                    $phpValues = (array) $value;
                    unset($phpValues["\0gcdata"]);

                    if ($isException) {
                        unset(
                            $phpValues["\0*\0file"],
                            $phpValues["\0*\0line"],
                            $phpValues["\0Exception\0trace"],
                            $phpValues["\0Exception\0string"],
                            $phpValues['xdebug_message']
                        );
                    } elseif ($isClosure) {
                        $reflector = new ReflectionFunction($value);
                        $result->label =
                            basename($reflector->getFilename()) . ':' .
                            $reflector->getStartLine();
                        $phpValues = [];
                    }

                    $properties = [];
                    $propertyCounts = [];

                    foreach (
                        $phpValues as $propertyName => $propertyValue
                    ) {
                        if (
                            preg_match(
                                '/^\x00([^\x00]+)\x00([^\x00]+)$/',
                                $propertyName,
                                $matches
                            )
                        ) {
                            if (
                                '*' === $matches[1] ||
                                $result->type === $matches[1]
                            ) {
                                $propertyName = $realName = $matches[2];
                            } else {
                                $propertyName = $matches[2];
                                $realName =
                                    $matches[1] . '.' . $propertyName;
                            }

                            $properties[] = [
                                $propertyName,
                                $realName,
                                $propertyValue,
                            ];
                        } else {
                            $properties[] = [
                                $propertyName,
                                $propertyName,
                                $propertyValue,
                            ];
                        }

                        if (isset($propertyCounts[$propertyName])) {
                            $propertyCounts[$propertyName] += 1;
                        } else {
                            $propertyCounts[$propertyName] = 1;
                        }
                    }

                    $values = [];

                    foreach ($properties as $property) {
                        list($shortName, $realName, $propertyValue) =
                            $property;

                        if ($propertyCounts[$shortName] > 1) {
                            $values[$realName] = $propertyValue;
                        } else {
                            $values[$shortName] = $propertyValue;
                        }
                    }

                    if ($isException) {
                        if ('' === $values['message']) {
                            unset($values['message']);
                        }
                        if (0 === $values['code']) {
                            unset($values['code']);
                        }
                        if (!$values['previous']) {
                            unset($values['previous']);
                        }
                    }

                    if ('stdClass' === $result->type) {
                        $result->type = '';
                    }

                    $result->type .= '#' . $id;

                    if ($this->depth > -1 && $currentDepth >= $this->depth) {
                        if (empty($values)) {
                            $result->type .= '{}';
                        } else {
                            $result->type .= '{:' . count($values) . '}';
                        }

                        break;
                    }

                    $seenObjects->offsetSet($value, true);

                    $result->children = [];
                    $result->object = true;

                    foreach ($values as $key => &$childValue) {
                        $valueResult = (object) [];
                        $result->children[] = [$key, $valueResult];

                        $stack[] = [
                            &$childValue,
                            $valueResult,
                            $currentDepth + 1,
                            gettype($childValue),
                        ];
                    }

                    break;

                // @codeCoverageIgnoreStart
                default:
                    $result->type = '???';
                // @codeCoverageIgnoreEnd
            }
        }

        foreach (array_reverse($results) as $result) {
            $result->final = $result->type;

            if (isset($result->object)) {
                $result->final .= '{';
                $isFirst = true;

                foreach ($result->children as $pair) {
                    if (!$isFirst) {
                        $result->final .= ', ';
                    }

                    $result->final .= $pair[0] . ': ' . $pair[1]->final;
                    $isFirst = false;
                }

                $result->final .= '}';
            } elseif (isset($result->map)) {
                $result->final .= '[';
                $isFirst = true;

                foreach ($result->children as $pair) {
                    if (!$isFirst) {
                        $result->final .= ', ';
                    }

                    $result->final .=
                        $pair[0]->final . ': ' . $pair[1]->final;
                    $isFirst = false;
                }

                $result->final .= ']';
            } elseif (isset($result->sequence)) {
                $result->final .= '[';
                $isFirst = true;

                foreach ($result->children as $pair) {
                    if (!$isFirst) {
                        $result->final .= ', ';
                    }

                    $result->final .= $pair[1]->final;
                    $isFirst = false;
                }

                $result->final .= ']';
            }

            if (isset($result->label)) {
                $result->final .= '[' . $result->label . ']';
            }
        }

        foreach ($seenArrays as &$value) {
            unset($value[self::ARRAY_ID_KEY]);
        }

        return $final->final;
    }

    private function __construct($depth)
    {
        $this->depth = $depth;
        $this->objectSequence = 0;
        $this->objectIds = [];
        $this->jsonFlags = 0;

        if (defined('JSON_UNESCAPED_SLASHES')) {
            $this->jsonFlags |= JSON_UNESCAPED_SLASHES;
        }
        if (defined('JSON_UNESCAPED_UNICODE')) {
            $this->jsonFlags |= JSON_UNESCAPED_UNICODE;
        }
    }

    const ARRAY_ID_KEY = "\0__depict__\0";

    private static $instance;
    private $depth;
    private $objectSequence;
    private $objectIds;
    private $jsonFlags;
}
