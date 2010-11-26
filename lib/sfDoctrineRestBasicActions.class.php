<?php

/**
 * Description of sfDoctrineRestBasicActions
 *
 * class handles the basic rest actions for get/put/head/etc...
 * they can be overwritten if specific functionality is required
 *
 * @author Sean Villani
 */
class sfDoctrineRestBasicActions extends sfDoctrineRestBasic
{

    /**
     * array of values that the db should ignore on post and put method calls
     *
     * @var array
     */
    protected $dbKeysIgnore;

    /**
     * array of values that the should be cleaned from passed data on put/post
     *
     * @var array
     */
    protected $keysToBeCleaned;

     /**
     * defines all of the available rest methods for this specific module
     *
     * @var array
     */
    protected $availableMethods = array('GET', 'HEAD', 'POST', 'PUT', 'DELETE');

    /**
     * holds the module name which 90% of the time should be the name of the of the Doctrine object
     * it's defined here so it can be overwritten
     *
     * @var string
     */
    protected $doctrineModelClassName;


    /**
     * methodOverride
     *
     * checks to see if the method has been passed in the url to override the method
     * this helps us make posts,put,deletes tunnelled through a GET method for JSONP
     *
     */
    protected function methodOverride()
    {        
        $originalMethod = strtoupper($this->getRequest()->getMethod());

        $newMethod = strtoupper($this->getRequestParameter('method'));

        //validate passed method
        $this->checkAvailableMethods($newMethod);

        if ($newMethod)
        {
            //remove the method param, otherwise it'll get stuck in endless loop
            $this->getRequest()->setParameter('method', null);

            //automatically set the serialize param to jsonp
            $this->getRequest()->setParameter('serialize', 'jsonp');

            //flag it for later            
            $this->getRequest()->setParameter('jsonpMethodOverride', $newMethod);

            if ($newMethod == 'PUT')
            {
                //forward to acceptable action
                $this->forward($this->getRequestParameter('module'), strtolower('POST'));
            }
            else
            {
                //forward to acceptable action
                $this->forward($this->getRequestParameter('module'), strtolower($newMethod));
            }
            
        }        
    }    

    /**
     * sets class var of keys that should be ignore on put/post
     */
    protected function setDbKeysIgnore()
    {
        $this->dbKeysIgnore = array(            
            'created_at'
            , 'updated_at'            
            , 'deleted_at'
        );
    }

    /**
     * sets class var of keys that should be removed from the incoming put/post data content
     * we don't want these values because doctrine handles them
     */
    protected function setKeysToBeCleaned()
    {
        $this->keysToBeCleaned = array(
            'id'
            , 'created_at'
            , 'updated_at'
            , 'deleted_at'
        );
    }    

    /**
     * removes unwanted values from the array
     *
     * @param array $array
     * @param array $unwanted_keys
     * @return array $array is returned back minus the unwanted keys
     */
    protected function _cleanArray($array, $unwanted_keys)
    {
        // we don't want them to alter any of the primary keys
        foreach($unwanted_keys as $k)
        {
            unset($array[$k]);
        }

        return $array;
    }

    /**
     * checks available methods and allows or disallows access
     */
    protected function checkAvailableMethods($method = null)
    {
        if (!$method)
        {
            $method = strtoupper($this->getRequest()->getMethod());
        }
        else
        {
            $method = strtoupper($method);
        }

        //uppercase all available methods for compare
        foreach($this->availableMethods as &$meth)
        {
            $meth = strtoupper($meth);
        }        

        // check if it's allowed
        if (!in_array($method, $this->availableMethods))
                 throw new sfException('Method not allowed', 405);        
    }

    /**
     * does all the checks before a result is produced
     * @param sfWebRequest $request
     */
    protected function checkRequest()
    {
        // check for key
        $this->checkKey();
        
        //check the available methods
        $this->checkAvailableMethods();
    }    

    /**
     * sets the doctrine model class name to the module name if not specified
     */
    protected function setDoctrineModelClassName()
    {        
        if (!isset($this->doctrineModelClassName))
        {
            $this->doctrineModelClassName = $this->getModuleName();
        }
    }    

    /**
     * builds the get query can be overridden to exclude/include data
     * 
     * @return <type> 
     */
    protected function buildDoctrineQueryGET()
    {
        //query db
        $q = Doctrine_Query::create()
                        ->select('*')
                        ->from($this->doctrineModelClassName);               

        return $q;
    }

    /**
     * builds the query to for get method
     * 
     * @param sfWebRequest $request
     * @return object doctrine obj 
     */
    protected function getData()
    {
        $request = $this->getRequest();

        if ($request->getParameter('id'))
        {            
            $data = Doctrine::getTable($this->doctrineModelClassName)->find($request->getParameter('id'));

            if ($data)
            {
                $data = $data->toArray();
            }
            else
            {
                throw new sfException("id doesn't exist", 404);
            }
        }
        else
        {
            $q = $this->buildDoctrineQueryGET();

            $data = $q->execute()->toArray();
        }

        return $data;
    }

    ////////////////////////////////////////////////////////////
    // basic SYMFONY ACTIONS defined here
    ////////////////////////////////////////////////////////////

    /**
     * executes before any action
     */
    public function preExecute()
    {
        parent::preExecute();

        $this->setDoctrineModelClassName();

        //check if method is being overwritten for jsonp tunnelling
        $this->methodOverride();

    }

     /**
     * basic GET request
     *
     * @param sfWebRequest $request
     * @return null
     */
    public function executeGet(sfWebRequest $request)
    {
        try
        {
            //checks request valid
            $this->checkRequest();

            //get db data
            $db_data = $this->getData();

            // no data
            if (empty($db_data))
                throw new sfException('No data to return', 204);

            // if an id is not present add a href link
            if (!$this->getRequestParameter('id') && ($this->getRequestParameter('serialize') == 'xml'||!$this->getRequestParameter('serialize')))
            {
                $this->mergeAnchors($db_data);
            }

            // create array for xml
            //$results = array('data' => $db_data);
            $results = $db_data;

            //serialize
            $data = $this->serializeData($results, $request->getParameter('serialize'));

            //ouptut req
            $this->outputRequest($data);
        }
        catch (Exception $e)
        {
            $this->handleException($e);
        }

        // required by symfony
        return sfView::NONE;
    }

    /**
     * Executes POST action
     * handles both POST AND PUT
     *
     * @param sfRequest $request A request object
     */
    public function executePost(sfWebRequest $request)
    {        
        try
        {
            //checks request valid
            $this->checkRequest();

            //set method to check for put/post later
            $method = $this->getRequest()->getMethod();

            //check if it's a jsonp tunnel
            if($request->getParameter('jsonpMethodOverride'))
            {
                //override method 
                $method = $request->getParameter('jsonpMethodOverride');
                
                //get data from url
                $data =  $request->getParameter('data');                
                
            }
            else
            {
                //get content from proper rest request
                $data = $request->getContent();
            }

            //decode it
            $data = json_decode($data, true);

            if(!$data)
            {
                throw new sfException("Data is empty", 400); // bad request
            }

            // get the keys that should be ignored on update
            $this->setDbKeysIgnore();

            // get the keys that should be cleaned from the content passed
            $this->setKeysToBeCleaned();

            //remove unwanted values
            $data = $this->_cleanArray($data, $this->keysToBeCleaned);

            if ($method == 'POST')
            {

                $id = $request->getParameter('id');
                
                //get record
                if ($id)
                {
                    $record = Doctrine::getTable($this->doctrineModelClassName)->find($id);

                    //get differences
                    $updates = array_diff($data, $record->toArray());

                    if (count($updates))
                    {
                        //update record w differences
                        foreach ($updates as $key => $val)
                        {

                            if (!in_array($key, $this->dbKeysIgnore))
                            {
                                $record[$key] = $val;
                            }                            
                        }

                        // this needs to be type cast
                        $record->updated_by = (int)$record->updated_by;
                        $record->save();

                        $data = $this->serializeData('Updated', $request->getParameter('serialize'));

                        //ouptut req
                        $this->outputRequest($data, array(),  201);
                    }
                    else
                    {
                        //ouptut req
                        throw new sfException("Nothing to update!", 202);
                    }

                }
                else
                {
                    throw new sfException("id not present", 400);
                }
            }
            else
            {
                //it's a put so let's add the record                
                $e = new $this->doctrineModelClassName();
                $e->fromArray((array)$data);
                // this needs to be type cast
                $e->updated_by = (int)$e->updated_by;
                $e->save();                

                if($e->id)
                {
                    //return the object back
                    $result = Doctrine::getTable($this->doctrineModelClassName)->findById($e->id);
                    
                    //ouptut req
                    $data = $this->serializeData($result->toArray(), $request->getParameter('serialize'));

                    $this->outputRequest($data, array('id' => $e->id),  201);
                }
                else
                {
                    //ouptut req
                    $this->outputRequest('Not created there was an error', array(),  500);
                }
            }


            //output text
        }
        catch (Exception $e)
        {
            //print_r($e);exit;
            $this->handleException($e);
        }

        // required by symfony
        return sfView::NONE;

    }

    /**
     * basic DELETE request
     *
     * @param sfWebRequest $request (url params id, updated_by required)
     * @return null
     */
    public function executeDelete(sfWebRequest $request)
    {
       try
        {
            //checks request valid
            $this->checkRequest();

            $id = $request->getParameter('id');            

            //get record
            if ($id)
            {
                $record = Doctrine::getTable($this->doctrineModelClassName)->find($id);

                if ($record)
                {
                    $record->delete();

                    //ouptut req
                    $data = $this->serializeData('Deleted', $request->getParameter('serialize'));
                    $this->outputRequest($data, array(),  202);
                }
                else
                {
                    $this->outputRequest("$id not found", array(),  404);
                }

            }
            else
            {
                throw new sfException("id not present", 400);
            }



            //output text
        }
        catch (Exception $e)
        {
            $this->handleException($e);
        }

        // required by symfony
        return sfView::NONE;
    }

    public function executePut(sfWebRequest $request)
    {        
        $this->forward($this->getModuleName(), 'post');
    }
    
}
