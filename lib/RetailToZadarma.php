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

    /** @var \RetailCrm\ApiClient|null $cCrm */
    public $cCrm = null;
    /** @var \Zadarma_API\Client|null $cZadarma */
    public $cZadarma = null;

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
    public function validateClients()
    {
        $result = true;

        $test_response = json_decode($this->cZadarma->call('/v1/info/balance/', [], 'GET'), true);
        if (CommonFunctions::nullableFromArray($test_response, 'status') === 'error') {
            $this->cZadarma = null;
            $result = false;
        }

        $test_response = $this->cCrm->usersList();
        if (!$test_response->isSuccessful()) {
            $this->cCrm = null;
            $result = false;
        }

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

        try {
            $response = $this->cCrm->telephonySettingsGet('zadarma');
            if ($response->isSuccessful()) {
                $is_already_registered = true;
                $result = true;
            }
        } catch (\Exception $e) {
            $this->Log->error("Can't check crm registration: " . $e->getMessage());
        }

        if (!$is_already_registered) {
            try {
                $response = $this->cCrm->telephonySettingsEdit(
                    'zadarma', md5(print_r($this->crm_config, true)), true, 'Zadarma', 'http://retail.e3d567e3.pub.sipdc.net/crm_integration/make-call.php',
                    'http://www.clker.com/cliparts/O/n/v/t/d/3/ringing-red-telephone.svg',
                    [], [], true, true, true, true, false
                );
                $this->validateCrmResponse($response);
                $result = true;
            } catch (\RetailCrm\Exception\CurlException $e) {
                $this->Log->error("Registration crm action error: " . $e->getMessage());
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
        $code = null;
        $type = null;

        try {
            $internal_codes = CommonFunctions::nullableFromArray(
                json_decode(
                    $this->cZadarma->call('/v1/pbx/internal/', [], 'GET'),
                    true
                ), 'numbers'
            );

            switch ($params['event']) {
                case self::ZD_CALLBACK_EVENT_START:
                    $phone = CommonFunctions::nullableFromArray($params, 'caller_id');
                    $code = CommonFunctions::nullableFromArray(CommonFunctions::nullableFromArray($this->cCrm->telephonyCallManager($phone, 0), 'manager'), 'code');
                    $type = 'in';

                    // Блок для проверки доступных менеджеров в случае, если недоступен привязанный к клиенту
                    $codes = [];
                    if (empty($code)) {
                        $internal_codes = $this->getInternalCodes(true);

                        if (!empty($internal_codes)) {
                            foreach ($internal_codes as $internal_code) {
                                if(!empty(CommonFunctions::nullableFromArray($internal_code, 'manager'))) {
                                    $codes[] = $internal_code['code'];
                                }
                            }
                        }
                    } else {
                        $codes = [$code];
                    }

                    $result = $this->cCrm->telephonyCallEvent(
                        $phone, $type, $codes, null
                    );
                    break;
                case self::ZD_CALLBACK_EVENT_END:
                    $phone = CommonFunctions::nullableFromArray($params, 'caller_id');
                    $code = CommonFunctions::nullableFromArray($params, 'internal');
                    $type = 'hangup';

                    if (in_array($code, $internal_codes)) {
                        /** @var \RetailCrm\Response\ApiResponse $result */
                        $result = $this->cCrm->telephonyCallEvent(
                            $phone, $type, [$code], null
                        );

                        if ($result->isSuccessful()) {
                            $this->Log->notice('HERE!');
                            $pbx_call_id = CommonFunctions::nullableFromArray($params, 'pbx_call_id');
                            $call_id = CommonFunctions::nullableFromArray($params, 'call_id_with_rec');
                            $call_record_link = $this->getCallRecord($call_id, $pbx_call_id);

                            $result = $this->cCrm->telephonyCallsUpload([
                                [
                                    'date' => date('Y-m-d H:i:s', CommonFunctions::nullableFromArray($params, 'call_start')),
                                    'type' => 'in',
                                    'phone' => $phone,
                                    'code' => $code,
                                    'result' => $this->zdStatusToCrmStatus(CommonFunctions::nullableFromArray($params, 'reason')),
                                    'duration' => CommonFunctions::nullableFromArray($params, 'duration'),
                                    'externalId' => $pbx_call_id,
                                    'recordUrl' => $call_record_link
                                ]
                            ]);
                        }
                    }
                    break;
                case self::ZD_CALLBACK_EVENT_OUT_START:
                    $phone = CommonFunctions::nullableFromArray($params, 'destination');
                    $code = CommonFunctions::nullableFromArray($params, 'internal');
                    $type = 'out';

                    if (in_array($code, $internal_codes)) {
                        $result = $this->cCrm->telephonyCallEvent(
                            $phone, $type, [$code], null
                        );
                    }
                    break;
                case self::ZD_CALLBACK_EVENT_OUT_END:
                    $phone = CommonFunctions::nullableFromArray($params, 'destination');
                    $code = CommonFunctions::nullableFromArray($params, 'internal');
                    $type = 'hangup';

                    if (in_array($code, $internal_codes)) {
                        /** @var \RetailCrm\Response\ApiResponse $result */
                        $result = $this->cCrm->telephonyCallEvent(
                            $phone, $type, [$code], null
                        );

                        if ($result->isSuccessful()) {
                            $pbx_call_id = CommonFunctions::nullableFromArray($params, 'pbx_call_id');
                            $call_id = CommonFunctions::nullableFromArray($params, 'call_id_with_rec');
                            $call_record_link = $this->getCallRecord($call_id, $pbx_call_id);

                            $result = $this->cCrm->telephonyCallsUpload([
                                [
                                    'date' => date('Y-m-d H:i:s', CommonFunctions::nullableFromArray($params, 'call_start')),
                                    'type' => 'out',
                                    'phone' => $phone,
                                    'code' => $code,
                                    'result' => $this->zdStatusToCrmStatus(CommonFunctions::nullableFromArray($params, 'reason')),
                                    'duration' => CommonFunctions::nullableFromArray($params, 'duration'),
                                    'externalId' => $pbx_call_id,
                                    'recordUrl' => $call_record_link
                                ]
                            ]);
                        }
                    }
                    break;
                default:
                    break;
            }

            $this->validateCrmResponse($result);
            $this->Log->notice(sprintf('New call event recorded<pre>%s</pre>', print_r([
                'type' => $type,
                'phone' => $phone,
                'code' => $code,
            ], true)));
        } catch (\Exception $e) {
            $this->Log->error(sprintf('Can\'t send information about incoming call to crm (%s)', $e->getMessage()));
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
            $this->Log->error(sprintf('Can\'t make phone callback, to few params'));
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
     * @inheritdoc
     */
    protected function zdStatusToCrmStatus($input_status)
    {
        $statuses = [
            'CANCEL' => 'failed',
            'ANSWER' => 'answered',
            'BUSY' => 'busy',
            'NOANSWER' => 'no answer',
        ];

        $result = CommonFunctions::nullableFromArray($statuses, $input_status);
        return empty($result) ? 'unknown' : $result;
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

                if($with_manager && !empty($user_id)) {
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
}