
{def $node=fetch( 'content', 'node', hash( 'node_id', $node_id ) )}

{def $err_tot = 0}
{def $objects = array()}
{def $object_child=''}

{foreach $errors as $object_id => $msg_array}
    {set $object_child=fetch( 'content', 'object', hash( 'object_id', $object_id ) )}
    {set $objects=$objects|append($object_child)}
    {set $err_tot=sum($err_tot, $msg_array|count())}
{/foreach}

{def $fields = $node.data_map.fields.content}
{def $fieldsParts = $fields|explode( '|' )
$class = fetch( content, class, hash( 'class_id', $fieldsParts[0]|trim() ) )
$identifiers = $fieldsParts[1]|explode( ',' )
}

<div class="content-view-full class-{$node.class_identifier} row">

    {if and( $node.parent_node_id|ne(1), $node.node_id|ne( ezini( 'NodeSettings', 'RootNode', 'content.ini' ) ) )}
        {include uri='design:nav/nav-section.tpl'}
    {/if}

    <div class="content-main{if or( $node.parent_node_id|eq(1), $node.node_id|eq( ezini( 'NodeSettings', 'RootNode', 'content.ini' ) ) )} wide{/if}">

        <h1>{$node.name|wash()}</h1>

        {if eq($errors|count,0)}

            <div class="alert alert-info" role="alert">
                <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
                Non sono stati riscontrati errori rilevanti nei dati inseriti, è pertanto possibile procedere con l'esportazione
            </div>

        {else}

            <div class="alert alert-danger" role="alert">
                <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
                Sono presenti errori negli oggetti esportati
            </div>

            <div class="alert alert alert-info" role="alert">
                <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
                Correggi gli oggetti nella lista sottostante, <u>modificandoli secondo le indicazioni</u>, poi esegui nuovamente l'esportazione.
            </div>

            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-3"><strong>Totale errori</strong></div>
                        <div class="col-md-9">{$err_tot}</div>
                        <div class="col-md-3"><strong>Oggetti da correggere</strong></div>
                        <div class="col-md-9">{$errors|count()}</div>
                    </div>
                </div>
            </div>

            <table class="table table-striped" cellspacing="0" class="list" summary="Elenco di oggetti di tipo {$class.name|wash()}">
                <thead>
                <tr>
                    {foreach $identifiers as $identifier}
                        {if is_set( $class.data_map[$identifier] )}
                            <th>{$class.data_map[$identifier].name|wash()}</th>
                        {/if}
                    {/foreach}
                    <td></td>
                </tr>
                </thead>
                <tbody>

                {def $msg_node_array = array())}
                {foreach $objects as $item}
                    {if $item.class_identifier|eq( $class.identifier )}
                        <tr>
                            {foreach $identifiers as $i => $identifier}
                                {if is_set( $item.data_map[$identifier] )}
                                    <td>
                                        {if $i|eq(0)}<a href={$item.url_alias|ezurl()}>{/if}
                                        {attribute_view_gui attribute=$item.data_map[$identifier] show_link=true()}
                                    </td>
                                {/if}
                            {/foreach}
                            <td>
                                <a href="{concat('/content/edit/', $item.id,"/",$item.default_language)}"  class="btn btn-default btn-sm">
                                    <span class="glyphicon glyphicon-pencil" aria-hidden="true"></span>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="{$identifiers|count()}">
                                <div class="alert alert alert-warning" role="alert">
                                    <p><b>Descrizione errori</b></p>
                                    <ul>
                                        {set $msg_node_array = $errors[$item.id]}
                                        {foreach $msg_node_array as $error_message}
                                            <li>{$error_message}</li>
                                        {/foreach}
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    {/if}
                {/foreach}
                </tbody>
            </table>

        {/if}

        <div class="buttonblock">
            <a href={$node.url_alias|ezurl()} class="btn btn-lg btn-danger">Annulla</a>
            <a href={concat("exportas/",$export_module,"/",$class.identifier,"/",$node.node_id,'/(errors)/',$errors|count())|ezurl()} class="btn btn-lg btn-success pull-right">Esporta</a>
        </div>

    </div>
</div>


