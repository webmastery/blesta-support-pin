<?php

/**
 * Class ClientPin
 */
class ClientPin extends SupportPinModel
{
    const TABLE_PIN = 'wm_support_pin';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Generate support PIN for all existing clients without one
     * @param int $length
     * @return void
     */
    public function gapfill($length)
    {
        $max = str_repeat('9', $length);
        $company_id = Configure::get('Blesta.company_id');

        return $this->Record->query(
            'INSERT INTO ' . self::TABLE_PIN . ' (client_id, date_updated, pin)
            select c.id, NOW() - interval extract(second from now()) second, LPAD(FLOOR(RAND() * ?), ?, \'0\')
              from clients c, client_groups g
              where c.client_group_id = g.id
                and g.company_id = ?
                and c.id not in (select client_id from wm_support_pin)',
            $max, $length, $company_id
        );
    }

    public function updateExpired($mins, $length)
    {
        $max = str_repeat('9', $length);
        return $this->Record->query(
            'UPDATE ' . self::TABLE_PIN . '
            SET date_updated = now() - interval extract(second from now()) second,
            pin = LPAD(FLOOR(RAND() * ?), ?, \'0\')
            WHERE date_updated + interval ? minute <= now()',
            $max, $length, $mins
        );
    }

    public function regenerateAll($length)
    {
        try {
            $this->Record->begin();

            $this->deleteAll();
            $this->gapfill($length);

            $this->Record->commit();
        } catch (Exception $e) {
            $this->Record->rollback();
            throw $e;
        }
    }

    /**
     * (Re)generate support PIN for given client ID
     * @param int $client_id
     * @param int $length
     * @return void
     */
    public function generate($client_id, $length=6)
    {
        $new = $this->_generate($length);
        $now = date("Y-m-d H:i:00");

        return $this->Record
            ->duplicate('pin', '=', $new)
            ->duplicate('date_updated', '=', $now)
            ->insert(self::TABLE_PIN, [
                'client_id'    => $client_id,
                'pin'          => $new,
                'date_updated' => $now
            ]);
    }

    /**
     * Fetch a clients support PIN details
     * @param int $client_id
     * @return void
     */
    public function get($client_id, $expire_interval = null)
    {
        $expire_interval = $expire_interval ? intval($expire_interval) : null;

        $query = $this->Record
            ->select(['id', 'client_id', 'pin', 'date_updated'])
            ->from(self::TABLE_PIN)
            ->where('client_id', '=', $client_id);

        if ($expire_interval) {
            $query->select(['date_updated + INTERVAL ' . $expire_interval . ' MINUTE AS expires'], false);
        }

        return $query->fetch();
    }

    /**
     * Validate a provided client PIN is valid (perhaps useful for API usage)
     * @param int $client_id
     * @param string $pin
     * @return bool
     */
    public function isValid($client_id=null, $client_no=null, $pin)
    {
        // Get the internal ID of a client if their client number was given
        if (!$client_id && $client_no) {
            $company_id = Configure::get('Blesta.company_id');
            $_client = $this->Record
              ->select(['clients.id'])
              ->from('clients')
              ->from('client_groups')
              ->where('clients.id_value', '=', $client_no)
              ->where('client_groups.id', '=', 'clients.client_group_id', false)
              ->where('client_groups.company_id', '=', $company_id)
              ->fetch();

            if (!$_client) {
                return false;
            }
            $client_id = $_client->id;
        }

        // Check the provided PIN is valid for the given client
        $found = $this->get($client_id);
        return $found && $found->pin === $pin;
    }

    /**
     * Delete support PIN for given client ID
     * @param int $client_id
     *
     * @return void
     */
    public function delete($client_id)
    {
        return $this->Record
            ->from(self::TABLE_PIN)
            ->where('client_id', '=', $client_id)
            ->delete();
    }

    /**
     * Delete all PINs for all clients in current company
     * @return void
     */
    public function deleteAll()
    {
        $company_id = Configure::get('Blesta.company_id');
        return $this->Record
            ->from(self::TABLE_PIN)
            ->from('clients')
            ->from('client_groups')
            ->where(self::TABLE_PIN . '.client_id', '=', 'clients.id', false)
            ->where('clients.client_group_id', '=', 'client_groups.id', false)
            ->where('client_groups.company_id', '=', $company_id)
            ->delete([self::TABLE_PIN]);
    }

    private function _generate($length)
    {
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= mt_rand(0, 9);
        }
        return $out;
    }
}
