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
                'zadarma', 'volandkb', true, 'Zadarma', 'http://www.clker.com/cliparts/O/n/v/t/d/3/ringing-red-telephone.svg',
                'http://www.clker.com/cliparts/O/n/v/t/d/3/ringing-red-telephone.svg',
                [
                    ['userId' => '8', 'code' => 101]
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
            throw new \Exception('Error making request');
        }
    }
}