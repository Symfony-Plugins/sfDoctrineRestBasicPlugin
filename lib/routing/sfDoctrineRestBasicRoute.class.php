<?php
/**
 * Description of sfDoctrineRestBasicRoute
 *
 * @author Villani
 */
class sfDoctrineRestBasicRoute extends sfRequestRoute  {

    public function  matchesUrl($url, $context = array())
    {
        $parameters = parent::matchesUrl($url, $context);

        if ($parameters)
        {
            //head is really just a get
            $meth = ($context['method'] == 'HEAD')? 'GET' : $context['method'];
            
            //set the action to the requested method
            $parameters['action'] = strtolower($meth);

            return $parameters;
        }

        return FALSE;
    }

   
}

