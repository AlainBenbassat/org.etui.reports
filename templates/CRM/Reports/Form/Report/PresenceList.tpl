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
        {assign var="etuiTableStyle" value="font-family: Georgia; border-collapse: collapse; border: none"}
        {assign var="etuiColHeaderStyle" value="font-family: Georgia; font-size: 14px; text-align: left; border-top: 1px solid #000000; border-bottom: 1px solid #000000; border-left: 1px dotted #000000; border-right: 1px dotted #000000; padding-left: 10px; padding-right: 10px; background-color: #b09ab6; color: #FFFFFF"}
        {assign var="etuiCellStyle" value="font-family: Georgia; font-size: 14px; border-bottom: 1px solid #000000; padding-left: 10px; border-left: 1px dotted #000000; border-right: 1px dotted #000000; padding-right: 10px;"}

        {include file="CRM/Reports/Form/Report/PresenceListRows.tpl"}

        <br>

        {include file="CRM/Report/Form/ErrorMessage.tpl"}
    </div>
{/if}
{if $outputMode == 'print'}
    <script type="text/javascript">
      window.print();
    </script>
{/if}

{if $outputMode == 'pdf'}
    <!-- footer on every page -->
    <div style="width: 100%; position: fixed; left: 0; bottom: -1.6em; font-size: 10px; line-height: 1.8;">
        <table width="100%">
            <tr>
                <td style="vertical-align: top; width:80px"><img src="https://crm.etui.org/sites/default/files/pictures/cvrep/eu-flag.jpg"></td>
                <td style="vertical-align: top;">The ETUI is co-funded by the European Union.<br>ETUI aisbl, {$currentYear}</td>
                <td style="vertical-align: top; text-align: right"><img style="width: 120px" src="https://crm.etui.org/sites/default/files/pictures/cvrep/logo-etui.png"</td>
            </tr>
        </table>
    </div>
{/if}

