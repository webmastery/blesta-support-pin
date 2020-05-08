<?php
/*
 *
 */

use Blesta\Core\Util\Common\Traits\Container;

/**
 * Class SupportPinPlugin
 */
class SupportPinPlugin extends Plugin
{
    const NAME        = 'support_pin';
    const TASK_EXPIRE = 'wm_cron_expire';

    // Load traits
    use Container;

    /**
     * @var Monolog\Logger An instance of the logger
     */
    protected $logger;

    public function __construct()
    {
        Language::loadLang(self::NAME . '_plugin', null, dirname(__FILE__) . DS . 'language' . DS);

        // Load components required by this plugin
        Loader::loadComponents($this, ['Input', 'Record']);

        $this->loadConfig(dirname(__FILE__) . DS . "config.json");

        $this->logger = $this->getFromContainer('logger');
    }

    public function install($plugin_id)
    {
        if (!isset($this->Record)) {
            Loader::loadComponents($this, ['Record']);
        }
        Loader::loadModels($this, ['SupportPin.ClientPin', 'SupportPin.SupportPinSettings']);

        // Default settings
        $settings = [
            'interval' => 60,
            'length'   => 6,
            'expire'   => 'yes'
        ];

        try {
            $this->Record
                ->setField('id', ['type'=>'int', 'size'=>10, 'unsigned'=>true, 'auto_increment'=>true])
                ->setField('client_id', ['type'=>'int', 'size'=>10, 'unsigned'=>true])
                ->setField('pin', ['type'=>'varchar', 'size'=>255, 'is_null'=>true, 'default'=>null])
                ->setField('date_updated', ['type'=>'datetime', 'is_null'=>true, 'default'=>null])
                ->setKey(['id'], 'primary')
                ->setKey(['client_id'], 'index')
                ->setKey(['client_id'], 'unique', 'wm_support_pin_client_id_uniq')
                ->setKey(['date_updated'], 'index')
                ->create(ClientPin::TABLE_PIN, true);

            $this->Record
                ->setField('company_id', ['type' => 'int', 'size' => 10, 'unsigned' => true])
                ->setField('key', ['type' => 'varchar', 'size' => 128])
                ->setField('value', ['type' => 'text'])
                ->setKey(['company_id', 'key'], 'primary')
                ->create(SupportPinSettings::TABLE_SETTINGS, true);

            $this->SupportPinSettings->update($settings);
        } catch (Exception $e) {
            $this->Input->setErrors(['db'=> ['create'=>$e->getMessage()]]);
            return;
        }

        $this->addCronTasks($this->getCronTasks());

        // Create a PIN for all existing clients
        $this->ClientPin->gapfill($settings['length']);
    }

    public function uninstall($plugin_id, $last_instance)
    {
        if ($last_instance) {
            // Try to remove DB table(s) & cron tasks
            Loader::loadModels($this, [
                'SupportPin.ClientPin',
                'SupportPin.SupportPinSettings'
            ]);

            try {
                $this->Record->drop(ClientPin::TABLE_PIN);
                $this->Record->drop(SupportPinSettings::TABLE_SETTINGS);
                $this->deleteCronTasks($last_instance);
            } catch (Exception $e) {
                $this->Input->setErrors(['db'=> ['create'=>$e->getMessage()]]);
                return;
            }
        }
    }

    public function upgrade($current_version, $plugin_id)
    {
        #
        # TODO: Place upgrade logic here if/when required
        #
    }

    public function getActions()
    {
        return [
            [
                'action' => "nav_primary_client",
                'uri' => "plugin/" . self::NAME . "/client_main/index/",
                'name' => "SupportPinPlugin.display_name",
                'options' => null,
                'enabled' => 1
            ],
            [
                'action' => 'widget_client_home',
                'name' => "SupportPinPlugin.action_client_widget",
                'uri' => "plugin/" . self::NAME . "/client_main/index/"
            ],
            [
                'action' => 'widget_staff_client',
                'name' => "SupportPinPlugin.action_staff_client",
                'uri' => "plugin/" . self::NAME . "/admin_main/client_widget/"
            ]
        ];
    }

    public function getEvents()
    {
        return [
            [
                'event' => "Clients.create",
                'callback' => array("this", "onClientCreate")
            ],
            [
                'event' => "Clients.delete",
                'callback' => array("this", "onClientDelete")
            ]
        ];
    }

    public function onClientCreate($event)
    {
        Loader::loadModels($this, ['SupportPin.ClientPin', 'SupportPin.SupportPinSettings']);
        $settings = $this->SupportPinSettings->getAll();
        $params = $event->getParams();
        $client = $params['client'];
        $id = $client->id;

        // Generate PIN record for client $id
        try {
            $this->ClientPin->generate($client->id, $settings->length);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    public function onClientDelete($event)
    {
        Loader::loadModels($this, ['SupportPin.ClientPin', 'SupportPin.SupportPinSettings']);
        $params = $event->getParams();
        $client = $params['client'];
        $id = $client->id;

        // Remove the PIN record for client $id
        try {
            $this->ClientPin->delete($client->id);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    private function getCronTasks()
    {
        return [
            [
                'key' => self::TASK_EXPIRE,
                'task_type' => "plugin",
                'dir' => self::NAME,
                'name' => "SupportPinPlugin.cron_expire.name",
                'name' => Language::_("SupportPinPlugin.cron_expire.name", true),
                'description' => Language::_("SupportPinPlugin.cron_expire.description", true),
                'type' => "interval",
                'type_value' => 5,
                'enabled' => 1
            ]
        ];
    }

    private function addCronTasks(array $tasks)
    {
        Loader::loadModels($this, array("CronTasks"));
        foreach ($tasks as $task) {
            $task_id = $this->CronTasks->add($task);

            if (!$task_id) {
                $cron_task = $this->CronTasks->getByKey($task['key'], $task['dir'], $task['task_type']);
                if ($cron_task) {
                    $task_id = $cron_task->id;
                }
            }

            if ($task_id) {
                $task_vars = ['enabled' => $task['enabled']];
                if ($task['type'] == 'interval') {
                    $task_vars['interval'] = $task['type_value'];
                } else {
                    $task_vars['time'] = $task['type_value'];
                }

                $this->CronTasks->addTaskRun($task_id, $task_vars);
            }
        }
    }

    private function deleteCronTasks($last_instance)
    {
        Loader::loadModels($this, array("CronTasks"));
        $cron_tasks = $this->getCronTasks();

        if ($last_instance) {
            // Remove the cron tasks
            foreach ($cron_tasks as $task) {
                $cron_task = $this->CronTasks->getByKey($task['key'], $task['dir'], $task['task_type']);
                if ($cron_task) {
                    $this->CronTasks->deleteTask($cron_task->id, $task['task_type'], $task['dir']);
                }
            }
        }

        // Remove individual cron task runs
        foreach ($cron_tasks as $task) {
            $cron_task_run = $this->CronTasks->getTaskRunByKey($task['key'], $task['dir'], false, $task['task_type']);
            if ($cron_task_run) {
                $this->CronTasks->deleteTaskRun($cron_task_run->task_run_id);
            }
        }
    }

    // This method is invoked once for each different cron task configured by the plugin and identified by $key
    public function cron($key)
    {
        Loader::loadModels($this, ['SupportPin.ClientPin', 'SupportPin.SupportPinSettings']);

        switch ($key) {
            case self::TASK_EXPIRE:
                $settings = $this->SupportPinSettings->getAll();
                if ($settings->expire) {
                    $this->ClientPin->updateExpired($settings->interval, $settings->length);
                }
            break;
        }
    }
}
