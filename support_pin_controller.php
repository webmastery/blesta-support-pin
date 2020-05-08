<?php

class SupportPinController extends AppController
{
    /**
     * Setup.
     */
    public function preAction()
    {
        Loader::loadComponents($this, ['Session']);
        $this->requireLogin();

        $this->structure->setDefaultView(APPDIR);
        parent::preAction();

        // Load config
        Configure::load('support_pin', dirname(__FILE__).DS.'config'.DS);

        // Auto load language for the controller
        Language::loadLang([Loader::fromCamelCase(get_class($this))], null, dirname(__FILE__).DS.'language'.DS);

        $this->uses(['SupportPin.ClientPin', 'SupportPin.SupportPinSettings']);
        $this->settings = $this->SupportPinSettings->getAll();

        // Override default view directory
        $this->view->view = 'default';
    }
}
