{* this div is being used to apply special css *}
{if $section eq 1}
    <div class="crm-block crm-content-block crm-report-layoutGraph-form-block">
        {*include the graph*}
        {include file="CRM/Report/Form/Layout/Graph.tpl"}
    </div>
{elseif $section eq 2}
    <div class="crm-block crm-content-block crm-report-layoutTable-form-block">
        {*include the table layout*}
        {include file="CRM/Report/Form/Layout/Table.tpl"}
    </div>
{else}
    {if $criteriaForm OR $instanceForm OR $instanceFormError}
        <div class="crm-block crm-form-block crm-report-field-form-block">
            {include file="CRM/Report/Form/Fields.tpl"}
        </div>
    {/if}
    <div class="crm-block crm-content-block crm-report-form-block">
        {*include actions*}
        {include file="CRM/Report/Form/Actions.tpl"}


        {*include the graph*}
        {include file="CRM/Report/Form/Layout/Graph.tpl"}

        {*include the table layout*}
        {*include file="CRM/Report/Form/Layout/Table.tpl"*}
        {assign var="etuiTableStyle" value="border-collapse: collapse; font-family: Arial Narrow; border: none"}
        {assign var="etuiColHeaderStyle" value="text-align: left; border-top: 1px solid #000000; border-bottom: 1px solid #000000; border-left: 0px solid #FFFFFF; border-right: 0px solid #FFFFFF; padding-left: 10px; padding-right: 10px; background-color: #b09ab6; color: #FFFFFF"}
        {assign var="etuiCellStyle" value="border-bottom: 1px solid #000000; padding-left: 10px; border-left: 0px solid #FFFFFF; border-right: 0px solid #FFFFFF; padding-right: 10px;"}

        {include file="CRM/Reports/Form/Report/PresenceListRows.tpl"}

        <br />

        {include file="CRM/Report/Form/ErrorMessage.tpl"}
    </div>
{/if}
{if $outputMode == 'print'}
    <script type="text/javascript">
      window.print();
    </script>
{/if}

<!-- footer on every page -->
<div style="width: 100%; position: fixed; left: 0; bottom: -1.6em; font-size: 10px; line-height: 1.8;">
    <table width="100%">
        <tr>
            <td style="vertical-align: top;"><img src="https://crm.etui.org/sites/default/files/pictures/cvrep/eu-flag.jpg"></td>
            <td style="vertical-align: top;">The ETUI is financially supported by the European Union.<br>ETUI aisbl, {$currentYear}</td>
            <td style="vertical-align: top; text-align: right"><img src="https://crm.etui.org/sites/default/files/pictures/cvrep/logo-etui.png"</td>
        </tr>
    </table>
</div>