<?php

class SupportPinSettings extends SupportPinModel
{
    const TABLE_SETTINGS = "wm_support_pin_settings";

    private $defaults = [
        'length' => 4,
        'interval' => 60,
        'expire' => 'yes'
    ];

    private $min_length = 4;
    private $max_length = 12;

    /**
     * Returns all settings stored for the current company
     *
     * @return stdClass A stdClass object with member variables that as setting names and values as setting values
     */
    public function getAll()
    {
        $settings = [];

        $results = $this->Record
            ->select(['key', 'value'])
            ->from(self::TABLE_SETTINGS)
            ->where('company_id', '=', Configure::get('Blesta.company_id'))
            ->fetchAll();

        foreach ($results as $result) {
            switch ($result->key) {
                // Booleans
                case 'expire':
                    $settings[$result->key] = $result->value == "yes"
                        ? true
                        : false;
                    break;

                // Everything else
                default:
                  $settings[$result->key] = $result->value;
            }
        }

        // Merge any missing fields with defaults
        return (object)$this->defaults($settings);
    }

    /**
     * Updates a set of settings
     *
     * @param array $vars A key/value paired array of settings to update
     */
    public function update(array $vars)
    {
        $this->Input->setRules($this->getRules());

        if (!$this->Input->validates($vars)) {
            return;
        }

        foreach ($vars as $key => $value) {
            // Munge some data before storage
            switch ($key) {
                default:
                    if (is_bool($value)) {
                        $value = $value ? "yes" : "no";
                    }
                break;
            }

            $fields = [
                'key'   => $key,
                'value' => $value,
                'company_id' => Configure::get('Blesta.company_id')
            ];
            $res = $this->Record
                    ->duplicate('value', '=', $fields['value'])
                    ->insert(self::TABLE_SETTINGS, $fields);
        }

        return $this->getAll();
    }

    /**
     * Remove all settings for current company
     */
    public function deleteAll()
    {
        $this->Record
            ->from(self::TABLE_SETTINGS)
            ->where('company_id', '=', Configure::get('Blesta.company_id'))
            ->delete();
    }

    private function getRules()
    {
        return [
          'length' => [
              'valid' => [
                  'rule' => [
                      'in_array',
                      array_keys($this->getAllowedLengths())
                  ],
                  'message' => 'Invalid length'
              ]
          ],
          'interval' => [
              'valid' => [
                  'rule' => [
                      'in_array',
                      array_keys($this->getAvailableIntervals())
                  ],
                  'message' => 'Invalid interval'
              ]
          ]
      ];
    }

    public function getDefaultSettings()
    {
        return $this->defaults;
    }

    public function getAllowedLengths()
    {
        $out = [];
        for ($i = $this->min_length; $i <= $this->max_length; $i++) {
            $out[$i] = $i;
        }
        return $out;
    }

    public function getAvailableIntervals()
    {
        $intervals = [];
        $intervals['5'] = '5 Minutes';
        $intervals['10'] = '10 Minutes';
        $intervals['15'] = '15 Minutes';
        $intervals['30'] = '30 Minutes';

        for ($i = 1; $i <= 24; $i++) {
            $intervals[$i * 60] = $i . " Hours";
        }

        for ($i = 1; $i <= 30; $i++) {
            $intervals[$i * 1440] = $i . " Days";
        }

        return $intervals;
    }

    private function defaults($values)
    {
        return array_merge(
            $this->defaults,
            array_intersect_key($values, $this->defaults)
        );
    }
}
