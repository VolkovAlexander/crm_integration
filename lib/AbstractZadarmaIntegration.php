<?php
/**
 * AbstractZadarmaIntegration
 * @author Volkov Alexander
 */

namespace lib;

require_once 'Log.php';
require_once 'CommonFunctions.php';

/**
 * Class AbstractZadarmaIntegration
 * @package lib
 */
class AbstractZadarmaIntegration
{
    protected $crm_name = null;

    protected $zadarma_config = [];
    protected $crm_config = [];
    protected $mysql_config = [];

    public $cCrm = null;
    /** @var \Zadarma_API\Client|null */
    public $cZadarma = null;

    /** @var  Log $Log */
    public $Log;

    /** @var \Pixie\QueryBuilder\QueryBuilderHandler $cMysql */
    public $Mysql = null;

    /**
     * @inheritdoc
     */
    public function __construct()
    {
        define('ROOT_DIR', sprintf('%s/./../', __DIR__));

        $this->zadarma_config = include ROOT_DIR . '/config/zadarma.php';
        $this->mysql_config = include ROOT_DIR . '/config/mysql.php';
        $crm_config_file = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, sprintf('%s/config/%s.php', ROOT_DIR, $this->crm_name));

        if (file_exists($crm_config_file)) {
            $this->crm_config = include $crm_config_file;
        } else throw new \Exception(sprintf('Configuration file for "%s" not found', $this->crm_name));

        $this->Log = new Log($this->crm_name);
        $this->initMysqlClient();

        if (is_array($this->zadarma_config) && is_array($this->crm_config)) {
            try {
                $this->initZadarmaClient();
                $this->initCrmClient();

            } catch (\Exception $e) {
                $this->Log->error('Can\'t initialize api-clients');
            }
        }
    }

    /**
     * Передача в Zadarma запроса на коллбек клиенту
     * @param string $from
     * @param string $to
     * @param string $sip
     * @param bool $predicted
     *
     * @return mixed
     */
    public function makeCallbackToPhone($from, $to, $sip = null, $predicted = false)
    {
        $result = null;

        try {
            $result = $this->cZadarma->call('/v1/request/callback/', [
                'from' => $from,
                'to' => $to,
                'sip' => $sip,
                'predicted' => $predicted
            ]);

            $this->validateZdResponse($result);
        } catch (\Exception $e) {
            $this->Log->error(sprintf('Failed output calling from %s to %s', $from, $to), $e->getMessage());
        }

        return $result;
    }

    /**
     * Получение ссылки на запись разговора
     * @param string $call_id
     * @param string $pbx_call_id
     * @param int $lifetime
     * @return null
     */
    public function getCallRecord($call_id, $pbx_call_id, $lifetime = 5184000)
    {
        $result = null;

        $try_finished = false;
        $try_counters = 0;
        $try_max_count = 4;

        while (!$try_finished) {
            try {
                $response = $this->cZadarma->call('/v1/pbx/record/request/', [
                    'call_id' => $call_id,
                    'pbx_call_id' => $pbx_call_id,
                    'lifetime' => $lifetime
                ], 'get');

                $this->validateZdResponse($response);

                $response = json_decode($response, true);
                if (!empty($response) && $response['status'] === 'success') {
                    $result = (isset($response['links']) && count($response['links']) === 1) ? $response['links'][0] : null;
                    $try_finished = true;
                } else {
                    sleep(5);

                    $try_counters++;
                    $try_finished = ($try_counters === $try_max_count);
                }
            } catch (\Exception $e) {
                $this->Log->error('Can\'t get call record', $e->getMessage());
                sleep(5);

                $try_counters++;
                $try_finished = ($try_counters === $try_max_count);
            }
        }


        return $result;
    }

    /**
     * Валидация ответа из Zadarma
     * @param string $response
     * @return bool
     * @throws \Exception
     */
    public function validateZdResponse($response)
    {
        $data = json_decode($response, true);

        if (CommonFunctions::nullableFromArray($data, 'status') !== 'success') {
            throw new \Exception($data['message']);
        }

        return true;
    }

    /**
     * Валидация ответа из CRM
     * @param $response
     */
    public function validateCrmResponse($response)
    {
    }

    /**
     * Валидация клиентов в обьекте
     */
    public function validateClients()
    {
    }

    /**
     * Инициализация клиента Zadarma
     */
    protected function initZadarmaClient()
    {
        $key = CommonFunctions::nullableFromArray($this->zadarma_config, 'key');
        $secret = CommonFunctions::nullableFromArray($this->zadarma_config, 'secret');

        $this->cZadarma = new \Zadarma_API\Client($key, $secret);
    }

    /**
     * Инициализация клиента CRM
     */
    protected function initCrmClient()
    {
    }

    /**
     * Инициализация клиента на доступ к Mysql
     */
    protected function initMysqlClient()
    {
        $config = array(
            'driver' => 'mysql',
            'database' => CommonFunctions::nullableFromArray($this->mysql_config, 'user'),
            'host' => CommonFunctions::nullableFromArray($this->mysql_config, 'host'),
            'username' => CommonFunctions::nullableFromArray($this->mysql_config, 'user'),
            'password' => CommonFunctions::nullableFromArray($this->mysql_config, 'password'),
        );

        $connection = new \Pixie\Connection('mysql', $config);
        $this->Mysql = new \Pixie\QueryBuilder\QueryBuilderHandler($connection);

        if (!empty($this->Mysql)) {
            try {
                $this->Mysql->statement(sprintf('CREATE DATABASE IF NOT EXISTS %s',
                    CommonFunctions::nullableFromArray($this->mysql_config, 'user')
                ));

                $config['database'] = CommonFunctions::nullableFromArray($this->mysql_config, 'user');
                $connection = new \Pixie\Connection('mysql', $config);
                $this->Mysql = new \Pixie\QueryBuilder\QueryBuilderHandler($connection);
            } catch (\PDOException $e) {
                $this->Log->error('Can\'t connect to mysql db', $e->getMessage());
            }
        }
    }

    const ZD_CALLBACK_EVENT_START = 'NOTIFY_START';
    const ZD_CALLBACK_EVENT_INTERNAL = 'NOTIFY_INTERNAL';
    const ZD_CALLBACK_EVENT_END = 'NOTIFY_END';
    const ZD_CALLBACK_EVENT_OUT_START = 'NOTIFY_OUT_START';
    const ZD_CALLBACK_EVENT_OUT_END = 'NOTIFY_OUT_END';
}