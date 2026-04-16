{extends file="helpers/view/view.tpl"}
{block name="override_tpl"}
<div class="panel">
  <div class="panel-heading">
    <i class="icon-list"></i> PrestaForm — All Forms
    <a href="{$base_url|escape}&action=edit" class="btn btn-default btn-sm pull-right">
      <i class="icon-plus"></i> New Form
    </a>
  </div>
  <div class="panel-body">
    {if $forms|@count == 0}
      <p class="text-muted">No forms yet. <a href="{$base_url|escape}&action=edit">Create your first form.</a></p>
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
          <td><strong>{$f.name|escape}</strong></td>
          <td><code>{$f.slug|escape}</code></td>
          <td>{$f.submission_count|intval}</td>
          <td>
            {if $f.status == 'active'}
              <span class="label label-success">Active</span>
            {else}
              <span class="label label-default">Draft</span>
            {/if}
          </td>
          <td>
            <a href="{$base_url|escape}&action=edit&id_form={$f.id_form|intval}" class="btn btn-default btn-xs">
              <i class="icon-pencil"></i> Edit
            </a>
            <a href="{$base_url|escape}&action=delete&id_form={$f.id_form|intval}"
               class="btn btn-danger btn-xs"
               onclick="return confirm('Delete this form and all its submissions?')">
              <i class="icon-trash"></i> Delete
            </a>
          </td>
        </tr>
        {/foreach}
      </tbody>
    </table>
    {/if}
  </div>
</div>
{/block}
