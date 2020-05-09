<?php

class SupportPinSettings extends SupportPinModel
{
    const TABLE_SETTINGS = "wm_support_pin_settings";
    
    /**
     * Returns all settings stored for the current company
     *
     * @return stdClass A stdClass object with member variables that as setting names and values as setting values
     */
    public function getAll()
    {
        $settings = new stdClass();
                
        $results = $this->Record
                ->select(['key', 'value'])
                ->from(self::TABLE_SETTINGS)
                ->where('company_id', '=', Configure::get('Blesta.company_id'))
                ->fetchAll();

        foreach ($results as $result) {
            // Munge some data before returning
            switch ($result->key) {
                // Booleans
                case 'expire':
                    $settings->{$result->key} = $result->value == "yes" ? true : false;
                    break;
                
                // Everything else
                default:
                  $settings->{$result->key} = $result->value;
            }
        }
        return $settings;
    }

    /**
     * Updates a set of settings
     *
     * @param array $vars A key/value paired array of settings to update
     */
    public function update(array $vars)
    {
        $rules = [ ];

        $this->Input->setRules($rules);

        if ($this->Input->validates($vars)) {
            foreach ($vars as $key => $value) {
                // Munge some data before storage
                switch ($key) {
                    default:
                        if (is_bool($value)) {
                            $value = $value ? "yes" : "no";
                        }

                        if (is_array($value)) {
                            $value = implode(",", $value);
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
        }
    }

    /**
     * Remove all settings for current company
     */
    public function deleteAll()
    {
        return $this->Record
            ->from(self::TABLE_SETTINGS)
            ->where('company_id', '=', Configure::get('Blesta.company_id'))
            ->delete();
    }
}
