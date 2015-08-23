<fieldset id="fieldset-ead-config"><legend><?php echo __('EAD configuration'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('ead_max_level',
                __('Maximum number of levels')); ?>
        </div>
        <div class='inputs five columns omega'>
            <?php echo $this->formText('ead_max_level', get_option('ead_max_level'), null); ?>
            <p class="explanation">
                <?php echo __('To set a maximum number of levels avoids heavy processing.')
                    . ' ' . __('The recommended is 15: 12 components, an archival description, a finding aid and one more.')
                    . ' ' . __('If "dsc" are used as item, this can be 30.'); ?>
            </p>
        </div>
    </div>
</fieldset>
<fieldset id="fieldset-ead-display"><legend><?php echo __('Display'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('ead_append_to_item', __('Automatic display')); ?>
        </div>
        <div class="inputs five columns omega">
            <div class="input-block">
                <ul>
                <?php
                    foreach ($display as $value) {
                        echo '<li>';
                        echo $this->formCheckbox('ead_append_to_item[]', $value,
                            array('checked' => in_array($value, $currentDisplay) ? 'checked' : ''));
                        echo ucfirst($value);
                        echo '</li>';
                    }
                ?>
                </ul>
                <p class="explanation">
                    <?php echo __('Ead metadata about an item can be displayed automatically on the page "items/show".'); ?>
                    <?php echo __('If unchecked, the theme should be adapted (shortcode or helper).'); ?>
                </p>
            </div>
        </div>
    </div>
</fieldset>
<?php /*
<fieldset id="fieldset-ead-oaipmh"><legend><?php echo __('OAI-PMH'); ?></legend>
    <p><?php
        if (plugin_is_active('OaiPmhRepository')):
            echo __('These options allow to select formats of metadata to expose via the the plugin %sOAI-PMH Repository%s.',
                '<a href="http://omeka.org/add-ons/plugins/oai-pmh-repository/">', '</a>');
        else:
            echo __('These options allow to define formats of metadata to expose when the plugin %sOAI-PMH Repository%s is installed.',
                '<a href="http://omeka.org/add-ons/plugins/oai-pmh-repository/">', '</a>');
        endif;
    ?></p>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('ead_oaipmh_expose',
                __('Expose EAD')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $this->formCheckbox('ead_oaipmh_expose', true,
                array('checked' => (boolean) get_option('ead_oaipmh_expose'))); ?>
            <p class="explanation">
                <?php echo __('If checked, exposes the collections with the EAD format.'); ?>
            </p>
        </div>
    </div>
</fieldset>
*/ ?>
