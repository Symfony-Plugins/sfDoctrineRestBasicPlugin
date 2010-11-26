<?php
/**
 * rest is the base class for all of the rest actions
 *
 * @author Sean Villani
 */
class sfDoctrineRestBasic extends sfActions
{
    /**
     * houses the logger class when instantiated
     *
     * @var obj
     */
    protected $logger;

    /**
     * set up logging, check api key
     *
     * @see app.yml for config var
     */
    public function preExecute()
    {        
        if (sfConfig::get('app_sfDoctrineRestBasic_logging_enabled'))
        {
            $this->logger = new sfDoctrineRestBasicLogger();
            $log_data = $this->logger->getRequestLogData();
            $this->logger->log($log_data, sfConfig::get('app_sfDoctrineRestBasic_request_log_prefix').date('Y-m-d').'.log');
        }        
    }

    /**
     * set up logging response
     *
     * @see app.yml for config var
     */
    public function postExecute()
    {
        if (sfConfig::get('app_sfDoctrineRestBasic_logging_enabled'))
        {            
            $log_data = array_merge($this->logger->getRequestLogData(), $this->logger->getResponseLogData());
            $this->logger->log($log_data, sfConfig::get('app_sfDoctrineRestBasic_response_log_prefix').date('Y-m-d').'.log');
        }
    }    

    /**
     *
     * @param Exception obj $e
     */
    public function handleException($e)
    {
        $code = (!$e->getCode() || $e->getCode() > 500) ? 500 : $e->getCode();


        $this->getResponse()->setHttpHeader('Message', $e->getMessage());
        $this->getResponse()->setStatusCode($code);
        $this->renderText($e->getMessage());

        // log all exceptions except for 204
        if($code != 204)
        {
            if (sfConfig::get('app_sfDoctrineRestBasic_exceptions_enabled'))
            {
                $this->logger = (isset($this->logger)) ? $this->logger : new sfDoctrineRestBasicLogger();
                $log_data = array_merge($this->logger->getRequestLogData(), $this->logger->getResponseLogData());
                $this->logger->log($log_data, sfConfig::get('app_sfDoctrineRestBasic_exceptions_log_prefix').date('Y-m-d').'.log');
            }
        }
        
//        exit; // for testing only
    }

    /**
     * handles the output to be sent to the rest client
     *
     * @param string $text to return
     * @param array $additionalHeaders key=>value of additonal headers to set
     * @param int $status http status code
     */
    public function outputRequest($text = null, $additionalHeaders = array(), $status = 200)
    {
        foreach($additionalHeaders as $key=>$val)
        {
            $this->getResponse()->setHttpHeader($key, $val);
        }

        $this->getResponse()->setStatusCode($status);
        if ($this->getRequest()->getMethod() != 'HEAD')
        {
            $this->renderText($text);
        }
    }


    /**
     * serializes data depending on what the user requests
     *
     * @param mixed $data
     * @param string $dataContainerName
     * @param string $type json|php|xml
     * @return mixed
     */
    public function serializeData($data, $type = 'xml')
    {
        $return = null;
       
        switch($type)
        {
            case 'array':
                $return = print_r($data, true);
                break;

            case 'json':

                $return = json_encode($data);

                break;

            case 'jsonp':

                $callback = $this->getRequestParameter('callback');
                if (!$callback)
                {
                    throw new sfException("Missing 'callback' paramater for jsonp serialization", 400);
                }

                $return = $callback . '(' .json_encode($data) . ');';

                break;

            case 'php':
            case 'phpserialized':
            case 'phpserialize':

                $return = serialize($data);

                break;

            default:
                $arr2xml = new sfDoctrineRestBasicArrayToXML();
                $data = array('data' => $data);
                $return = $arr2xml->toXML($data, 'results');
        }

        if (is_null($return)) throw new sfException('Error occured', 500);
       
        return $return;
    }

    /**
     * adds anchors
     *
     * @param array $data
     * @param string $id the name of the data id field
     * @param string $name the name of the data name field for the <a> tag
     */
    public function mergeAnchors(&$data, $id = 'id', $name = 'name') 
    {
        $urlParams = $this->getRequest()->getParameterHolder()->getAll();

        //take out unneed vars
        $mod = $urlParams['module'];
        unset($urlParams['module'], $urlParams['action'], $urlParams['href']);
        

        //use the rest
        $qsParam = null;
        foreach ($urlParams as $key => $val)
        {            
            $qsParam .= '/' . $key . '/' . $val;
        }        

        foreach($data as &$d)
        {
            $url = $_SERVER['SCRIPT_NAME'] . '/'.$mod . '/' .@$d[$id] . $qsParam;
            $d['href'] = array('link' => $url, 'name' => @$d[$name]);
        }
    }

    /**
     * hook to check md5 key or any other webservice key see example
     * essentially just throw exception if key is missing or invalid
     */
    protected function checkKey()
    {

//        $key = $this->getRequestParameter('key');
//
//        $cacheKey = 'keys_'.$key;
//
//        if ($key)
//        {
//
//            $q = Doctrine::getTable('Restaurants')
//                    ->createQuery('id')
//                    ->where('md5_key = ?', $key)
//                    ->useResultCache(true, 3600, $cacheKey);
//
//            $rest = $q->execute();
//
//            $this->key = $rest[0]['id'];
//
//            if (!$this->key)
//            {
//                throw new sfException('Webservice Key is invalid', 401);
//            }
//        }
//        else
//        {
//            throw new sfException('Webservice Key is missing', 401);
//        }
    }

}

