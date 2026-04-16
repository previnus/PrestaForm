{extends file="helpers/view/view.tpl"}
{block name="override_tpl"}
<div class="panel">
  <div class="panel-heading"><i class="icon-cog"></i> PrestaForm — Global Settings</div>
  <div class="panel-body">
    <form method="post">
      <input type="hidden" name="save_settings" value="1">

      <h4>Google reCAPTCHA</h4>
      <div class="row">
        <div class="col-md-6">
          <div class="form-group">
            <label>reCAPTCHA v2 Site Key</label>
            <input type="text" name="recaptcha_v2_site_key" class="form-control"
                   value="{$settings.recaptcha_v2_site_key|default:''|escape}">
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label>reCAPTCHA v2 Secret Key</label>
            <input type="text" name="recaptcha_v2_secret_key" class="form-control"
                   value="{$settings.recaptcha_v2_secret_key|default:''|escape}">
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label>reCAPTCHA v3 Site Key</label>
            <input type="text" name="recaptcha_v3_site_key" class="form-control"
                   value="{$settings.recaptcha_v3_site_key|default:''|escape}">
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label>reCAPTCHA v3 Secret Key</label>
            <input type="text" name="recaptcha_v3_secret_key" class="form-control"
                   value="{$settings.recaptcha_v3_secret_key|default:''|escape}">
          </div>
        </div>
      </div>

      <h4>Cloudflare Turnstile</h4>
      <div class="row">
        <div class="col-md-6">
          <div class="form-group">
            <label>Turnstile Site Key</label>
            <input type="text" name="turnstile_site_key" class="form-control"
                   value="{$settings.turnstile_site_key|default:''|escape}">
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label>Turnstile Secret Key</label>
            <input type="text" name="turnstile_secret_key" class="form-control"
                   value="{$settings.turnstile_secret_key|default:''|escape}">
          </div>
        </div>
      </div>

      <h4>Defaults</h4>
      <div class="form-group" style="max-width:250px">
        <label>Default Submission Retention (days) <small>leave blank for forever</small></label>
        <input type="number" name="default_retention_days" class="form-control" min="1"
               value="{$settings.default_retention_days|default:''|escape}">
      </div>

      <button type="submit" class="btn btn-primary"><i class="icon-save"></i> Save Settings</button>
    </form>
  </div>
</div>
{/block}
