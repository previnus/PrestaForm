{extends file=$layout}
{block name="content"}
  <section id="main">
    <div class="container">
      <h1>{$form_title|escape}</h1>
      {if $pf_success}
        <div class="alert alert-success">{$success_message|escape}</div>
      {elseif $pf_error}
        <div class="alert alert-danger">There was a problem submitting the form. Please try again.</div>
      {/if}
      {$form_html nofilter}
    </div>
  </section>
{/block}
