<?php
declare(strict_types=1);

namespace PrestaForm\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use PrestaForm\Service\EmailRouter;

class EmailRouterTest extends TestCase
{
    private EmailRouter $router;

    protected function setUp(): void
    {
        $this->router = new EmailRouter();
    }

    public function testRenderTemplateSubstitutesFieldVars(): void
    {
        $result = $this->router->renderTemplate(
            'Hello [your-name], your email is [your-email].',
            ['your-name' => 'Jane', 'your-email' => 'jane@example.com'],
            []
        );
        $this->assertSame('Hello Jane, your email is jane@example.com.', $result);
    }

    public function testRenderTemplateSubstitutesSystemVars(): void
    {
        $result = $this->router->renderTemplate(
            'Form: [_form_title] submitted on [_date].',
            [],
            ['name' => 'Contact Form']
        );
        $this->assertStringContainsString('Form: Contact Form submitted on', $result);
    }

    public function testRenderTemplateHandlesMissingVar(): void
    {
        $result = $this->router->renderTemplate(
            'Hello [missing-var].',
            [],
            []
        );
        $this->assertSame('Hello [missing-var].', $result);
    }

    public function testRenderTemplateHandlesArrayFieldValue(): void
    {
        $result = $this->router->renderTemplate(
            'Interests: [interests].',
            ['interests' => ['PHP', 'PS9', 'Forms']],
            []
        );
        $this->assertSame('Interests: PHP, PS9, Forms.', $result);
    }

    public function testResolveAddressesReturnsBaseWhenNoRules(): void
    {
        $route = [
            'notify_addresses' => ['admin@store.com', 'boss@store.com'],
            'routing_rules'    => null,
        ];
        $addresses = $this->router->resolveAdminAddresses($route, []);
        $this->assertSame(['admin@store.com', 'boss@store.com'], $addresses);
    }

    public function testResolveAddressesOverridesWhenRuleMatches(): void
    {
        $route = [
            'notify_addresses' => ['admin@store.com'],
            'routing_rules'    => [
                ['field' => 'department', 'value' => 'Sales', 'email' => 'sales@store.com'],
            ],
        ];
        $addresses = $this->router->resolveAdminAddresses($route, ['department' => 'Sales']);
        $this->assertSame(['sales@store.com'], $addresses);
    }

    public function testResolveAddressesKeepsBaseWhenRuleDoesNotMatch(): void
    {
        $route = [
            'notify_addresses' => ['admin@store.com'],
            'routing_rules'    => [
                ['field' => 'department', 'value' => 'Sales', 'email' => 'sales@store.com'],
            ],
        ];
        $addresses = $this->router->resolveAdminAddresses($route, ['department' => 'Support']);
        $this->assertSame(['admin@store.com'], $addresses);
    }

    public function testResolveAddressesSubstitutesFieldVarInAddress(): void
    {
        $route = [
            'notify_addresses' => ['[your-email]', 'admin@store.com'],
            'routing_rules'    => null,
        ];
        $addresses = $this->router->resolveAdminAddresses($route, ['your-email' => 'user@example.com']);
        $this->assertSame(['user@example.com', 'admin@store.com'], $addresses);
    }

    public function testResolveAddressesDecodesJsonString(): void
    {
        $route = [
            'notify_addresses' => json_encode(['a@b.com', 'c@d.com']),
            'routing_rules'    => null,
        ];
        $addresses = $this->router->resolveAdminAddresses($route, []);
        $this->assertSame(['a@b.com', 'c@d.com'], $addresses);
    }
}
