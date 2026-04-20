<div class="panel">
  <div class="panel-heading">
    <i class="icon-list"></i> PrestaForm — All Forms
    <a href="{$base_url|escape}&action=edit" class="btn btn-default btn-sm pull-right">
      <i class="icon-plus"></i> New Form
    </a>
  </div>
  <div class="panel-body">

    <p class="text-muted" style="margin-bottom:15px">
      Each form you create can be embedded anywhere on your storefront using a simple shortcode.
      Click <strong>New Form</strong> to get started, or <strong>Edit</strong> an existing form to update its fields, emails, webhooks, and settings.
      <strong>Active</strong> forms accept submissions from visitors; <strong>Draft</strong> forms are hidden until you publish them.
    </p>

    {if $forms_count == 0}
      <div class="alert alert-info">
        <i class="icon-info-sign"></i> No forms yet.
        <a href="{$base_url|escape}&action=edit" class="alert-link">Create your first form</a> — it only takes a minute.
      </div>
    {else}
    <table class="table tableDnD">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Slug</th>
          <th>Submissions</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        {foreach $forms as $f}
        <tr>
          <td>{$f.id_form|intval}</td>
          <td><strong>{$f.name|default:''|escape}</strong></td>
          <td><code>{$f.slug|default:''|escape}</code></td>
          <td>
            {if ($f.submission_count|default:0) > 0}
              <a href="{$submissions_url|escape}&id_form={$f.id_form|intval}">
                {$f.submission_count|intval}
              </a>
            {else}
              0
            {/if}
          </td>
          <td>
            {if ($f.status|default:'') == 'active'}
              <span class="label label-success">Active</span>
            {else}
              <span class="label label-default">Draft</span>
            {/if}
          </td>
          <td>
            <a href="{$base_url|escape}&action=edit&id_form={$f.id_form|intval}" class="btn btn-default btn-xs">
              <i class="icon-pencil"></i> Edit
            </a>
            <a href="{$submissions_url|escape}&id_form={$f.id_form|intval}" class="btn btn-info btn-xs">
              <i class="icon-inbox"></i> Submissions
            </a>
            <form method="post" action="{$base_url|escape}" style="display:inline"
                  onsubmit="return confirm('Delete this form and all its submissions? This cannot be undone.')">
              <input type="hidden" name="action"  value="delete">
              <input type="hidden" name="id_form" value="{$f.id_form|intval}">
              <button type="submit" class="btn btn-danger btn-xs">
                <i class="icon-trash"></i> Delete
              </button>
            </form>
          </td>
        </tr>
        {/foreach}
      </tbody>
    </table>
    {/if}
  </div>
</div>
