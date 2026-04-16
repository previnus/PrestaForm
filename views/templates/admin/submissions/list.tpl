{extends file="helpers/view/view.tpl"}
{block name="override_tpl"}
<div class="panel">
  <div class="panel-heading"><i class="icon-inbox"></i> PrestaForm — Submissions</div>
  <div class="panel-body">
    <form method="get" action="{$base_url|escape}" class="form-inline" style="margin-bottom:15px">
      <input type="hidden" name="controller" value="AdminPrestaFormSubmissions">
      <select name="id_form" class="form-control">
        <option value="">All forms</option>
        {foreach $forms as $f}
          <option value="{$f.id_form|intval}" {if $filters.id_form==$f.id_form}selected{/if}>{$f.name|escape}</option>
        {/foreach}
      </select>
      &nbsp;
      <input type="date" name="date_from" class="form-control" value="{$filters.date_from|escape}" placeholder="From">
      <input type="date" name="date_to"   class="form-control" value="{$filters.date_to|escape}"   placeholder="To">
      &nbsp;
      <button type="submit" class="btn btn-default"><i class="icon-search"></i> Filter</button>
      <a href="{$base_url|escape}&action=export_csv&id_form={$filters.id_form|intval}"
         class="btn btn-default pull-right">
        <i class="icon-download"></i> Export CSV
      </a>
    </form>

    <p class="text-muted">{$total|intval} submission(s) found.</p>

    <table class="table tableDnD">
      <thead>
        <tr>
          <th>#</th><th>Form</th><th>Date</th><th>IP</th><th>Preview</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        {foreach $submissions as $s}
        <tr>
          <td>{$s.id_submission|intval}</td>
          <td>{$s.form_name|escape}</td>
          <td>{$s.date_add|escape}</td>
          <td>{$s.ip_address|escape}</td>
          <td>
            {foreach $s.data as $k => $v}
              {if $k@index < 3}
                <span class="label label-default">{$k|escape}</span>: {if is_array($v)}{$v|@implode:', '|escape}{else}{$v|escape|truncate:40}{/if}
                &nbsp;
              {/if}
            {/foreach}
          </td>
          <td>
            <a href="{$base_url|escape}&action=view&id_submission={$s.id_submission|intval}"
               class="btn btn-default btn-xs"><i class="icon-eye"></i> View</a>
            <a href="{$base_url|escape}&action=delete&id_submission={$s.id_submission|intval}"
               class="btn btn-danger btn-xs"
               onclick="return confirm('Delete this submission?')"><i class="icon-trash"></i></a>
          </td>
        </tr>
        {foreachelse}
        <tr><td colspan="6" class="text-muted text-center">No submissions found.</td></tr>
        {/foreach}
      </tbody>
    </table>

    {if $pages > 1}
    <ul class="pagination">
      {section name=p loop=$pages start=1}
        <li class="{if $page == $smarty.section.p.index}active{/if}">
          <a href="{$base_url|escape}&page={$smarty.section.p.index}">{$smarty.section.p.index}</a>
        </li>
      {/section}
    </ul>
    {/if}
  </div>
</div>
{/block}
