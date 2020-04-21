<?php

class CSV2JSON
{
    private const DATETIME_REGEXES = [
        // matches 'yyyy-mm-dd'
        'date' => '/([12]\d{3}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01]))/',
        // matches 'hh:mm:ss'
        'time' => '/(?:[01]\d|2[0123]):(?:[012345]\d):(?:[012345]\d)/',
        // matches 'yyyy-mm-dd hh:mm:ss'>
        'datetime' => '/([12]\d{3}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])) (?:[01]\d|2[0123]):(?:[012345]\d):(?:[012345]\d)/',
    ];

    private const BOOL_TYPE_TRUTHY = ['true', '1', 'on', 'yes'];
    private const BOOL_TYPE_FALSY = ['false', '0', 'off', 'no'];

    private const OPTION_SHORTKEY_CSV = 'f';
    private const OPTION_SHORTKEY_DESC = 'd';
    private const OPTION_LONGKEY_AGGREGATE = 'aggregate';
    private const OPTION_LONGKEY_FIELDS = 'fields';
    private const OPTION_LONGKEY_PRETTY = 'pretty';

    private const REQUIRED_OPTIONS = [
         self::OPTION_SHORTKEY_CSV,
         self::OPTION_SHORTKEY_DESC,
    ];

    private const SUPPORTED_TYPES = [
        'string', 'int', 'integer', 'float', 'bool', 'boolean', 'date', 'time', 'datetime',
    ];

    private ?string $aggregate;
    private string $csvpath;
    private array $columns;
    private array $data;
    private string $delimiter;
    private int $index = 0;
    private bool $pretty = false;
    private array $types;

    public function __construct(array $options)
    {
        foreach (self::REQUIRED_OPTIONS as $opt) {
            if (!isset($options[$opt])) {
                throw new \InvalidArgumentException(sprintf("could not read options: '%s' option missing", $opt));
            }

            $abspath = realpath(dirname(__FILE__) . '/' . $options[$opt]);
            if (!file_exists($abspath)) {
                throw new \Exception(sprintf("file '%s' does not exists", $options[$opt]));
            }
            if ($opt === self::OPTION_SHORTKEY_DESC) {
                $this->initializeTypes($abspath);
            }
            if ($opt === self::OPTION_SHORTKEY_CSV) {
                $this->csvpath = $abspath;
            }
        }

        $fields = $options[self::OPTION_LONGKEY_FIELDS] ?? null;
        $aggregate = $options[self::OPTION_LONGKEY_AGGREGATE] ?? null;

        $this->initializeColumns($fields);
        $this->initializeAggregate($aggregate);
        $this->pretty = isset($options[self::OPTION_LONGKEY_PRETTY]) ? true : false;
    }

    public function run(): string
    {
        $handle = fopen($this->csvpath, 'rb');

        while (false !== ($item = fgetcsv($handle, 0, $this->delimiter))) {
            $this->index++;
            // skip first line
            if ($this->index === 1) {
                continue;
            }

            $this->addRow($item);
        }

        fclose($handle);

        return $this->pretty
            ? json_encode($this->data, JSON_PRETTY_PRINT)
            : json_encode($this->data);
    }

    private function addRow($item): void
    {
        $item = array_filter($item, function ($key) {
            return array_key_exists($key, $this->columns);
        }, ARRAY_FILTER_USE_KEY);

        $item = array_combine($this->columns, array_map(function ($k, $v) {
            return $this->validate($k, $v, $this->types[$k]);
        }, $this->columns, $item));

        if (!$this->aggregate) {
            $this->data[] = $item;
        } else {
            if (!isset($this->data[$item[$this->aggregate]])) {
                $this->data[$item[$this->aggregate]] = [];
            }

            $this->data[$item[$this->aggregate]][] = array_filter(
                $item,
                function ($key) {
                    return $key != $this->aggregate;
                },
                ARRAY_FILTER_USE_KEY
            );
        }
    }

    private function initializeAggregate(?string $aggregate): void
    {
        if ($aggregate && !in_array($aggregate, $this->columns)) {
            throw new Exception(sprintf(
                "aggregate '%s' is not an existing field (%s)",
                $aggregate,
                join(',', $this->columns)
            ));
        }

        $this->aggregate = $aggregate;
    }

    private function initializeColumns(?string $fields): void
    {
        // take given fields option or csv header by default
        $head = $this->firstline($this->csvpath);
        $fields = $fields ?? $head;

        $this->delimiter = $this->findSeparator($head);

        // extract header row from csv
        $handle = fopen($this->csvpath, 'rb');
        $columns = fgetcsv($handle, 0, $this->delimiter);
        fclose($handle);

        // create fields array
        $fields = array_map(function ($field) {
            return trim($field);
        }, explode($this->findSeparator($fields), $fields));

        // filter columns by fields
        $columns = array_filter($columns, function ($name) use ($fields) {
            return in_array($name, $fields);
        });

        if (count($columns) !== count($fields)) {
            throw new \InvalidArgumentException('could not map all fields');
        }

        $this->columns = $columns;
    }

    private function initializeTypes($filepath): void
    {
        $contents = file_get_contents($filepath);
        $lines = explode("\n", $contents);

        // remove comments and empty lines
        $lines = array_filter($lines, function ($line) {
            return $line != '' && (isset($line[0]) && $line[0] !== '#');
        });

        // all supported types are nullable
        $supportedTypes = array_merge(self::SUPPORTED_TYPES, array_map(function ($type) {
            return sprintf('?%s', $type);
        }, self::SUPPORTED_TYPES));

        // check types and populate instance types
        foreach ($lines as $line) {
            preg_match('/(\S+?)\s*=\s*(\S+)/', $line, $matches);
            if (!in_array($matches[2], $supportedTypes)) {
                throw new \Exception(sprintf("invalid type '%s' in desc config file", $matches[2]));
            }

            $this->types[$matches[1]] = $matches[2];
        }
    }

    private function firstline($filepath): string
    {
        return fgets(fopen($filepath, 'rb'));
    }

    private function findSeparator(string $line): string
    {
        preg_match('/[^a-zA-Z\d\s:]/', $line, $matches);
        return $matches[0];
    }

    private function validate($column, $value, $type)
    {
        $nullable = strpos($type, '?') === 0;
        $type = $nullable ? substr($type, 1) : $type;

        if ('' === $value) {
            if (!$nullable) {
                throw new \Exception(sprintf("column '%s' at line %d cannot be empty", $column, $this->index));
            }

            return null;
        }

        if (!in_array($type, ['bool', 'boolean', 'date', 'time', 'datetime'])) {
            settype($value, $type);
        } else {
            if (in_array($type, ['bool', 'boolean'])) {
                $truthy = in_array($value, self::BOOL_TYPE_TRUTHY);
                $falsy = in_array($value, self::BOOL_TYPE_FALSY);

                if (!$truthy && !$falsy) {
                    throw new \Exception(sprintf(
                        "column '%s' at line %d value '%s' is not a valid bool",
                        $column,
                        $this->index,
                        $value,
                    ));
                }

                $value = $truthy ?? false;
            } else {
                preg_match(self::DATETIME_REGEXES[$type], $value, $matches);
                if (empty($matches)) {
                    throw new \Exception(sprintf(
                        "column '%s' at line %d value '%s' is not a valid %s",
                        $column,
                        $this->index,
                        $value,
                        $type
                    ));
                }
            }
        }

        return $value;
    }
}
