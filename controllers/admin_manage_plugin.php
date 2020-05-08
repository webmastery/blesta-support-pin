<?php

class AdminManagePlugin extends AppController
{
    /**
     * Performs necessary initialization
     */
    private function init() {
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
    public function index() {
        $this->uses(['SupportPin.SupportPinSettings']);
        $this->init();

        if (!empty($this->post)) {
            $update = [
                'interval'    => $this->Html->ifSet($this->post['interval']),
                'expire'      => $this->Html->ifSet($this->post['expire']) == "on" ? "yes" : "no",
                'length'      => $this->post['length'],
            ];
            $this->SupportPinSettings->update($update);

            if (($errors = $this->SupportPinSettings->errors())) {
                $this->parent->setMessage('error', $errors);
            } else {
                $this->parent->setMessage('message', Language::_('SupportPinPlugin.settings_updated', true));
            }
        }

        $settings = $this->SupportPinSettings->getAll();
        $plugin_id = $this->get[0];

        $lengths = [];
        for ($i = 4; $i <= 12; $i++) { $lengths[$i] = $i; }
        
        // Set up expiry interval selections
        $available_intervals = [];
        $available_intervals['5'] = '5 Minutes';
        $available_intervals['10'] = '10 Minutes';
        $available_intervals['15'] = '15 Minutes';
        $available_intervals['30'] = '30 Minutes';

        for ($i = 1; $i <= 24; $i++) {
          $available_intervals[$i * 60] = $i . " Hours";
        }

        for ($i = 1; $i <= 30; $i++) {
          $available_intervals[$i * 1440] = $i . " Days";
        }

        // Set the view to render for all actions under this controller
        return $this->partial(
            'admin_manage_plugin',
             array_merge(compact(['plugin_id', 'available_intervals', 'lengths']), json_decode(json_encode($settings), TRUE))
        );
    }
}
