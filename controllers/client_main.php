<?php

class ClientMain extends SupportPinController
{
    public function preAction()
    {
        parent::preAction();
        $this->client_id = $this->Session->read('blesta_client_id');
    }

    public function index() {
        $pin = $this->ClientPin->get($this->client_id);

        // Set some variables to the view
        $this->set("settings", $this->settings);
        $this->set("pin", $pin);
        $this->set("client_no", $this->client->id_value);

        // Automatically renders the view in /plugins/my_plugin/views/default/client_main.pdt
        return $this->renderAjaxWidgetIfAsync(false);
    }
}
