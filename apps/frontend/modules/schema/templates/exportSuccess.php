<?php
    // auto-generated by sfPropelAdmin
    // date: 2015/01/27 16:41:38
?>
<?php use_helper('Object', 'Validation', 'ObjectAdmin', 'I18N', 'Date', 'Text') ?>

<div id="sf_admin_container">
    <div id="sf_admin_header">
        <?php include_partial('schema/show_header', array ( 'schema' => $schema, )) ?>
    </div>

    <div id="sf_admin_content">
        <?php include_partial('schema/show_messages', array ( 'schema' => $schema, 'labels' => $labels, )) ?>
        <?php include_partial('schema/export', array (
                    'schema'          => $schema,
                    'labels'          => $labels,
                    'language'        => $language,
                    'defaultLanguage' => $defaultLanguage,
                    'includeDeleted'  => $includeDeleted,
                    'includeDeprecated'  => $includeDeprecated,
              )) ?>

    </div>

    <div id="sf_admin_footer">
        <?php include_partial('schema/show_footer', array ( 'schema' => $schema, )) ?>
    </div>
</div>
