<?php

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
     * Accepts an array of options, including:
     *
     * 'depth' (defaults to -1):
     *   An integer that determines the depth to which Depict will export before
     *   truncating output. Negative values are treated as infinite depth.
     *
     * 'breadth' (defaults to -1):
     *   An integer that determines the number of sub-values that Depict will
     *   export before truncating output. Negative values are treated as
     *   infinite breadth.
     *
     * 'useShortNames' (defaults to true):
     *   When true, Depict will omit namespace information from exported symbol
     *   names.
     *
     * 'useShortPaths' (defaults to false):
     *   When true, Depict will export only the basename of closure paths.
     *
     * @param array<string,mixed> $options The options.
     *
     * @return self An inline exporter.
     */
    public static function create(array $options = array())
    {
        if (isset($options['depth'])) {
            $depth = $options['depth'];
        } else {
            $depth = -1;
        }

        if (isset($options['breadth'])) {
            $breadth = $options['breadth'];
        } else {
            $breadth = -1;
        }

        if (isset($options['useShortNames'])) {
            $useShortNames = $options['useShortNames'];
        } else {
            $useShortNames = true;
        }

        if (isset($options['useShortPaths'])) {
            $useShortPaths = $options['useShortPaths'];
        } else {
            $useShortPaths = false;
        }

        return new self($depth, $breadth, $useShortNames, $useShortPaths);
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
        $final = (object) array();
        $stack = array(array(&$value, $final, 0, gettype($value)));
        $results = array();
        $seenObjects = new SplObjectStorage();
        $seenArrays = array();
        $arrayResults = array();
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
                    $count = count($value) - 1;

                    if ($this->depth > -1 && $currentDepth >= $this->depth) {
                        if ($count) {
                            $result->type .= '[~' . $count . ']';
                        } else {
                            $result->type .= '[]';
                        }

                        break;
                    }

                    $arrayResults[$id] = $result;

                    $result->children = array();
                    $result->sequence = true;
                    $result->truncated = 0;
                    $sequenceKey = 0;
                    $currentBreadth = 0;

                    foreach ($value as $key => &$childValue) {
                        if (self::ARRAY_ID_KEY === $key) {
                            continue;
                        }

                        if (
                            $this->breadth > -1 &&
                            ++$currentBreadth > $this->breadth
                        ) {
                            $result->truncated = $count - $currentBreadth + 1;

                            break;
                        }

                        if ($result->sequence) {
                            if ($key !== $sequenceKey++) {
                                $result->map = true;
                                $result->sequence = false;
                            }
                        }

                        $keyResult = (object) array();
                        $valueResult = (object) array();
                        $result->children[] = array($keyResult, $valueResult);

                        $stack[] = array(
                            $key,
                            $keyResult,
                            $currentDepth + 1,
                            gettype($key),
                        );
                        $stack[] = array(
                            &$childValue,
                            $valueResult,
                            $currentDepth + 1,
                            gettype($childValue),
                        );
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
                    } elseif ($this->useShortNames) {
                        $atoms = explode('\\', get_class($value));
                        $result->type = array_pop($atoms);
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

                        if ($this->useShortPaths) {
                            $result->label =
                                basename($reflector->getFilename());
                        } else {
                            $result->label = $reflector->getFilename();
                        }

                        $result->label .= ':' . $reflector->getStartLine();
                        $phpValues = array();
                    }

                    $properties = array();
                    $propertyCounts = array();

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

                            $properties[] = array(
                                $propertyName,
                                $realName,
                                $propertyValue,
                            );
                        } else {
                            $properties[] = array(
                                $propertyName,
                                $propertyName,
                                $propertyValue,
                            );
                        }

                        if (isset($propertyCounts[$propertyName])) {
                            $propertyCounts[$propertyName] += 1;
                        } else {
                            $propertyCounts[$propertyName] = 1;
                        }
                    }

                    $values = array();

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
                    $count = count($values);

                    if ($this->depth > -1 && $currentDepth >= $this->depth) {
                        if ($count) {
                            $result->type .= '{~' . $count . '}';
                        } else {
                            $result->type .= '{}';
                        }

                        break;
                    }

                    $seenObjects->offsetSet($value, true);

                    $result->children = array();
                    $result->object = true;
                    $result->truncated = 0;
                    $currentBreadth = 0;

                    foreach ($values as $key => &$childValue) {
                        if (
                            $this->breadth > -1 &&
                            ++$currentBreadth > $this->breadth
                        ) {
                            $result->truncated = $count - $currentBreadth + 1;

                            break;
                        }

                        $valueResult = (object) array();
                        $result->children[] = array($key, $valueResult);

                        $stack[] = array(
                            &$childValue,
                            $valueResult,
                            $currentDepth + 1,
                            gettype($childValue),
                        );
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

                if ($result->truncated > 0) {
                    if (!$isFirst) {
                        $result->final .= ', ';
                    }

                    $result->final .= '~' . $result->truncated;
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

                if ($result->truncated > 0) {
                    if (!$isFirst) {
                        $result->final .= ', ';
                    }

                    $result->final .= '~' . $result->truncated;
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

                if ($result->truncated > 0) {
                    if (!$isFirst) {
                        $result->final .= ', ';
                    }

                    $result->final .= '~' . $result->truncated;
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

    private function __construct(
        $depth,
        $breadth,
        $useShortNames,
        $useShortPaths
    ) {
        $this->depth = $depth;
        $this->breadth = $breadth;
        $this->useShortNames = $useShortNames;
        $this->useShortPaths = $useShortPaths;
        $this->objectSequence = 0;
        $this->objectIds = array();
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
    private $breadth;
    private $useShortNames;
    private $useShortPaths;
    private $objectSequence;
    private $objectIds;
    private $jsonFlags;
}
