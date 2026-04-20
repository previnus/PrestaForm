<?php
declare(strict_types=1);

namespace PrestaForm\Service;

class ConditionEvaluator
{
    /**
     * Return the list of field names that should be visible, given condition groups and submitted data.
     *
     * Fields not targeted by any rule are always visible.
     * A 'show' rule makes the field visible only when its conditions pass.
     * A 'hide' rule makes the field hidden when its conditions pass (visible otherwise).
     *
     * @param list<string>               $allFields  All field names in the form
     * @param list<array<string, mixed>> $groups     Condition groups from pf_conditions
     * @param array<string, mixed>       $data       Submitted (or current) field values
     * @return list<string>
     */
    public function getVisibleFields(array $allFields, array $groups, array $data): array
    {
        // Build visibility map: field name → bool (true = visible, false = hidden)
        // Start with all fields visible
        $visibility = array_fill_keys($allFields, true);

        foreach ($groups as $group) {
            $target = (string) $group['target_field'];
            $action = (string) $group['action'];
            $logic  = (string) $group['logic'];
            $rules  = (array)  $group['rules'];

            if (!array_key_exists($target, $visibility)) {
                continue;
            }

            $conditionPasses = $this->evaluateGroup($rules, $logic, $data);

            if ($action === 'show') {
                // Field is visible only if condition passes; hidden otherwise
                $visibility[$target] = $conditionPasses;
            } elseif ($action === 'hide') {
                // Field is hidden if condition passes; visible otherwise
                $visibility[$target] = !$conditionPasses;
            }
        }

        return array_values(array_keys(array_filter($visibility)));
    }

    /**
     * Evaluate a group of rules with AND or OR logic.
     *
     * @param list<array<string, string>> $rules
     */
    private function evaluateGroup(array $rules, string $logic, array $data): bool
    {
        if (empty($rules)) {
            return true;
        }

        foreach ($rules as $rule) {
            $result = $this->evaluateRule($rule, $data);
            if ($logic === 'OR' && $result) {
                return true;
            }
            if ($logic === 'AND' && !$result) {
                return false;
            }
        }

        // AND: all passed; OR: none passed
        return $logic === 'AND';
    }

    /**
     * Evaluate a single condition rule.
     *
     * @param array<string, string> $rule
     * @param array<string, mixed>  $data
     */
    private function evaluateRule(array $rule, array $data): bool
    {
        $field    = (string) $rule['field'];
        $operator = (string) $rule['operator'];
        $value    = (string) $rule['value'];
        $raw      = $data[$field] ?? '';

        // Checkbox groups arrive as arrays (name="field[]" → $_POST['field'] = [...])
        if (is_array($raw)) {
            $values = array_map('strval', $raw);
            return match ($operator) {
                'equals'       => in_array($value, $values, true),
                'not_equals'   => !in_array($value, $values, true),
                'contains'     => (bool) array_filter($values, fn(string $v) => str_contains($v, $value)),
                'is_empty'     => empty(array_filter($values, fn(string $v) => $v !== '')),
                'is_not_empty' => (bool) array_filter($values, fn(string $v) => $v !== ''),
                default        => false,
            };
        }

        $actual = (string) $raw;
        return match ($operator) {
            'equals'       => $actual === $value,
            'not_equals'   => $actual !== $value,
            'contains'     => str_contains($actual, $value),
            'is_empty'     => $actual === '',
            'is_not_empty' => $actual !== '',
            default        => false,
        };
    }
}
