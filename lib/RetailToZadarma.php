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
            $this->parseResponseFromCrm($result);
        } catch (\RetailCrm\Exception\CurlException $e) {
            echo "Registration action error: " . $e->getMessage();
        }

        return $result;
    }

    public function sendCallEventToCrm($params)
    {
        $result = null;

        $internal_codes = [];
        $response_data = json_decode($this->cZadarma->call('/v1/pbx/internal/', [], 'GET'), true);

        if(!empty($response_data) && $response_data['status'] === 'success') {
            $internal_codes = isset($response_data['numbers']) ? array_values($response_data['numbers']) : [];
        }

        try {
            switch ($params['event']) {
                case self::ZD_CALLBACK_EVENT_START:
                    $phone = isset($params['caller_id']) ? $params['caller_id'] : null;
                    $code = isset($params['internal']) ? $params['internal'] : 100;

                    $result = $this->cCrm->telephonyCallEvent(
                        $phone, 'in', [$code], null
                    );
                    break;
                case self::ZD_CALLBACK_EVENT_END:
                    $phone = isset($params['caller_id']) ? $params['caller_id'] : null;
                    $code = isset($params['internal']) ? $params['internal'] : 100;

                    if(in_array($code, $internal_codes)) {
                        /** @var \RetailCrm\Response\ApiResponse $result */
                        $result = $this->cCrm->telephonyCallEvent(
                            $phone, 'hangup', [$code], null
                        );

                        if($result->isSuccessful()) {
                            $call_start = isset($params['call_start']) ? strtotime($params['call_start']) : null;
                            $duration = isset($params['duration']) ? $params['duration'] : null;
                            $externalId = isset($params['pbx_call_id']) ? $params['pbx_call_id'] : null;
                            $reason = isset($params['reason']) ? $params['reason'] : null;
                            $pbx_call_id = isset($params['pbx_call_id']) ? $params['pbx_call_id'] : null;

                            $result = $this->cCrm->telephonyCallsUpload([
                                [
                                    'date' => date('Y-m-d H:i:s', $call_start),
                                    'type' => 'in',
                                    'phone' => $phone,
                                    'code' => $code,
                                    'result' => $this->zdStatusToCrmStatus($reason),
                                    'duration' => $duration,
                                    'externalId' => $externalId,
                                    'recordUrl' => $this->getCallRecord(null, $pbx_call_id)
                                ]
                            ]);
                        }
                    }
                    break;
                case self::ZD_CALLBACK_EVENT_OUT_START:
                    $phone = isset($params['destination']) ? $params['destination'] : null;
                    $code = isset($params['internal']) ? $params['internal'] : null;

                    if(in_array($code, $internal_codes)) {
                        $result = $this->cCrm->telephonyCallEvent(
                            $phone, 'out', [$code], null
                        );
                    }
                    break;
                case self::ZD_CALLBACK_EVENT_OUT_END:
                    $phone = isset($params['destination']) ? $params['destination'] : null;
                    $code = isset($params['internal']) ? $params['internal'] : null;

                    if(in_array($code, $internal_codes)) {
                        /** @var \RetailCrm\Response\ApiResponse $result */
                        $result = $this->cCrm->telephonyCallEvent(
                            $phone, 'hangup', [$code], null
                        );

                        if($result->isSuccessful()) {
                            $call_start = isset($params['call_start']) ? strtotime($params['call_start']) : null;
                            $duration = isset($params['duration']) ? $params['duration'] : null;
                            $externalId = isset($params['pbx_call_id']) ? $params['pbx_call_id'] : null;
                            $reason = isset($params['reason']) ? $params['reason'] : null;

                            $result = $this->cCrm->telephonyCallsUpload([
                                [
                                    'date' => date('Y-m-d H:i:s', $call_start),
                                    'type' => 'out',
                                    'phone' => $phone,
                                    'code' => $code,
                                    'result' => $this->zdStatusToCrmStatus($reason),
                                    'duration' => $duration,
                                    'externalId' => $externalId,
                                    'recordUrl' => null
                                ]
                            ]);
                        }
                    }
                    break;
                default:
                    break;
            }
        } catch (\Exception $e) {
            echo 'Can\'t parse input data';
        }


        if(!empty($result)) {
            $this->parseResponseFromCrm($result);
        }

        return $result;
    }

    /**
     * @param \RetailCrm\Response\ApiResponse $response
     * @return \RetailCrm\Response\ApiResponse
     * @throws \Exception
     */
    protected function parseResponseFromCrm($response)
    {
        if ($response->isSuccessful()) {
            return $response;
        } else {
            throw new \Exception('Error making request: ' . print_r($response, true));
        }
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