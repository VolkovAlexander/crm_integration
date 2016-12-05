<?php
/**
 * AbstractZadarmaIntegration
 * @author Volkov Alexander
 */

namespace lib;

require_once 'Log.php';

class AbstractZadarmaIntegration
{
    protected $crm_name = null;

    protected $zadarma_config = [];
    protected $crm_config = [];

    public $cCrm = null;
    /** @var \Zadarma_API\Client|null */
    public $cZadarma = null;

    /** @var  Log $Log */
    public $Log;

    /**
     * @inheritdoc
     */
    public function __construct()
    {
        define('ROOT_DIR', sprintf('%s/./../', __DIR__));

        $this->zadarma_config = include ROOT_DIR . '/config/zadarma.php';
        $crm_config_file = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, sprintf('%s/config/%s.php', ROOT_DIR, $this->crm_name));

        if (file_exists($crm_config_file)) {
            $this->crm_config = include $crm_config_file;
        } else throw new \Exception(sprintf('Configuration file for "%s" not found', $this->crm_name));

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
            if (!empty($response) && $response['status'] === 'success') {
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

        if ($data['status'] !== 'success') {
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

    protected function initCrmClient()
    {
    }

    protected function parseRequestFromCrm()
    {
    }

    const ZD_CALLBACK_EVENT_START = 'NOTIFY_START';
    const ZD_CALLBACK_EVENT_INTERNAL = 'NOTIFY_INTERNAL';
    const ZD_CALLBACK_EVENT_END = 'NOTIFY_END';
    const ZD_CALLBACK_EVENT_OUT_START = 'NOTIFY_OUT_START';
    const ZD_CALLBACK_EVENT_OUT_END = 'NOTIFY_OUT_END';
}