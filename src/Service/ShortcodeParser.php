<?php
declare(strict_types=1);

namespace PrestaForm\Service;

class ShortcodeParser
{
    /**
     * Parse a form template string into an array of field definitions.
     *
     * @return list<array{type: string, required: bool, name: string, params: array<string, string>, options: list<array{label: string, value: string}>, flags: list<string>}>
     */
    public function parse(string $template): array
    {
        $fields = [];
        preg_match_all('/\[([^\]]+)\]/', $template, $matches);

        foreach ($matches[1] as $inner) {
            $field = $this->parseTag($inner);
            if ($field !== null) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Parse a single tag's inner content (everything between [ and ]).
     *
     * @return array{type: string, required: bool, name: string, params: array<string, string>, options: list<array{label: string, value: string}>, flags: list<string>}|null
     */
    public function parseTag(string $inner): ?array
    {
        $tokens = $this->tokenize($inner);
        if (empty($tokens)) {
            return null;
        }

        // First token: type with optional * suffix
        $typeToken = array_shift($tokens);
        $required  = str_ends_with($typeToken, '*');
        $type      = strtolower(rtrim($typeToken, '*'));

        // Second token: field name (slug-like identifier, not a quoted string)
        $name = '';
        if (!empty($tokens) && preg_match('/^[a-z][a-z0-9_-]*$/i', $tokens[0])) {
            $name = array_shift($tokens);
        }

        ['params' => $params, 'options' => $options, 'flags' => $flags] =
            $this->parseAttributes($tokens);

        return [
            'type'     => $type,
            'required' => $required,
            'name'     => $name,
            'params'   => $params,
            'options'  => $options,
            'flags'    => $flags,
        ];
    }

    /**
     * @param list<string> $tokens
     * @return array{params: array<string, string>, options: list<array{label: string, value: string}>, flags: list<string>}
     */
    private function parseAttributes(array $tokens): array
    {
        $params  = [];
        $options = [];
        $flags   = [];
        $i       = 0;
        $count   = count($tokens);

        while ($i < $count) {
            $token = $tokens[$i];

            if (str_contains($token, ':')) {
                // key:value — value may or may not be quoted
                [$key, $value] = explode(':', $token, 2);
                $params[trim($key)] = trim($value, '"\'');
                $i++;
            } elseif ($this->isQuoted($token)) {
                // Bare quoted string → option
                $value = trim($token, '"\'');
                if (str_contains($value, '|')) {
                    [$label, $val] = explode('|', $value, 2);
                    $options[] = ['label' => $label, 'value' => $val];
                } else {
                    $options[] = ['label' => $value, 'value' => $value];
                }
                $i++;
            } elseif (isset($tokens[$i + 1]) && $this->isQuoted($tokens[$i + 1])) {
                // bare_word "quoted value" → named param (e.g. placeholder "Name")
                $params[$token] = trim($tokens[$i + 1], '"\'');
                $i += 2;
            } else {
                // Bare keyword → boolean flag
                $flags[] = $token;
                $i++;
            }
        }

        return ['params' => $params, 'options' => $options, 'flags' => $flags];
    }

    /** @return list<string> */
    private function tokenize(string $str): array
    {
        preg_match_all('/"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|[^\s]+/', $str, $matches);
        return $matches[0];
    }

    private function isQuoted(string $token): bool
    {
        return strlen($token) >= 2
            && ((str_starts_with($token, '"') && str_ends_with($token, '"'))
                || (str_starts_with($token, "'") && str_ends_with($token, "'")));
    }
}
