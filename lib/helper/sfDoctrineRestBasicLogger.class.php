<?php
/**
 * used to log request and response data
 */
class sfDoctrineRestBasicLogger
{
    /**
     * Logs data in the text file
     * @param array $data
     * @param string $filename
     */
    public function log($data ,$filename='/tmp/rest.log')
    {
        if(is_array($data)){
            $data = print_r($data, true);
        }
        file_put_contents($filename, "\n====================================\n".date('H:i:s')."\n".$data, FILE_APPEND);
    }

    /**
     * Retrives Request data from Context
     * @return array
     */
    public function getRequestLogData()
    {
        $request = sfContext::getInstance()->getRequest();
        $log_data = array(
            'Request' => array(
                'context' => $request->getRequestContext(),
                'parameters' => $request->getParameterHolder()->getAll(),
                'content' => $request->getContent(),
            )
        );
        return $log_data;
    }

    /**
     * Retrives response data from Context
     * @return array
     */
    public function getResponseLogData()
    {
        $response = sfContext::getInstance()->getResponse();
        $log_data = array(
            'Response' => array(
                'status_code' => $response->getStatusCode(),
                'content' => $response->getContent()
            )
        );
        return $log_data;
    }    
}