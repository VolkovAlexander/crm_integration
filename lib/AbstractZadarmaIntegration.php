<?php
/**
 * AbstractZadarmaIntegration
 * @author Volkov Alexander
 */

namespace lib;

require_once 'Log.php';

class AbstractZadarmaIntegration
{
    protected $zadarma_config = [];
    protected $crm_config = [];

    public $cCrm = null;
    /** @var \Zadarma_API\Client|null  */
    public $cZadarma = null;

    /** @var  Log $Log */
    public $Log;

    /**
     * @inheritdoc
     */
    public function __construct($zd_config = [], $crm_config = [])
    {
        $this->zadarma_config = $zd_config;
        $this->crm_config = $crm_config;

        try {
            $this->initZadarmaClient();
            $this->initCrmClient();
            $this->Log = new Log();
        } catch (\Exception $e) {
            throw new \Exception('Can\'t initialize api-clients');
        }
    }

    /**
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

            $this->Log->notice(sprintf('New output calling from %s to %s', $from, $to));
        } catch (\Exception $e) {
            $this->Log->error(sprintf('Failed output calling from %s to %s (%s)', $from, $to, $e->getMessage()));
        }

        return $result;
    }

    public function getCallRecord($call_id, $pbx_call_id, $lifetime = 5184000)
    {
        $result = null;

        try {
            $response = $this->cZadarma->call('/v1/pbx/record/request/', [
                'call_id' => $call_id,
                'pbx_call_id' => $pbx_call_id,
                'lifetime' => $lifetime
            ], 'get');

            $this->validateZdResponse($response);

            $response = json_decode($response, true);
            if(!empty($response) && $response['status'] === 'success') {
                $result = (isset($response['links']) && count($response['links']) === 1) ? $response['links'][0] : null;
            }
        } catch (\Exception $e) {
            $this->Log->error(sprintf('Can\'t get call record (%s)', $e->getMessage()));
        }


        return $result;
    }

    public function validateZdResponse($response)
    {
        $data = json_decode($response, true);

        if($data['status'] !== 'success') {
            throw new \Exception($data['message']);
        }

        return true;
    }

    protected function initZadarmaClient()
    {
        $this->cZadarma = new \Zadarma_API\Client(
            $this->zadarma_config['key'], $this->zadarma_config['secret']
        );
    }

    protected function initCrmClient() {}

    protected function parseRequestFromCrm() {}

    const ZD_CALLBACK_EVENT_START = 'NOTIFY_START';
    const ZD_CALLBACK_EVENT_INTERNAL = 'NOTIFY_INTERNAL';
    const ZD_CALLBACK_EVENT_END = 'NOTIFY_END';
    const ZD_CALLBACK_EVENT_OUT_START = 'NOTIFY_OUT_START';
    const ZD_CALLBACK_EVENT_OUT_END = 'NOTIFY_OUT_END';
}