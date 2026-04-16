<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class PrestaformSubmitModuleFrontController extends ModuleFrontController
{
    public bool $ajax = true;

    public function postProcess(): void
    {
        $service = new \PrestaForm\Service\SubmissionService();
        $result  = $service->handle(
            \Tools::getAllValues(),
            $_FILES,
            \Tools::getRemoteAddr()
        );

        // If form contains file field, form POSTs normally (not AJAX).
        // Redirect to current page with success query param.
        if ($this->isMultipart()) {
            $redirectUrl = \Tools::getReferer();
            // Validate Referer belongs to this shop to prevent open redirect
            $refererHost = parse_url($redirectUrl, PHP_URL_HOST) ?? '';
            $shopHost    = $_SERVER['HTTP_HOST'] ?? '';
            if ($refererHost !== $shopHost) {
                $redirectUrl = \Context::getContext()->link->getBaseLink();
            }
            $sep = str_contains($redirectUrl, '?') ? '&' : '?';
            \Tools::redirectLink($redirectUrl . $sep . ($result['success'] ? 'pf_success=1' : 'pf_error=1'));
            return;
        }

        $this->outputJson($result);
    }

    private function isMultipart(): bool
    {
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        return str_contains($ct, 'multipart/form-data');
    }

    private function outputJson(array $data): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
