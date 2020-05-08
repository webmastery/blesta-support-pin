<?php

class AdminMain extends SupportPinController
{
    public function preAction()
    {
        parent::preAction();
    }

    public function client_widget()
    {
        // Ensure a valid client was given
        $this->uses(['Clients']);
        $client_id = (isset($this->get['client_id'])
            ? $this->get['client_id']
            : (isset($this->get[0]) ? $this->get[0] : null)
        );
        if (empty($client_id) || !($client = $this->Clients->get($client_id))) {
            header($this->server_protocol . ' 401 Unauthorized');
            exit();
        }

        // Load client's pin
        $pin = $this->ClientPin->get($client_id);

        // Set some variables to the view
        $this->set("settings", $this->settings);
        $this->set("pin", $pin);

        // Automatically renders the view in /plugins/my_plugin/views/default/client_main.pdt
        return $this->renderAjaxWidgetIfAsync(false);
    }
}
