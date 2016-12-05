<?php
/**
 * Class RetailToZadarma
 * @author Volkov Alexander
 */

namespace lib;

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
        /** @var \RetailCrm\ApiClient cCrm */
        $this->cCrm = new \RetailCrm\ApiClient(
            $this->crm_config['url'],
            $this->crm_config['key']
        );

        parent::initCrmClient();
    }

    public function registrateSipInCrm()
    {
        $result = null;

        try {
            $result = $this->cCrm->telephonySettingsEdit(
                'zadarma', 'volandkb', true, 'Zadarma', 'http://retail.e3d567e3.pub.sipdc.net/crm_integration/make-call.php',
                'http://www.clker.com/cliparts/O/n/v/t/d/3/ringing-red-telephone.svg',
                [
                    ['userId' => '8', 'code' => 100],
                    ['userId' => '9', 'code' => 101]
                ], [
                ['siteCode' => 'crm-integration-test', 'externalPhone' => '+7-351-277-91-49']
            ], false, true, true, true, false
            );

            $this->validateCrmResponse($result);
            $this->Log->notice(sprintf('Success new crm integration'));
        } catch (\RetailCrm\Exception\CurlException $e) {
            $this->Log->error(sprintf('Failed new crm integration (%s)', $e->getMessage()));
        }

        return $result;
    }

    public function sendCallEventToCrm($params)
    {
        $result = null;

        $phone = null;
        $code = null;
        $type = null;

        try {
            $response_data = $this->cZadarma->call('/v1/pbx/internal/', [], 'GET');

            $this->validateZdResponse($response_data);

            $response_data = json_decode($response_data, true);
            $internal_codes = isset($response_data['numbers']) ? array_values($response_data['numbers']) : [];

            switch ($params['event']) {
                case self::ZD_CALLBACK_EVENT_START:
                    $phone = isset($params['caller_id']) ? $params['caller_id'] : null;
                    $code = isset($params['internal']) ? $params['internal'] : 100;
                    $type = 'in';

                    $result = $this->cCrm->telephonyCallEvent(
                        $phone, $type, [$code], null
                    );
                    break;
                case self::ZD_CALLBACK_EVENT_END:
                    $phone = isset($params['caller_id']) ? $params['caller_id'] : null;
                    $code = isset($params['internal']) ? $params['internal'] : 100;
                    $type = 'hangup';

                    if (in_array($code, $internal_codes)) {
                        /** @var \RetailCrm\Response\ApiResponse $result */
                        $result = $this->cCrm->telephonyCallEvent(
                            $phone, $type, [$code], null
                        );

                        if ($result->isSuccessful()) {
                            $call_start = isset($params['call_start']) ? strtotime($params['call_start']) : null;
                            $duration = isset($params['duration']) ? $params['duration'] : null;
                            $externalId = isset($params['pbx_call_id']) ? $params['pbx_call_id'] : null;
                            $reason = isset($params['reason']) ? $params['reason'] : null;
                            $pbx_call_id = isset($params['pbx_call_id']) ? $params['pbx_call_id'] : null;
                            $call_id = isset($params['call_id_with_rec']) ? $params['call_id_with_rec'] : null;

                            $call_record_link = $this->getCallRecord($call_id, $pbx_call_id);

                            $result = $this->cCrm->telephonyCallsUpload([
                                [
                                    'date' => date('Y-m-d H:i:s', $call_start),
                                    'type' => 'in',
                                    'phone' => $phone,
                                    'code' => $code,
                                    'result' => $this->zdStatusToCrmStatus($reason),
                                    'duration' => $duration,
                                    'externalId' => $externalId,
                                    'recordUrl' => $call_record_link
                                ]
                            ]);
                        }
                    }
                    break;
                case self::ZD_CALLBACK_EVENT_OUT_START:
                    $phone = isset($params['destination']) ? $params['destination'] : null;
                    $code = isset($params['internal']) ? $params['internal'] : null;
                    $type = 'out';

                    if (in_array($code, $internal_codes)) {
                        $result = $this->cCrm->telephonyCallEvent(
                            $phone, $type, [$code], null
                        );
                    }
                    break;
                case self::ZD_CALLBACK_EVENT_OUT_END:
                    $phone = isset($params['destination']) ? $params['destination'] : null;
                    $code = isset($params['internal']) ? $params['internal'] : null;
                    $type = 'hangup';

                    if (in_array($code, $internal_codes)) {
                        /** @var \RetailCrm\Response\ApiResponse $result */
                        $result = $this->cCrm->telephonyCallEvent(
                            $phone, $type, [$code], null
                        );

                        if ($result->isSuccessful()) {
                            $call_start = isset($params['call_start']) ? strtotime($params['call_start']) : null;
                            $duration = isset($params['duration']) ? $params['duration'] : null;
                            $externalId = isset($params['pbx_call_id']) ? $params['pbx_call_id'] : null;
                            $reason = isset($params['reason']) ? $params['reason'] : null;
                            $pbx_call_id = isset($params['pbx_call_id']) ? $params['pbx_call_id'] : null;
                            $call_id = isset($params['call_id_with_rec']) ? $params['call_id_with_rec'] : null;

                            $call_record_link = $this->getCallRecord($call_id, $pbx_call_id);

                            $result = $this->cCrm->telephonyCallsUpload([
                                [
                                    'date' => date('Y-m-d H:i:s', $call_start),
                                    'type' => 'out',
                                    'phone' => $phone,
                                    'code' => $code,
                                    'result' => $this->zdStatusToCrmStatus($reason),
                                    'duration' => $duration,
                                    'externalId' => $externalId,
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



    public function validateCallbackParams($params)
    {
        $clientId = isset($params['clientId']) ? $params['clientId'] : null;
        $code = isset($params['code']) ? $params['code'] : null;
        $phone = isset($params['phone']) ? $params['phone'] : null;

        if(empty($clientId) || empty($code) || empty($phone)) {
            $this->Log->error(sprintf('Can\'t make phone callback, to few params'));
            return null;
        }

        try {
            $response_data_for_codes = $this->cZadarma->call('/v1/pbx/internal/', [], 'GET');

            $this->validateZdResponse($response_data_for_codes);

            $response_data_for_codes = json_decode($response_data_for_codes, true);
            $internal_codes = isset($response_data_for_codes['numbers']) ? array_values($response_data_for_codes['numbers']) : [];
        } catch (\Exception $e) {
            $this->Log->error(sprintf('Can\'t get codes from zadarma'));
            return null;
        }

        if(!in_array($code, $internal_codes)) {
            $this->Log->error(sprintf('Can\'t get codes from zadarma'));
            return null;
        }

        $phone = sprintf('+%s', str_replace(' ', '', $phone));

        return [$code, $phone];
    }

    /**
     * @param \RetailCrm\Response\ApiResponse $response
     * @throws \Exception
     *
     * @return bool
     */
    protected function validateCrmResponse($response)
    {
        if (!$response->isSuccessful()) {
            throw new \Exception($response);
        }

        return true;
    }

    protected function zdStatusToCrmStatus($input_status = null)
    {
        $statuses = [
            'CANCEL' => 'failed',
            'ANSWER' => 'answered',
            'BUSY' => 'busy',
            'NOANSWER' => 'no answer',
        ];

        return empty($input_status) ? 'unknown' : (isset($statuses[$input_status]) ? $statuses[$input_status] : 'unknown');
    }
}