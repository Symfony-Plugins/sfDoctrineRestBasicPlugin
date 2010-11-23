<?php
class ArrayToXML
{
    protected $tidy_config = array(
        "doctype" => "omit",
        "logical-emphasis" => true,
        "show-body-only" => true,
        "quote-ampersand" =>true,
        "char-encoding" => "utf8",
        "output-xml" => true,
        "clean" => true,
        "input-xml" => true,
        "indent" => true,
//        "escape-cdata" => true, //to test layout
        "indent-cdata" => true, //to test layout
        "wrap" => 0
    );


    /**
     * The main function for converting to an XML document.
     * Pass in a multi dimensional array and this recrusively loops through and builds up an XML document.
     *
     * @param array $data
     * @param string $rootNodeName - what you want the root node to be - defaultsto data.
     * @param SimpleXMLElement $xml - should only be used recursively
     * @return string XML
     */
    public function toXml($data, $rootNodeName = 'data', &$xml=null)
    {
        // turn off compatibility mode as simple xml throws a wobbly if you don't.
        if (ini_get('zend.ze1_compatibility_mode') == 1)
        {
            ini_set ('zend.ze1_compatibility_mode', 0);
        }

        if (is_null($xml))
        {
            $xml = simplexml_load_string("<?xml version='1.0' encoding='utf-8'?><$rootNodeName />");
        }

        // loop through the data passed in.
        foreach($data as $key => $value)
        {
            // if numeric key, assume array of rootNodeName elements
            if (is_numeric($key))
            {
                $key = $rootNodeName;
            }

            // delete any char not allowed in XML element names
            $key = preg_replace('/[^a-z0-9\-\_\.\:]/i', '', $key);

            // if there is another array found recrusively call this function
            if (is_array($value) && $key != 'href')
            {                
                // create a new node unless this is an array of elements
                $node = $this->isAssoc($value) ? $xml->addChild($key) : $xml;

                // recrusive call - pass $key as the new rootNodeName
                $this->toXml($value, $key, $node);
            }
            else
            {
                // add single node or href node.
                //$value = htmlentities($value);
                //cdata non numeric nodes                
                if (is_numeric($value) || is_bool($value)){
					$xml->addChild($key,$value);
				}
				else
				{
                    if ($key == 'href')
                    {                        
//                        $href = $xml->addChild('a');
//                        $node= dom_import_simplexml($href);
//                        $no = $node->ownerDocument;
//                        $node->appendChild($no->createCDATASection($value['name']));
                        $href = $xml->addChild('a', htmlentities($value['name']));
                        $href->addAttribute('href', $value['link']);
                    }
                    else
                    {                     
					$new_node = $xml->addChild($key);
					$node= dom_import_simplexml($new_node);
					$no = $node->ownerDocument;
					$node->appendChild($no->createCDATASection($value));
				}
            }
            }

        }

        //tidyup xml
        $xml = tidy_repair_string($xml->asXML(), $this->tidy_config);


        // pass back as string. or simple xml object if you want!
        return $xml;
    }

    // determine if a variable is an associative array
    public function isAssoc( $array ) {
        return (is_array($array) && 0 !== count(array_diff_key($array, array_keys(array_keys($array)))));
    }
}
