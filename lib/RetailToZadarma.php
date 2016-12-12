<?php
/**
 * Class RetailToZadarma
 * @author Volkov Alexander
 */

namespace lib;

use RetailCrm\Response\ApiResponse;

require_once 'AbstractZadarmaIntegration.php';

/**
 * Class RetailToZadarma
 * @package lib
 */
class RetailToZadarma extends AbstractZadarmaIntegration
{
    protected $crm_name = 'retail';

    protected $zd_name = 'zadarma';
    protected $make_call_url = 'http://crm.e3d567e3.pub.sipdc.net/retail/make-call.php';
    protected $zd_image = 'http://www.clker.com/cliparts/O/n/v/t/d/3/ringing-red-telephone.svg';

    /** @var \RetailCrm\ApiClient|null $cCrm */
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
        $url = CommonFunctions::nullableFromArray($this->crm_config, 'url');
        $key = CommonFunctions::nullableFromArray($this->crm_config, 'key');

        /** @var \RetailCrm\ApiClient cCrm */
        $this->cCrm = new \RetailCrm\ApiClient($url, $key);
    }

    /**
     * @inheritdoc
     */
    protected function initMysqlClient()
    {
        parent::initMysqlClient();

        try {
            $this->Mysql->statement(sprintf("
            CREATE TABLE IF NOT EXISTS %s.retail
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

        if (!empty($this->cCrm)) {
            $test_response = $this->cCrm->usersList();
            if (!$test_response->isSuccessful()) {
                $this->cCrm = null;
                $result = false;
            }
        } else $result = false;

        return $result;
    }

    /**
     * Регистрация Zadarma как телефонии в RetailCRM
     * @return bool
     */
    public function registrateSipInCrm()
    {
        $result = false;

        $is_already_registered = false;

        if ($this->validateClients()) {
            try {
                $response = $this->cCrm->telephonySettingsGet('zadarma');
                if ($response->isSuccessful()) {
                    $is_already_registered = true;
                    $result = true;
                }
            } catch (\Exception $e) {
                $this->Log->error("Can't check crm registration: ", $e->getMessage());
            }

            if (!$is_already_registered) {
                try {
                    $response = $this->cCrm->telephonySettingsEdit(
                        $this->zd_name,
                        md5(print_r($this->crm_config, true)), true,
                        $this->zd_name,
                        $this->make_call_url,
                        $this->zd_image,
                        [], [], true, true, true, true, false
                    );
                    $this->validateCrmResponse($response);
                    $result = true;
                } catch (\RetailCrm\Exception\CurlException $e) {
                    $this->Log->error("Registration crm action error: ", $e->getMessage());
                }
            }
        }

        return $result;
    }

    /**
     * Отправка события звонка из Zadarma в RetailCRM
     * @param $params
     * @return null|\RetailCrm\Response\ApiResponse
     */
    public function sendCallEventToCrm($params)
    {
        $result = null;

        $phone = null;
        $codes = null;
        $type = null;

        $this->Log->notice('new call event', $params);

        $this->writeInfoAboutCallToDb($params);
        $this->getSipRedirections();

        try {
            $event = CommonFunctions::nullableFromArray($params, 'event');

            switch ($event) {
                case self::ZD_CALLBACK_EVENT_START:
                    $phone = CommonFunctions::nullableFromArray($params, 'caller_id');
                    $code = CommonFunctions::nullableFromArray(CommonFunctions::nullableFromArray($this->cCrm->telephonyCallManager($phone, 0), 'manager'), 'code');
                    $type = 'in';

                    $codes = [];
                    if (empty($code)) {
                        $internal_codes = $this->getInternalCodes(true);

                        if (!empty($internal_codes)) {
                            foreach ($internal_codes as $internal_code) {
                                if (!empty(CommonFunctions::nullableFromArray($internal_code, 'manager'))) {
                                    $codes[] = $internal_code['code'];
                                }
                            }
                        }
                    } else {
                        $codes = [$code];
                        echo json_encode(array(
                            'redirect' => $code,
                        ));
                    }
                    break;
                case self::ZD_CALLBACK_EVENT_OUT_START:
                    $phone = CommonFunctions::nullableFromArray($params, 'destination');
                    $codes = [CommonFunctions::nullableFromArray($params, 'internal')];
                    $type = 'out';
                    break;
                case self::ZD_CALLBACK_EVENT_END:
                    $phone = CommonFunctions::nullableFromArray($params, 'caller_id');
                    $codes = [CommonFunctions::nullableFromArray($params, 'internal')];
                    $type = 'hangup';
                    break;
                case self::ZD_CALLBACK_EVENT_OUT_END:
                    $phone = CommonFunctions::nullableFromArray($params, 'destination');
                    $codes = [CommonFunctions::nullableFromArray($params, 'internal')];
                    $type = 'hangup';
                    break;
                default:
                    break;
            }

            if ($event !== self::ZD_CALLBACK_EVENT_INTERNAL) {
                $result = $this->cCrm->telephonyCallEvent(
                    $phone, $type, $codes, null
                );

                $this->validateCrmResponse($result);
            }

            $this->uploadCallsToCrm();
        } catch (\Exception $e) {
            $this->Log->error('Can\'t send information about incoming call to crm - ' . $e->getMessage(), [
                'phone' => $phone,
                'type' => $type,
                'codes' => $codes
            ]);
        }

        return $result;
    }

    /**
     * Валидация параметров, пришедших из RetailCRM для организации callback на стороне Zadarma
     * @param $params
     * @return array|null
     */
    public function validateCallbackParams($params)
    {
        $clientId = CommonFunctions::nullableFromArray($params, 'clientId');
        $code = CommonFunctions::nullableFromArray($params, 'code');
        $phone = CommonFunctions::nullableFromArray($params, 'phone');

        if (empty($clientId) || empty($code) || empty($phone)) {
            $this->Log->error('Can\'t make phone callback, to few params');
            return null;
        }

        try {
            $zd_response = $this->cZadarma->call('/v1/pbx/internal/', [], 'GET');

            $this->validateZdResponse($zd_response);

            $zd_response = json_decode($zd_response, true);
            $internal_codes = CommonFunctions::nullableFromArray($zd_response, 'numbers');
        } catch (\Exception $e) {
            $this->Log->error('Can\'t get codes from zadarma');
            return null;
        }

        if (!in_array($code, $internal_codes)) {
            $this->Log->error('Can\'t get codes from zadarma');
            return null;
        }

        if (md5(print_r($this->crm_config, true)) !== $clientId) {
            $this->Log->error('Broken clientId');
            return null;
        }

        $phone = sprintf('+%s', str_replace([' ', '+'], '', $phone));

        return [$code, $phone];
    }

    /**
     * @inheritdoc
     * @param ApiResponse $response
     * @return bool
     * @throws \Exception
     */
    public function validateCrmResponse($response)
    {
        if (!$response->isSuccessful()) {
            throw new \Exception('Failed request to CRM');
        }

        return true;
    }

    /**
     * Получение данных о связке внутренних кодов и менеджеров
     * @param bool $with_manager
     * @return array
     */
    private function getInternalCodes($with_manager = false)
    {
        $result = [];

        $internal_codes = CommonFunctions::nullableFromArray(
            CommonFunctions::nullableFromArray(
                $this->cCrm->telephonySettingsGet('zadarma'),
                'configuration'
            ),
            'additionalCodes'
        );

        if (!empty($internal_codes)) {
            foreach ($internal_codes as $internal_code) {
                $manager = [];
                $user_id = CommonFunctions::nullableFromArray($internal_code, 'userId');

                if ($with_manager && !empty($user_id)) {
                    $user = CommonFunctions::nullableFromArray($this->cCrm->usersGet($user_id), 'user');

                    $isManager = CommonFunctions::nullableFromArray($user, 'isManager');
                    $status = CommonFunctions::nullableFromArray($user, 'status');
                    $online = CommonFunctions::nullableFromArray($user, 'online');

                    if (!empty($user) && $isManager === true && $status === 'free' && $online === true) {
                        $manager = $user;
                    }
                }

                $result[] = [
                    'code' => CommonFunctions::nullableFromArray($internal_code, 'code'),
                    'manager' => $manager
                ];
            }
        }

        return $result;
    }

    /**
     * Конвертация статуса завершения звонка
     * @param $disposition
     * @return string
     */
    private function convertZdDisposition($disposition)
    {
        $crm_statuses = ['answered', 'busy', 'not allowed', 'no answer', 'failed'];

        $result = 'unknown';

        if (in_array($disposition, $crm_statuses)) {
            $result = $disposition;
        } else {
            switch ($disposition) {
                case 'cancel':
                    $result = 'busy';
                    break;
                case 'no money':
                case 'unallocated number':
                case 'no limit':
                case 'no day limit':
                case 'line limit':
                case 'no money, no limit':
                    $result = 'failed';
                    break;
                default:
                    break;
            }
        }

        return $result;
    }

    /**
     * Запись данных о входящем звонке в базу данных
     * @param $params
     * @return $this|array|null|string
     */
    private function writeInfoAboutCallToDb($params)
    {
        $result = null;

        $pbx_id = CommonFunctions::nullableFromArray($params, 'pbx_call_id');
        $event = CommonFunctions::nullableFromArray($params, 'event');

        if (!empty($pbx_id)) {
            switch ($event) {
                case self::ZD_CALLBACK_EVENT_START:
                case self::ZD_CALLBACK_EVENT_OUT_START:
                    $result = $this->Mysql->table('retail')->insert([
                        'client_id' => CommonFunctions::nullableFromArray($this->crm_config, 'username'),
                        'call_id' => $pbx_id,
                        'status' => self::CALL_STATUS_STARTED,
                        'start_data' => json_encode($params),
                        'created_at' => strtotime(CommonFunctions::nullableFromArray($params, 'call_start'))
                    ]);
                    break;
                case self::ZD_CALLBACK_EVENT_END:
                case self::ZD_CALLBACK_EVENT_OUT_END:
                    $result = $this->Mysql->table('retail')->where('call_id', $pbx_id)->update([
                        'status' => self::CALL_STATUS_FINISHED,
                        'end_data' => json_encode($params),
                        'updated_at' => strtotime(CommonFunctions::nullableFromArray($params, 'call_start'))
                    ]);
                    break;
                default:
                    break;
            }
        }

        return $result;
    }

    /**
     * Получение основной информации о закончившемся звонке
     * @param $Call
     * @return array
     */
    private function getTotalInfoAboutCall($Call)
    {
        $start_data = json_decode($Call->start_data, true);
        $end_data = json_decode($Call->end_data, true);

        $type = CommonFunctions::nullableFromArray($start_data, 'event') === self::ZD_CALLBACK_EVENT_START ? 'in' : (
        CommonFunctions::nullableFromArray($start_data, 'event') === self::ZD_CALLBACK_EVENT_OUT_START ? 'out' : null
        );

        $phone = CommonFunctions::nullableFromArray($start_data, 'event') === self::ZD_CALLBACK_EVENT_START ? CommonFunctions::nullableFromArray($end_data, 'caller_id') : (
        CommonFunctions::nullableFromArray($start_data, 'event') === self::ZD_CALLBACK_EVENT_OUT_START ? CommonFunctions::nullableFromArray($end_data, 'destination') : null
        );

        return [
            'date' => date('Y-m-d H:i:s', strtotime(CommonFunctions::nullableFromArray($end_data, 'call_start'))),
            'type' => $type,
            'phone' => $phone,
            'code' => CommonFunctions::nullableFromArray($end_data, 'internal'),
            'result' => $this->convertZdDisposition(CommonFunctions::nullableFromArray($end_data, 'disposition')),
            'duration' => CommonFunctions::nullableFromArray($end_data, 'duration'),
            'externalId' => $Call->id,
        ];
    }

    /**
     * Отправка всех данных по законченным звонкам в CRM
     */
    private function uploadCallsToCrm()
    {
        $calls_to_upload = $this->Mysql->table('retail')
            ->where('status', self::CALL_STATUS_FINISHED)
            ->select('*')
            ->get();

        if (!empty($calls_to_upload)) {
            foreach ($calls_to_upload as $Call) {
                $pbx_call_id = $Call->call_id;
                $call_record_link = $this->getCallRecord(null, $pbx_call_id);

                $data = array_merge($this->getTotalInfoAboutCall($Call), [
                    'recordUrl' => $call_record_link
                ]);

                $result = $this->cCrm->telephonyCallsUpload([$data]);

                if ($result->isSuccessful()) {
                    $this->Mysql->table('retail')->where('call_id', $pbx_call_id)->update([
                        'status' => self::CALL_STATUS_SENT
                    ]);
                } else {
                    $this->Mysql->table('retail')->where('call_id', $pbx_call_id)->update([
                        'status' => self::CALL_STATUS_CANT_SEND
                    ]);
                }
            }
        }
    }

    const CALL_STATUS_STARTED = 0;
    const CALL_STATUS_FINISHED = 1;
    const CALL_STATUS_SENT = 2;
    const CALL_STATUS_CANT_SEND = 3;
}