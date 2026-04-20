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
        try {
            $service = new \PrestaForm\Service\SubmissionService();
            $result  = $service->handle(
                \Tools::getAllValues(),
                $_FILES,
                \Tools::getRemoteAddr()
            );
        } catch (\Throwable $e) {
            \PrestaShopLogger::addLog('PrestaForm submit error: ' . $e->getMessage(), 3);
            if ($this->isAjaxRequest()) {
                $this->outputJson(['success' => false, 'errors' => ['_form' => 'An unexpected error occurred. Please try again.']]);
                return;
            }
            $redirectUrl = \Tools::getReferer() ?: \Context::getContext()->link->getBaseLink();
            $sep = str_contains($redirectUrl, '?') ? '&' : '?';
            \Tools::redirectLink($redirectUrl . $sep . 'pf_error=1');
            return;
        }

        // AJAX submissions (non-file forms) send X-Requested-With: XMLHttpRequest.
        // Return JSON so the front-end JS can display inline success / errors.
        //
        // NOTE: we intentionally check the XHR header, NOT Content-Type.
        // fetch() + FormData always sends multipart/form-data even with no file
        // inputs, so Content-Type is an unreliable discriminator here.
        if ($this->isAjaxRequest()) {
            $this->outputJson($result);
            return;
        }

        // Native browser POST (file-upload forms bypass AJAX in our JS).
        // Redirect back to the referrer with a status query param; the JS reads it.
        $redirectUrl = \Tools::getReferer();
        // Validate Referer belongs to this shop to prevent open redirect
        $refererHost = parse_url($redirectUrl, PHP_URL_HOST) ?? '';
        $shopHost    = $_SERVER['HTTP_HOST'] ?? '';
        if ($refererHost !== $shopHost) {
            $redirectUrl = \Context::getContext()->link->getBaseLink();
        }
        $sep = str_contains($redirectUrl, '?') ? '&' : '?';
        \Tools::redirectLink($redirectUrl . $sep . ($result['success'] ? 'pf_success=1' : 'pf_error=1'));
    }

    private function isAjaxRequest(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
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
