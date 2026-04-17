<div class="panel">
  <div class="panel-heading"><i class="icon-cog"></i> PrestaForm — Global Settings</div>
  <div class="panel-body">

    <div class="alert alert-info" style="margin-bottom:20px">
      <strong><i class="icon-info-sign"></i> Global Settings</strong><br>
      These settings apply across <strong>all</strong> your PrestaForm forms.
      Enter your CAPTCHA API keys here once, then select the provider per-form in each form's <em>Settings</em> tab.
      Keys are stored securely in the PrestaShop configuration database.
    </div>

    <form method="post">
      <input type="hidden" name="save_settings" value="1">

      {* ── reCAPTCHA ── *}
      <h4><i class="icon-shield"></i> Google reCAPTCHA</h4>
      <p class="text-muted" style="margin-bottom:15px">
        reCAPTCHA protects your forms from bots and spam.
        Obtain your keys for free at <a href="https://www.google.com/recaptcha/admin" target="_blank">google.com/recaptcha/admin</a>.
        Register your domain there and copy the <strong>Site Key</strong> (used in the page HTML) and <strong>Secret Key</strong> (used server-side to verify responses) into the fields below.
        You only need to fill in the version you intend to use.
      </p>
      <div class="row">
        <div class="col-md-6">
          <div class="form-group">
            <label>reCAPTCHA v2 Site Key</label>
            <input type="text" name="recaptcha_v2_site_key" class="form-control"
                   value="{$settings.recaptcha_v2_site_key|default:''|escape}"
                   placeholder="6LeXXXXXAAAAAXXXXX…">
            <p class="help-block">The public key embedded in your page. Shown on the reCAPTCHA admin dashboard as "Site key".</p>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label>reCAPTCHA v2 Secret Key</label>
            <input type="text" name="recaptcha_v2_secret_key" class="form-control"
                   value="{$settings.recaptcha_v2_secret_key|default:''|escape}"
                   placeholder="6LeXXXXXAAAAAXXXXX…">
            <p class="help-block">The private key used to verify responses server-side. Never share this key publicly.</p>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label>reCAPTCHA v3 Site Key</label>
            <input type="text" name="recaptcha_v3_site_key" class="form-control"
                   value="{$settings.recaptcha_v3_site_key|default:''|escape}"
                   placeholder="6LeXXXXXAAAAAXXXXX…">
            <p class="help-block">reCAPTCHA v3 is invisible — no user interaction required. It assigns a risk score to each submission. Use a separate key pair registered for v3 in the Google reCAPTCHA console.</p>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label>reCAPTCHA v3 Secret Key</label>
            <input type="text" name="recaptcha_v3_secret_key" class="form-control"
                   value="{$settings.recaptcha_v3_secret_key|default:''|escape}"
                   placeholder="6LeXXXXXAAAAAXXXXX…">
            <p class="help-block">The private key for v3 server-side verification. Keep this secret.</p>
          </div>
        </div>
      </div>

      {* ── Cloudflare Turnstile ── *}
      <h4 style="margin-top:20px"><i class="icon-cloud"></i> Cloudflare Turnstile</h4>
      <p class="text-muted" style="margin-bottom:15px">
        Turnstile is Cloudflare's privacy-friendly alternative to reCAPTCHA — no visible challenge, no tracking.
        Get your keys at <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank">dash.cloudflare.com &rsaquo; Turnstile</a>.
        Add your domain there and copy the keys below.
      </p>
      <div class="row">
        <div class="col-md-6">
          <div class="form-group">
            <label>Turnstile Site Key</label>
            <input type="text" name="turnstile_site_key" class="form-control"
                   value="{$settings.turnstile_site_key|default:''|escape}"
                   placeholder="0x4AAAAAAA…">
            <p class="help-block">The public key included in your page to render the Turnstile widget.</p>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label>Turnstile Secret Key</label>
            <input type="text" name="turnstile_secret_key" class="form-control"
                   value="{$settings.turnstile_secret_key|default:''|escape}"
                   placeholder="0x4AAAAAAA…">
            <p class="help-block">The private key used to verify responses server-side. Keep this secret.</p>
          </div>
        </div>
      </div>

      {* ── Defaults ── *}
      <h4 style="margin-top:20px"><i class="icon-tasks"></i> Defaults</h4>
      <div class="form-group" style="max-width:280px">
        <label>Default Submission Retention (days)</label>
        <input type="number" name="default_retention_days" class="form-control" min="1"
               value="{$settings.default_retention_days|default:''|escape}"
               placeholder="e.g. 365">
        <p class="help-block">
          How many days to keep submissions in the database before they are automatically deleted.
          Leave blank to keep all submissions forever.
          Individual forms can override this value in their own <em>Settings</em> tab.
          Recommended: <strong>365</strong> days for GDPR-conscious stores.
        </p>
      </div>

      <button type="submit" class="btn btn-primary"><i class="icon-save"></i> Save Settings</button>
    </form>
  </div>
</div>
