<?php
declare(strict_types=1);

namespace PrestaForm\Service;

class FormRenderer
{
    public function __construct(private readonly ShortcodeParser $parser) {}

    /**
     * Render a form array to HTML.
     *
     * @param array<string, mixed>       $form
     * @param list<array<string, mixed>> $conditionRules
     */
    public function render(
        array  $form,
        string $actionUrl,
        string $token,
        array  $conditionRules = []
    ): string {
        $formId   = (int) $form['id_form'];
        $template = (string) ($form['template'] ?? '');

        // Replace [tag] tokens in-place within the template HTML
        $body = preg_replace_callback(
            '/\[([^\]]+)\]/',
            fn(array $m): string => $this->renderTag($m[1]),
            $template
        ) ?? $template;

        $css           = $this->renderCss((string) ($form['custom_css'] ?? ''), $formId);
        $condJson      = json_encode($conditionRules, JSON_UNESCAPED_UNICODE) ?: '[]';
        $enctype       = str_contains($template, '[file') ? ' enctype="multipart/form-data"' : '';
        $captchaScript  = $this->renderCaptchaScript((string) ($form['captcha_provider'] ?? 'none'), $formId);
        $safeActionUrl  = htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8');
        $safeToken      = htmlspecialchars($token,     ENT_QUOTES, 'UTF-8');
        $safeSuccessMsg = htmlspecialchars((string) ($form['success_message'] ?? ''), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div id="prestaform-{$formId}" class="prestaform-wrapper" data-success-message="{$safeSuccessMsg}">
{$css}
<script>
window.pfConditions = window.pfConditions || {};
window.pfConditions[{$formId}] = {$condJson};
</script>
{$captchaScript}
<form action="{$safeActionUrl}" method="post"{$enctype} data-pf-id="{$formId}" novalidate>
<input type="hidden" name="pf_form_id" value="{$formId}">
<input type="hidden" name="token" value="{$safeToken}">
{$body}
</form>
</div>
HTML;
    }

    private function renderCss(string $css, int $formId): string
    {
        if ($css === '') {
            return '';
        }
        return "<style>\n#prestaform-{$formId} { {$css} }\n</style>";
    }

    private function renderCaptchaScript(string $provider, int $formId): string
    {
        return match ($provider) {
            'recaptcha_v2' =>
                '<script src="https://www.google.com/recaptcha/api.js" async defer></script>',
            'recaptcha_v3' =>
                '<script src="https://www.google.com/recaptcha/api.js" async defer></script>',
            'turnstile' =>
                '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>',
            default => '',
        };
    }

    private function renderTag(string $inner): string
    {
        $field = $this->parser->parseTag($inner);
        if ($field === null) {
            return "[{$inner}]";
        }
        return $this->renderField($field);
    }

    /** @param array{type: string, required: bool, name: string, params: array<string, string>, options: list<array{label: string, value: string}>, flags: list<string>} $field */
    private function renderField(array $field): string
    {
        $wrap = fn(string $html): string =>
            '<div class="pf-field pf-field-' . htmlspecialchars($field['type'])
            . '" data-pf-name="' . htmlspecialchars($field['name']) . '">'
            . $html . '</div>';

        return match ($field['type']) {
            'text', 'email', 'tel', 'number', 'date', 'hidden' => $wrap($this->renderInput($field)),
            'textarea'  => $wrap($this->renderTextarea($field)),
            'select'    => $wrap($this->renderSelect($field)),
            'checkbox'  => $wrap($this->renderCheckbox($field)),
            'radio'     => $wrap($this->renderRadio($field)),
            'file'      => $wrap($this->renderFile($field)),
            'recaptcha' => $wrap($this->renderRecaptcha($field)),
            'submit'    => $this->renderSubmit($field),
            default     => "[{$field['type']}]",
        };
    }

    /** @param array{type: string, required: bool, name: string, params: array<string, string>, options: list<array{label: string, value: string}>, flags: list<string>} $field */
    private function renderInput(array $field): string
    {
        $type     = htmlspecialchars($field['type']);
        $name     = htmlspecialchars($field['name']);
        $required = $field['required'] ? ' required' : '';
        $attrs    = $this->buildAttrs($field['params'], ['placeholder', 'maxlength', 'min', 'max', 'step']);

        if (isset($field['params']['default'])) {
            $attrs .= ' value="' . htmlspecialchars($field['params']['default']) . '"';
        }

        return "<input type=\"{$type}\" name=\"{$name}\"{$attrs}{$required}>";
    }

    /** @param array{type: string, required: bool, name: string, params: array<string, string>, options: list<array{label: string, value: string}>, flags: list<string>} $field */
    private function renderTextarea(array $field): string
    {
        $name     = htmlspecialchars($field['name']);
        $required = $field['required'] ? ' required' : '';
        $attrs    = $this->buildAttrs($field['params'], ['rows', 'cols', 'placeholder']);

        return "<textarea name=\"{$name}\"{$attrs}{$required}></textarea>";
    }

    /** @param array{type: string, required: bool, name: string, params: array<string, string>, options: list<array{label: string, value: string}>, flags: list<string>} $field */
    private function renderSelect(array $field): string
    {
        $name     = htmlspecialchars($field['name']);
        $required = $field['required'] ? ' required' : '';
        $options  = '';

        if (in_array('include_blank', $field['flags'], true)) {
            $options .= '<option value="">---</option>';
        }

        foreach ($field['options'] as $opt) {
            $label = htmlspecialchars($opt['label']);
            $value = htmlspecialchars($opt['value']);
            $options .= "<option value=\"{$value}\">{$label}</option>";
        }

        return "<select name=\"{$name}\"{$required}>{$options}</select>";
    }

    /** @param array{type: string, required: bool, name: string, params: array<string, string>, options: list<array{label: string, value: string}>, flags: list<string>} $field */
    private function renderCheckbox(array $field): string
    {
        $name     = htmlspecialchars($field['name']);
        $required = $field['required'] ? ' required' : '';
        $html     = '';

        if (count($field['options']) === 1) {
            $label = htmlspecialchars($field['options'][0]['label']);
            $value = htmlspecialchars($field['options'][0]['value']);
            $html  = "<label><input type=\"checkbox\" name=\"{$name}\" value=\"{$value}\"{$required}> {$label}</label>";
        } else {
            foreach ($field['options'] as $opt) {
                $label = htmlspecialchars($opt['label']);
                $value = htmlspecialchars($opt['value']);
                $html .= "<label><input type=\"checkbox\" name=\"{$name}[]\" value=\"{$value}\"> {$label}</label>";
            }
        }

        return $html;
    }

    /** @param array{type: string, required: bool, name: string, params: array<string, string>, options: list<array{label: string, value: string}>, flags: list<string>} $field */
    private function renderRadio(array $field): string
    {
        $name     = htmlspecialchars($field['name']);
        $required = $field['required'] ? ' required' : '';
        $html     = '';

        foreach ($field['options'] as $opt) {
            $label = htmlspecialchars($opt['label']);
            $value = htmlspecialchars($opt['value']);
            $html .= "<label><input type=\"radio\" name=\"{$name}\" value=\"{$value}\"{$required}> {$label}</label>";
        }

        return $html;
    }

    /** @param array{type: string, required: bool, name: string, params: array<string, string>, options: list<array{label: string, value: string}>, flags: list<string>} $field */
    private function renderFile(array $field): string
    {
        $name     = htmlspecialchars($field['name']);
        $required = $field['required'] ? ' required' : '';
        $accept   = isset($field['params']['accept'])
            ? ' accept="' . htmlspecialchars($field['params']['accept']) . '"'
            : '';

        return "<input type=\"file\" name=\"{$name}\"{$accept}{$required}>";
    }

    /** @param array{type: string, required: bool, name: string, params: array<string, string>, options: list<array{label: string, value: string}>, flags: list<string>} $field */
    private function renderRecaptcha(array $field): string
    {
        return '<div class="pf-recaptcha" data-pf-captcha="1"></div>';
    }

    /** @param array{type: string, required: bool, name: string, params: array<string, string>, options: list<array{label: string, value: string}>, flags: list<string>} $field */
    private function renderSubmit(array $field): string
    {
        $label = $field['options'][0]['label'] ?? 'Submit';
        return '<div class="pf-field pf-field-submit"><button type="submit" class="pf-submit">'
            . htmlspecialchars($label) . '</button></div>';
    }

    /** @param array<string, string> $params */
    private function buildAttrs(array $params, array $allowedKeys): string
    {
        $out = '';
        foreach ($allowedKeys as $key) {
            if (isset($params[$key])) {
                $out .= ' ' . $key . '="' . htmlspecialchars($params[$key]) . '"';
            }
        }
        return $out;
    }
}
