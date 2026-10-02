<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use RuntimeException;

/**
 * Lee las filas de un volcado SQL de phpMyAdmin/mysqldump sin cargarlo en una
 * base: busca los `INSERT INTO `tabla` (columnas) VALUES (...), (...);` y
 * devuelve cada fila como arreglo columna => valor (NULL como null, números y
 * textos como string, con sus escapes resueltos).
 */
class SqlDumpReader
{
    private string $sql;

    public function __construct(string $path)
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("No se puede leer el archivo {$path}.");
        }

        $this->sql = (string) file_get_contents($path);
    }

    public static function fromString(string $sql): self
    {
        $reader = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $reader->sql = $sql;

        return $reader;
    }

    /** @return list<array<string, string|null>> */
    public function rows(string $table): array
    {
        $rows = [];
        $pattern = '/INSERT INTO `'.preg_quote($table, '/').'`\s*\(([^)]*)\)\s*VALUES\s*/i';
        $offset = 0;

        while (preg_match($pattern, $this->sql, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $columns = array_map(fn ($c) => trim($c, " `\t\r\n"), explode(',', $match[1][0]));
            $position = $match[0][1] + strlen($match[0][0]);

            foreach ($this->tuples($position) as $values) {
                if (count($values) !== count($columns)) {
                    throw new RuntimeException("Fila de `{$table}` con ".count($values).' valores para '.count($columns).' columnas.');
                }
                $rows[] = array_combine($columns, $values);
            }

            $offset = $position;
        }

        return $rows;
    }

    /**
     * Recorre las tuplas `(...)` desde $position hasta el `;` que cierra el INSERT.
     *
     * @return \Generator<int, list<string|null>>
     */
    private function tuples(int &$position): \Generator
    {
        $length = strlen($this->sql);

        while ($position < $length) {
            $char = $this->sql[$position];

            if ($char === '(') {
                $position++;
                yield $this->tuple($position);

                continue;
            }

            $position++;
            if ($char === ';') {
                return;
            }
        }
    }

    /** @return list<string|null> */
    private function tuple(int &$position): array
    {
        $values = [];
        $length = strlen($this->sql);

        while ($position < $length) {
            $char = $this->sql[$position];

            if ($char === ' ' || $char === "\n" || $char === "\r" || $char === "\t" || $char === ',') {
                $position++;

                continue;
            }

            if ($char === ')') {
                $position++;

                return $values;
            }

            if ($char === "'") {
                $values[] = $this->quoted($position);

                continue;
            }

            // Literal sin comillas: número o NULL.
            $start = $position;
            while ($position < $length && ! in_array($this->sql[$position], [',', ')'], true)) {
                $position++;
            }
            $literal = trim(substr($this->sql, $start, $position - $start));
            $values[] = strtoupper($literal) === 'NULL' ? null : $literal;
        }

        throw new RuntimeException('El volcado termina en medio de una fila.');
    }

    private function quoted(int &$position): string
    {
        $position++; // comilla de apertura
        $value = '';
        $length = strlen($this->sql);

        while ($position < $length) {
            $char = $this->sql[$position];

            if ($char === '\\') {
                $next = $this->sql[$position + 1] ?? '';
                $value .= match ($next) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    '0' => "\0",
                    'Z' => "\x1a",
                    default => $next,
                };
                $position += 2;

                continue;
            }

            if ($char === "'") {
                // '' dentro de un texto es una comilla escapada.
                if (($this->sql[$position + 1] ?? '') === "'") {
                    $value .= "'";
                    $position += 2;

                    continue;
                }
                $position++;

                return $value;
            }

            $value .= $char;
            $position++;
        }

        throw new RuntimeException('El volcado termina en medio de un texto.');
    }
}
