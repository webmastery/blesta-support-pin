<?php

class AdminManagePlugin extends AppController
{
    /**
     * Performs necessary initialization
     */
    private function init()
    {
        // Require login
        $this->parent->requireLogin();

        Language::loadLang('support_pin_plugin', null, PLUGINDIR . 'support_pin' . DS . 'language' . DS);
        $this->view->setView(null, 'SupportPin.default');

        // Set the page title
        $this->parent->structure->set(
            'page_title',
            Language::_(
                'SupportPinPlugin.admin_' . Loader::fromCamelCase($this->action ? $this->action : 'index') . '.title',
                true
            )
        );
    }

    /**
     * Returns the view to be rendered when managing this plugin
     */
    public function index()
    {
        $this->init();
        $this->uses([
            'SupportPin.SupportPinSettings',
            'SupportPin.ClientPin'
        ]);

        $settings = $this->SupportPinSettings->getAll();

        if (!empty($this->post)) {
            $defaults = $this->SupportPinSettings->getDefaultSettings();
            $updated_settings = $this->SupportPinSettings->update([
                'interval' => $this->Html->ifSet(
                    $this->post['interval'],
                    $defaults['interval']
                ),
                'length'   => $this->Html->ifSet(
                    $this->post['length'],
                    $defaults['length']
                ),
                'expire'   => $this->Html->ifSet($this->post['expire']) == "on" ? "yes" : "no",
            ]);

            if (($errors = $this->SupportPinSettings->errors())) {
                $this->parent->setMessage('error', $errors);
            } else {
                $this->parent->setMessage(
                    'message',
                    Language::_('SupportPinPlugin.settings_updated', true)
                );
            }

            // Regenerate all existing pins if length has changed
            if ($settings->length != $updated_settings->length) {
              $this->ClientPin->regenerateAll($updated_settings->length);
            }

            $settings = $updated_settings;
        }

        $plugin_id = $this->get[0];

        // Set up expiry interval selections
        $lengths = $this->SupportPinSettings->getAllowedLengths();
        $available_intervals = $this->SupportPinSettings->getAvailableIntervals();

        // Set the view to render for all actions under this controller
        return $this->partial(
            'admin_manage_plugin',
             array_merge(
                 compact([
                    'plugin_id',
                    'available_intervals',
                    'lengths'
                ]),
                json_decode(json_encode($settings), true)
            )
        );
    }
}
