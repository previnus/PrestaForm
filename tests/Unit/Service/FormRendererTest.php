<?php
declare(strict_types=1);

namespace PrestaForm\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use PrestaForm\Service\FormRenderer;
use PrestaForm\Service\ShortcodeParser;

class FormRendererTest extends TestCase
{
    private FormRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new FormRenderer(new ShortcodeParser());
    }

    private function makeForm(array $overrides = []): array
    {
        return array_merge([
            'id_form'          => 1,
            'name'             => 'Test Form',
            'template'         => '',
            'custom_css'       => '',
            'success_message'  => 'Thank you!',
            'captcha_provider' => 'none',
        ], $overrides);
    }

    public function testRendersTextInput(): void
    {
        $form = $this->makeForm(['template' => '[text* your-name]']);
        $html = $this->renderer->render($form, '/submit', 'tok123');

        $this->assertStringContainsString('<input', $html);
        $this->assertStringContainsString('type="text"', $html);
        $this->assertStringContainsString('name="your-name"', $html);
        $this->assertStringContainsString('required', $html);
    }

    public function testRendersOptionalInputWithoutRequired(): void
    {
        $form = $this->makeForm(['template' => '[text your-name]']);
        $html = $this->renderer->render($form, '/submit', 'tok');

        $this->assertStringNotContainsString('required', $html);
    }

    public function testRendersPlaceholder(): void
    {
        $form = $this->makeForm(['template' => '[text* n placeholder "Enter name"]']);
        $html = $this->renderer->render($form, '/submit', 'tok');

        $this->assertStringContainsString('placeholder="Enter name"', $html);
    }

    public function testRendersSelectWithOptions(): void
    {
        $form = $this->makeForm(['template' => '[select dept "Sales" "Support"]']);
        $html = $this->renderer->render($form, '/submit', 'tok');

        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('name="dept"', $html);
        $this->assertStringContainsString('<option value="Sales">Sales</option>', $html);
        $this->assertStringContainsString('<option value="Support">Support</option>', $html);
    }

    public function testRendersSelectWithIncludeBlank(): void
    {
        $form = $this->makeForm(['template' => '[select dept "A" "B" include_blank]']);
        $html = $this->renderer->render($form, '/submit', 'tok');

        $this->assertStringContainsString('<option value="">---</option>', $html);
    }

    public function testRendersTextarea(): void
    {
        $form = $this->makeForm(['template' => '[textarea msg rows:5]']);
        $html = $this->renderer->render($form, '/submit', 'tok');

        $this->assertStringContainsString('<textarea', $html);
        $this->assertStringContainsString('name="msg"', $html);
        $this->assertStringContainsString('rows="5"', $html);
    }

    public function testRendersSubmitButton(): void
    {
        $form = $this->makeForm(['template' => '[submit "Send"]']);
        $html = $this->renderer->render($form, '/submit', 'tok');

        $this->assertStringContainsString('<button type="submit"', $html);
        $this->assertStringContainsString('Send', $html);
    }

    public function testWrapsFormInContainer(): void
    {
        $form = $this->makeForm(['id_form' => 5, 'template' => '[submit "Go"]']);
        $html = $this->renderer->render($form, '/submit', 'tok');

        $this->assertStringContainsString('id="prestaform-5"', $html);
        $this->assertStringContainsString('<form ', $html);
        $this->assertStringContainsString('action="/submit"', $html);
    }

    public function testInjectsHiddenFormIdAndToken(): void
    {
        $form = $this->makeForm(['id_form' => 7, 'template' => '[submit "Go"]']);
        $html = $this->renderer->render($form, '/submit', 'mytoken');

        $this->assertStringContainsString('name="pf_form_id" value="7"', $html);
        $this->assertStringContainsString('name="token" value="mytoken"', $html);
    }

    public function testInjectsScopedCustomCss(): void
    {
        $form = $this->makeForm([
            'id_form'    => 3,
            'template'   => '[submit "Go"]',
            'custom_css' => '.pf-field { color: red; }',
        ]);
        $html = $this->renderer->render($form, '/submit', 'tok');

        $this->assertStringContainsString('<style>', $html);
        $this->assertStringContainsString('#prestaform-3', $html);
        $this->assertStringContainsString('.pf-field { color: red; }', $html);
    }

    public function testPreservesHtmlOutsideTags(): void
    {
        $form = $this->makeForm([
            'template' => '<label>Name</label>' . "\n" . '[text* your-name]',
        ]);
        $html = $this->renderer->render($form, '/submit', 'tok');

        $this->assertStringContainsString('<label>Name</label>', $html);
        $this->assertStringContainsString('<input', $html);
    }

    public function testInjectsConditionsJson(): void
    {
        $form = $this->makeForm(['id_form' => 2, 'template' => '[submit "Go"]']);
        $conditions = [
            ['action' => 'show', 'target_field' => 'msg', 'logic' => 'AND', 'rules' => []],
        ];
        $html = $this->renderer->render($form, '/submit', 'tok', $conditions);

        $this->assertStringContainsString('pfConditions', $html);
        $this->assertStringContainsString('"target_field":"msg"', $html);
    }
}
