<?php
declare(strict_types=1);

namespace PrestaForm\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use PrestaForm\Service\ShortcodeParser;

class ShortcodeParserTest extends TestCase
{
    private ShortcodeParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ShortcodeParser();
    }

    public function testParsesSimpleTextField(): void
    {
        $fields = $this->parser->parse('[text* your-name]');
        $this->assertCount(1, $fields);
        $this->assertSame('text', $fields[0]['type']);
        $this->assertTrue($fields[0]['required']);
        $this->assertSame('your-name', $fields[0]['name']);
    }

    public function testParsesOptionalField(): void
    {
        $fields = $this->parser->parse('[email your-email]');
        $this->assertCount(1, $fields);
        $this->assertFalse($fields[0]['required']);
        $this->assertSame('email', $fields[0]['type']);
    }

    public function testParsesKeyValueParam(): void
    {
        $fields = $this->parser->parse('[text* your-name maxlength:200]');
        $this->assertSame('200', $fields[0]['params']['maxlength']);
    }

    public function testParsesNamedParamWithQuotedValue(): void
    {
        $fields = $this->parser->parse('[text* your-name placeholder "Your full name"]');
        $this->assertSame('Your full name', $fields[0]['params']['placeholder']);
    }

    public function testParsesSelectWithOptions(): void
    {
        $fields = $this->parser->parse('[select department "Sales" "Support" "Billing"]');
        $this->assertSame('select', $fields[0]['type']);
        $this->assertSame('department', $fields[0]['name']);
        $this->assertCount(3, $fields[0]['options']);
        $this->assertSame('Sales',   $fields[0]['options'][0]['label']);
        $this->assertSame('Sales',   $fields[0]['options'][0]['value']);
        $this->assertSame('Support', $fields[0]['options'][1]['label']);
    }

    public function testParsesSelectWithValueLabelPairs(): void
    {
        $fields = $this->parser->parse('[select dept "Sales|sales" "Support|support"]');
        $this->assertSame('Sales',   $fields[0]['options'][0]['label']);
        $this->assertSame('sales',   $fields[0]['options'][0]['value']);
        $this->assertSame('support', $fields[0]['options'][1]['value']);
    }

    public function testParsesBooleanFlag(): void
    {
        $fields = $this->parser->parse('[select dept include_blank "A" "B"]');
        $this->assertContains('include_blank', $fields[0]['flags']);
    }

    public function testParsesSubmitWithoutName(): void
    {
        $fields = $this->parser->parse('[submit "Send Message"]');
        $this->assertSame('submit', $fields[0]['type']);
        $this->assertSame('',       $fields[0]['name']);
        $this->assertSame('Send Message', $fields[0]['options'][0]['label']);
    }

    public function testParsesMultipleFieldsFromTemplate(): void
    {
        $template = <<<TPL
<label>Name</label>
[text* your-name placeholder "Full name"]

<label>Email</label>
[email* your-email]

[submit "Go"]
TPL;
        $fields = $this->parser->parse($template);
        $this->assertCount(3, $fields);
        $this->assertSame('text',   $fields[0]['type']);
        $this->assertSame('email',  $fields[1]['type']);
        $this->assertSame('submit', $fields[2]['type']);
    }

    public function testParsesCombinedKeyValueAndPlaceholder(): void
    {
        $fields = $this->parser->parse('[text* your-name placeholder "Name" maxlength:200]');
        $this->assertSame('Name', $fields[0]['params']['placeholder']);
        $this->assertSame('200',  $fields[0]['params']['maxlength']);
    }

    public function testParsesNumberFieldWithMinMax(): void
    {
        $fields = $this->parser->parse('[number qty min:1 max:100 step:1]');
        $this->assertSame('number', $fields[0]['type']);
        $this->assertSame('1',   $fields[0]['params']['min']);
        $this->assertSame('100', $fields[0]['params']['max']);
        $this->assertSame('1',   $fields[0]['params']['step']);
    }

    public function testParsesFileFieldWithAcceptAndLimit(): void
    {
        $fields = $this->parser->parse('[file attachment accept:.pdf,.docx limit:5mb]');
        $this->assertSame('file',       $fields[0]['type']);
        $this->assertSame('.pdf,.docx', $fields[0]['params']['accept']);
        $this->assertSame('5mb',        $fields[0]['params']['limit']);
    }
}
