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
        if (!$this->isTokenValid()) {
            $this->outputJson(['success' => false, 'errors' => ['_form' => 'Invalid token.']]);
            return;
        }

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
            if ($result['success']) {
                \Tools::redirectLink($redirectUrl . (str_contains($redirectUrl, '?') ? '&' : '?') . 'pf_success=1');
            } else {
                \Tools::redirectLink($redirectUrl . (str_contains($redirectUrl, '?') ? '&' : '?') . 'pf_error=1');
            }
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
        ob_end_clean();
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
