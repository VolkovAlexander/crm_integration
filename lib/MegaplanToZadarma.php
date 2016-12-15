<?php
/**
 * Class MegaplanToZadarma
 * @author Volkov Alexander
 */

namespace lib;

use Megaplan\SimpleClient\Client;

require_once 'AbstractZadarmaIntegration.php';

/**
 * Class RetailToZadarma
 * @package lib
 */
class MegaplanToZadarma extends AbstractZadarmaIntegration
{
    protected $crm_name = 'megaplan';

    /** @var Client|null $cCrm */
    public $cCrm = null;
    /** @var \Zadarma_API\Client|null $cZadarma */
    public $cZadarma = null;
    /** @var \Pixie\QueryBuilder\QueryBuilderHandler $cMysql */
    public $Mysql = null;

    /**
     * @inheritdoc
     */
    protected function initCrmClient()
    {
        $host = CommonFunctions::nullableFromArray($this->crm_config, 'host');
        $login = CommonFunctions::nullableFromArray($this->crm_config, 'login');
        $password = CommonFunctions::nullableFromArray($this->crm_config, 'password');

        /** @var Client cCrm */
        $this->cCrm = new Client($host);

        try {
            $this->cCrm = $this->cCrm->auth($login, $password);
        } catch (\InvalidArgumentException $e) {
            $this->cCrm = null;
            $this->Log->error('Can\'t connect to crm', $e);
        }
    }

    /**
     * @inheritdoc
     */
    protected function initMysqlClient()
    {
        parent::initMysqlClient();

        try {
            $this->Mysql->statement(sprintf("
            CREATE TABLE IF NOT EXISTS %s.megaplan
            (id INT NOT NULL AUTO_INCREMENT,
            client_id VARCHAR(128) NOT NULL,
            call_id VARCHAR(128) NOT NULL,
            start_data TEXT,
            end_data TEXT,
            status INT NOT NULL DEFAULT 0,
            created_at INT NOT NULL DEFAULT 0,
            updated_at INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8
        ", CommonFunctions::nullableFromArray($this->mysql_config, 'user')));
        } catch (\PDOException $e) {
            $this->Log->error('Can\'t create table', $e->getMessage());
        }

    }

    /**
     * @inheritdoc
     * @todo Доделать проверку CRM
     */
    public function validateClients()
    {
        $result = true;

        if (!empty($this->cZadarma)) {
            $test_response = json_decode($this->cZadarma->call('/v1/info/balance/', [], 'GET'), true);
            if (CommonFunctions::nullableFromArray($test_response, 'status') === 'error') {
                $this->cZadarma = null;
                $result = false;
            }
        } else $result = false;

        return $result;
    }

    const CALL_STATUS_STARTED = 0;
    const CALL_STATUS_FINISHED = 1;
    const CALL_STATUS_SENT = 2;
    const CALL_STATUS_CANT_SEND = 3;
}