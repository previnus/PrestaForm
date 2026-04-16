<?php
declare(strict_types=1);

namespace PrestaForm\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use PrestaForm\Service\ConditionEvaluator;

class ConditionEvaluatorTest extends TestCase
{
    private ConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new ConditionEvaluator();
    }

    /** Build a condition group array */
    private function group(string $target, string $action, string $logic, array $rules): array
    {
        return [
            'target_field' => $target,
            'action'       => $action,
            'logic'        => $logic,
            'rules'        => $rules,
        ];
    }

    /** Build a single rule array */
    private function rule(string $field, string $operator, string $value): array
    {
        return ['field' => $field, 'operator' => $operator, 'value' => $value];
    }

    public function testFieldVisibleWhenNoGroups(): void
    {
        $visible = $this->evaluator->getVisibleFields(
            ['name', 'email', 'msg'],
            [],
            []
        );
        $this->assertSame(['name', 'email', 'msg'], $visible);
    }

    public function testShowActionWithEqualsRuleMatching(): void
    {
        $groups = [
            $this->group('other-dept', 'show', 'AND', [
                $this->rule('department', 'equals', 'Other'),
            ]),
        ];
        $data   = ['department' => 'Other'];

        $visible = $this->evaluator->getVisibleFields(
            ['department', 'other-dept'],
            $groups,
            $data
        );

        $this->assertContains('other-dept', $visible);
    }

    public function testShowActionWithEqualsRuleNotMatching(): void
    {
        $groups = [
            $this->group('other-dept', 'show', 'AND', [
                $this->rule('department', 'equals', 'Other'),
            ]),
        ];
        $data   = ['department' => 'Sales'];

        $visible = $this->evaluator->getVisibleFields(
            ['department', 'other-dept'],
            $groups,
            $data
        );

        $this->assertNotContains('other-dept', $visible);
    }

    public function testHideActionWithMatchingRule(): void
    {
        $groups = [
            $this->group('phone', 'hide', 'AND', [
                $this->rule('contact-pref', 'equals', 'Email'),
            ]),
        ];
        $data   = ['contact-pref' => 'Email'];

        $visible = $this->evaluator->getVisibleFields(
            ['contact-pref', 'phone'],
            $groups,
            $data
        );

        $this->assertNotContains('phone', $visible);
    }

    public function testAndLogicRequiresAllRulesTrue(): void
    {
        $groups = [
            $this->group('extra', 'show', 'AND', [
                $this->rule('dept', 'equals', 'Other'),
                $this->rule('pref', 'equals', 'Phone'),
            ]),
        ];

        // Only first rule matches
        $data    = ['dept' => 'Other', 'pref' => 'Email'];
        $visible = $this->evaluator->getVisibleFields(['extra', 'dept', 'pref'], $groups, $data);
        $this->assertNotContains('extra', $visible);

        // Both rules match
        $data2   = ['dept' => 'Other', 'pref' => 'Phone'];
        $visible2 = $this->evaluator->getVisibleFields(['extra', 'dept', 'pref'], $groups, $data2);
        $this->assertContains('extra', $visible2);
    }

    public function testOrLogicRequiresAnyRuleTrue(): void
    {
        $groups = [
            $this->group('extra', 'show', 'OR', [
                $this->rule('dept', 'equals', 'Other'),
                $this->rule('pref', 'equals', 'Phone'),
            ]),
        ];

        // Only second rule matches — should still show
        $data    = ['dept' => 'Sales', 'pref' => 'Phone'];
        $visible = $this->evaluator->getVisibleFields(['extra', 'dept', 'pref'], $groups, $data);
        $this->assertContains('extra', $visible);

        // Neither matches
        $data2   = ['dept' => 'Sales', 'pref' => 'Email'];
        $visible2 = $this->evaluator->getVisibleFields(['extra', 'dept', 'pref'], $groups, $data2);
        $this->assertNotContains('extra', $visible2);
    }

    public function testOperatorNotEquals(): void
    {
        $groups = [
            $this->group('extra', 'show', 'AND', [
                $this->rule('dept', 'not_equals', 'Sales'),
            ]),
        ];

        $data    = ['dept' => 'Support'];
        $visible = $this->evaluator->getVisibleFields(['extra', 'dept'], $groups, $data);
        $this->assertContains('extra', $visible);

        $data2   = ['dept' => 'Sales'];
        $visible2 = $this->evaluator->getVisibleFields(['extra', 'dept'], $groups, $data2);
        $this->assertNotContains('extra', $visible2);
    }

    public function testOperatorContains(): void
    {
        $groups = [
            $this->group('extra', 'show', 'AND', [
                $this->rule('msg', 'contains', 'urgent'),
            ]),
        ];

        $data    = ['msg' => 'This is urgent please help'];
        $visible = $this->evaluator->getVisibleFields(['extra', 'msg'], $groups, $data);
        $this->assertContains('extra', $visible);
    }

    public function testOperatorIsEmpty(): void
    {
        $groups = [
            $this->group('hint', 'show', 'AND', [
                $this->rule('phone', 'is_empty', ''),
            ]),
        ];

        $visible  = $this->evaluator->getVisibleFields(['hint', 'phone'], $groups, ['phone' => '']);
        $this->assertContains('hint', $visible);

        $visible2 = $this->evaluator->getVisibleFields(['hint', 'phone'], $groups, ['phone' => '555-1234']);
        $this->assertNotContains('hint', $visible2);
    }

    public function testOperatorIsNotEmpty(): void
    {
        $groups = [
            $this->group('clear-btn', 'show', 'AND', [
                $this->rule('phone', 'is_not_empty', ''),
            ]),
        ];

        $visible  = $this->evaluator->getVisibleFields(['clear-btn', 'phone'], $groups, ['phone' => '555']);
        $this->assertContains('clear-btn', $visible);

        $visible2 = $this->evaluator->getVisibleFields(['clear-btn', 'phone'], $groups, ['phone' => '']);
        $this->assertNotContains('clear-btn', $visible2);
    }
}
