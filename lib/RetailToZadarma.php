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
                ], [], false, true, true, true, false
            );
            $this->parseResponseFromCrm($result);
        } catch (\RetailCrm\Exception\CurlException $e) {
            echo "Registration action error: " . $e->getMessage();
        }

        return $result;
    }

    /**
     * @param string $phone
     * @param array $codes
     * @param string $type
     * @param string $hangupStatus
     * @return null|\RetailCrm\Response\ApiResponse
     */
    public function sendCallRequestToCrm($phone, $codes, $type = 'in', $hangupStatus = 'answered')
    {
        $result = null;

        try {
            $result = $this->cCrm->telephonyCallEvent($phone, $type, $codes, $hangupStatus);
            $this->parseResponseFromCrm($result);
        } catch (\RetailCrm\Exception\CurlException $e) {
            echo "Send call action error: " . $e->getMessage();
        }

        return $result;
    }

    public function sendCallEventToCrm($params)
    {
        $result = null;

        $internal_codes = [];
        $response_data = $this->cZadarma->call('/v1/pbx/internal/', [], 'GET');

        if(!empty($response_data) && $response_data->success === true) {
            $internal_codes = isset($response_data->numbers) ? $response_data->numbers : [];
        }

        try {
            switch ($params['event']) {
                case self::ZD_CALLBACK_EVENT_OUT_START:
                    $phone = isset($params['destination']) ? $params['destination'] : null;
                    $code = isset($params['internal']) ? $params['internal'] : null;

                    if(in_array($code, $internal_codes, true)) {
                        error_log('ZD_CALLBACK_EVENT_OUT_START: ' . print_r($params, true));

                        $result = $this->cCrm->telephonyCallEvent(
                            $phone, 'out', [$code], null
                        );
                    }
                    break;
                case self::ZD_CALLBACK_EVENT_OUT_END:
                    $phone = isset($params['destination']) ? $params['destination'] : null;
                    $code = isset($params['internal']) ? $params['internal'] : null;

                    if(in_array($code, $internal_codes, true)) {
                        error_log('ZD_CALLBACK_EVENT_OUT_END: ' . print_r($params, true));

                        $result = $this->cCrm->telephonyCallEvent(
                            $phone, 'hangup', [$code], null
                        );
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
}