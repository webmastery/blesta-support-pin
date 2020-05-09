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
    public function gapfill($length=6)
    {
        $max = str_repeat('9', $length);
        $company_id = Configure::get('Blesta.company_id');

        return $this->Record->query(
            'INSERT INTO ' . self::TABLE_PIN . ' (client_id, date_updated, pin)
            select c.id, NOW(), LPAD(FLOOR(RAND() * ?), ?, \'0\')
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
            'UPDATE ' . self::TABLE_PIN . ' SET date_updated = now() - interval extract(second from now()) second, pin = LPAD(FLOOR(RAND() * ?), ?, \'0\')
            WHERE date_updated + interval ? minute <= now()',
            $max, $length, $mins
        );
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
     * Fetch a clients support PIN
     * @param int $client_id
     * @return void
     */
    public function get($client_id)
    {
        $expire_interval = 5;
        if ($expire_interval) {
            return $this->Record->query(
                'SELECT p.*, `date_updated` + INTERVAL ? MINUTE AS expires from `' . self::TABLE_PIN . '` p where `client_id` = ?',
                $expire_interval, $client_id
            )->fetch();
        }

        return $this->Record->select([
          'id', 'client_id', 'pin', 'date_updated',
          # Is it possible to do this math with this ORM thing?
          //'date_updated + INTERVAL 5 MINUTE' => 'date_expires'
          ])
            ->from(self::TABLE_PIN)
            ->where('client_id', '=', $client_id)
            ->fetch();
    }

    /**
     * Validate a provided client PIN is valid (perhaps useful for API usage)
     * @param int $client_id
     * @param string $pin
     * @return bool
     */
    public function isValid($client_id=null, $client_no=null, $pin)
    {
        if (!$client_id && $client_no) {
            $company_id = Configure::get('Blesta.company_id');
            $_client = $this->Record->query('
              select c.id from clients c, client_groups g
              where c.id_value = ?
              and g.id = c.client_group_id
              and g.company_id = ?
            ', $client_no, $company_id)->fetch();
            if (!$_client) {
                return false;
            }
            $client_id = $_client->id;
        }

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
