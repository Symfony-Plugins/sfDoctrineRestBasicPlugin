<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of sfDoctrineRestBasicGenerateModule
 *
 * @author Villani
 */
class sfDoctrineRestBasicGenerateModule extends sfBaseTask
{

    /**
     * @see sfTask
     */
    protected function configure()
    {
        $this->addArguments(array(
            new sfCommandArgument('application', sfCommandArgument::REQUIRED, 'The application name'),
            new sfCommandArgument('module', sfCommandArgument::REQUIRED, 'The module name'),
            new sfCommandArgument('model', sfCommandArgument::REQUIRED, 'The Doctrine model class to use'),
            new sfCommandArgument('extends', sfCommandArgument::OPTIONAL, 'The class that you want sfDoctrineRestBasic to extend'),
        ));

        $this->namespace = 'generate';
        $this->name = 'sfDoctrineRestBasicModule';
        $this->briefDescription = 'Generates a sfDoctrineRestBasic module';

        $this->detailedDescription = <<<EOF
The [generate:sfDoctrineRestBasicModule|INFO] task generates a sfDoctrineRestBasic module:

  [./symfony generate:sfDoctrineRestBasicModule frontend article Article|INFO]

The task creates [%article%|COMMENT] rest module in the [%frontend%|COMMENT]
application.

The task creates routes for you in the application [routing.yml|COMMENT] to handle all rest based actions: get, put, post, delete, head.
EOF;
    }

    /**
     * @see sfTask
     */
    protected function execute($arguments = array(), $options = array())
    {
        // create routes
        $model = $arguments['model'];
        $module = $arguments['module'];
        $extends = $arguments['extends'];

        $routing = sfConfig::get('sf_app_config_dir') . '/routing.yml';
        $content = file_get_contents($routing);
        $routesArray = sfYaml::load($content);

        //REST routes
//        $routes = array(
//            'get' => array('id', 'main')
//            , 'head' => array('id', 'main')
//            , 'put' => array('main')
//            , 'post' => array('id')
//            , 'delete' => array('id')
//        );
        
        if (!isset($routesArray[$module]))
        {

            //echo "building main - $module - $method";
            $content = sprintf(<<<EOF

%s:
  url: /%s/*
  class: sfDoctrineRestBasicRoute
  param: { module: %s}
  requirements:
    sf_method: [get, head, put]
    
EOF
                    , $module
                    , $module
                    , $module
            ) . $content;

            $this->logSection('file+', $routing);
            file_put_contents($routing, $content);
        }

        if (!isset($routesArray[$module . "_id"]))
        {
            // build ID specific routes
            //echo "building id - $module - $method";
            $content = sprintf(<<<EOF

%s:
  url: /%s/:id/*
  class: sfDoctrineRestBasicRoute
  param: { module: %s}
  requirements:
    id: \d+
    sf_method: [get, head, post, delete]

EOF
                    , $module . "_id"
                    , $module
                    , $module
            ) . $content;

            $this->logSection('file+', $routing);
            file_put_contents($routing, $content);

        }  

        return $this->generate($module, $model, $extends);
    }

    protected function generate($module, $model, $extends = null)
    {
        //module app directory
        $moduleDir = sfConfig::get('sf_app_module_dir') . "/" . $module;

        //get possible dirs
        $dirs = $this->configuration->getGeneratorSkeletonDirs('sfDoctrineRestBasic', 'default');
        $finder = sfFinder::type('any');
        
        //get dir
        foreach ($dirs as $dir)
        {
            if (is_dir($dir))
            {
                $this->getFilesystem()->mirror($dir, $moduleDir, $finder);
                break;
            }
        }

        //tokenize the action
        $tokens = array(
            'moduleName' => $module,
            'baseActionsClass' => (isset($extends)) ? $extends: 'sfDoctrineRestBasicActions',
            'doctrineModelClassName' => $model
        );

        $finder = sfFinder::type('file');
        $this->getFilesystem()->replaceTokens($finder->in($moduleDir), '%', '%', $tokens);
    }    

}

?>
