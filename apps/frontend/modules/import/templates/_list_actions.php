<?php
    // auto-generated by sfPropelAdmin
    // date: 2015/01/25 21:06:33
?>
<ul class="sf_admin_actions">
    <?php if ($sf_user->hasObjectCredential($schema->getId(), 'schema', array(
          0 => array(
                0 => 'administrator',
                1 => 'schemaadmin',
          ),
    ))
    ): ?>
        <li><?php echo button_to(__('Import New CSV'), 'import/create?schema_id=' . $sf_params->get('schema_id') . '',
                  array(
                        'title' => 'Create',
                        'class' => 'sf_admin_action_create',
                  )) ?></li>

    <?php else: ?>
        &nbsp;
    <?php endif; ?></ul>
