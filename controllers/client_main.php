<?php

class ClientMain extends SupportPinController
{
    private $client_id = null;

    public function preAction()
    {
        parent::preAction();
        $this->client_id = $this->Session->read('blesta_client_id');
    }

    public function index()
    {
        Loader::loadModels($this, ['Clients']);
        $client = $this->Clients->get($this->client_id);

        $pin = $this->ClientPin->get($this->client_id, $this->settings->interval);

        // Set some variables to the view
        $this->set("settings", $this->settings);
        $this->set("pin", $pin);
        $this->set("client_no", $client->id_value);

        // Automatically renders the view in /plugins/my_plugin/views/default/client_main.pdt
        return $this->renderAjaxWidgetIfAsync(false);
    }
}
