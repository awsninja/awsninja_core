<?php

class SimpleDBService {

	private static $instance;
	static function instance()
    {
        if (!isset(self::$instance))
        {
            self::$instance = new self();
        }
        return self::$instance;
    }
	
    private function __construct ()
    {
			$this->accessKey = AWS_ACCESS_KEY;
			$this->secretKey = AWS_SECRET_KEY;
    }
    
    
    public function CreateDomain($domainName)
    {
    	$params = array(
    		'DomainName' => $domainName
    	);
    	return $this->sendRequest('CreateDomain', $params );
    }
    
    
    public function PutAttributes($domainName, $itemName, SimpleDBAttributeCollection $attributes)
    {
    	$params = $attributes->getParams();
    	$params['DomainName'] = $domainName;
    	$params['ItemName'] = $itemName;
    	$res =  $this->sendRequest('PutAttributes', $params);
    	return $res;
    }
    
    public function GetAttributes($domainName, $itemName, $attributeNames=null)
    {
    	$params = array();
    	$params['DomainName'] = $domainName;
    	$params['ItemName'] = $itemName;
    	if (isset($attributeNames))
    	{
    		if (is_array($attributeNames))
    		{
    			for($i=0; $i<count($attributeNames); $i++)
    			{
    				$attrName = $attributeNames[$i];
    				$params['AttributeName.' . $i] = $attrName;
    			}
    		}
    		else 
    		{
    			$params['AttributeName.0'] = $attributeNames;
    		}
    	}
    	
    	$resObj =  $this->sendRequest('GetAttributes', $params );
    	if ($resObj->responseCode == 200)
    	{
    		return $this->parseGetAttributesResponse($resObj->xml);
    	}
    	else
    	{
    		echo($resObj->responseData);
    	}
    	
    }
    
    public function Select($selectExpression, $nextToken=null)
    {
    	$params = array();
    	$params['SelectExpression'] = $selectExpression;
    	if (isset($nextToken))
    	{
    		$params['NextToken'] = $nextToken;
    	}
		
    	$resObj = $this->sendRequest('Select', $params);
    	if ($resObj->responseCode != 200)
    	{
    		var_dump($resObj);
    		throw new Exception('Error');	
    	}

    	//forget the last nextToken and see if we have another one
    	if (isset($resObj->responseData->NextToken))
    	{
    		$nextToken = $resObj->responseData->NextToken;
    	}
    	else
    	{
    		$nextToken = null;
    	}
    	$result =  $this->createReturnObjectStructure($resObj->responseData->children());
    	
    	if (isset($nextToken))
    	{
    		$result = array_merge($result,  $this->Select($selectExpression, $nextToken));
    	}
    	return $result;
    }
    
    
    public function ListDomains($maxNumberOfDomains=100,$nextToken=null)
    {
    	$params = array(
    		'MaxNumberOfDomains'=>$maxNumberOfDomains
    	);
    	if (isset($nextToken))
    	{
    		$params['NextToken'] = $nextToken;
    	}
   // 	$reqStr = $this->createAmazonRequestString('ListDomains', $params );
    	return $this->sendRequest('ListDomains', $params);
    }
    
    public function DeleteAttributes($domainName, $itemName, $attributeNames=null)
    {
        $params = array();
    	$params['DomainName'] = $domainName;
    	$params['ItemName'] = $itemName;
    	if (isset($attributeNames))
    	{
    		if (is_array($attributeNames))
    		{
    			for($i=0; $i<count($attributeNames); $i++)
    			{
    				$attrName = $attributeNames[$i];
    				$params['AttributeName.' . $i] = $attrName;
    			}
    		}
    		else 
    		{
    			$params['AttributeName.0'] = $attributeNames;
    		}
    	}
      //	$reqStr = $this->createAmazonRequestString('DeleteAttributes', $params );
    	return $this->sendRequest('DeleteAttributes', $params );  	
    }
    
    private function createReturnObjectStructure($res)
    {	
    	$xml = $res;
    	$items = $xml;
    	$numResults = count($items);
    	$objStruct = array();
    	for ($i=0;$i<$numResults; $i++)
    	{
    		$itm = $items[$i];
			
    	//	var_dump($itm);
				
    		$atts = new SimpleDBAttributeCollection();
  			$children = $itm->Attribute;
    		for($j=0; $j<count($children); $j++)
    		{
    			$child = $children[$j];
    			if ($child->getName() == 'Attribute')
    			{
	  				$sdba = new SimpleDBAttribute($child->Name, (string)$child->Value);
	    			$atts->add($sdba);
    			}
    
    		}
    		$objStruct["{$itm->Name}"] = $atts;
    	}
    	return $objStruct;
    }
	
    private function parseGetAttributesResponse($res)
    {
    
    	$xml = $res;
    	$atts = new SimpleDBAttributeCollection();
    	foreach($res->GetAttributesResult->children() as $at)
    	{
    		$sdba = new SimpleDBAttribute($at->Name, $at->Value);
    		$atts->add($sdba);
    	
    	}
    	return $atts;
    }
    
    
//  private function sendRequest($url)
//	{
//		$xmlStr = file_get_contents($url);
//		return $xmlStr;
//	}
	
   private function sendRequest($action, $nameValPairs)
   {
    	$nameValPairs['Action'] = $action;
			$params = $this->_addRequiredParameters($nameValPairs);
   		$query = $this->_getParametersAsString($params);
        $url = parse_url ('https://sdb.amazonaws.com/');
        $post  = "POST / HTTP/1.0\r\n";
        $post .= "Host: " . $url['host'] . "\r\n";
        $post .= "Content-Type: application/x-www-form-urlencoded; charset=utf-8\r\n";
        $post .= "Content-Length: " . strlen($query) . "\r\n";
        $post .= "User-Agent: AWS Ninja\r\n";
        $post .= "\r\n";
        $post .= $query;

        $response = '';
        
        if (isset($url['port']))
        {
        	$port = $url['port'];
        }
        else
        {
        	$port = 80;
        }

        if ($socket = @fsockopen($url['host'], $port, $errno, $errstr, 10))
        {
            fwrite($socket, $post);
            while (!feof($socket))
            {
                $response .= fgets($socket, 1160);
            }
            fclose($socket);
            list($other, $responseBody) = explode("\r\n\r\n", $response, 2);
            $other = preg_split("/\r\n|\n|\r/", $other);
            list($protocol, $code, $text) = explode(' ', trim(array_shift($other)), 3);
        }
        else
        {
            throw new Exception ("Unable to establish connection to host " . $url['host'] . " $errstr");
        }
        return new SimpleDbResponse((int)$code,  $responseBody);
   	
   }
   
   

    public function _addRequiredParameters(array $parameters)
    {
        $parameters['AWSAccessKeyId'] = $this->accessKey;
        $parameters['Timestamp'] = $this->_getFormattedTimestamp();
        $parameters['Version'] = '2009-04-15';
        $parameters['SignatureVersion'] = 2;
        $parameters['SignatureMethod'] = 'HmacSHA256';
        $parameters['Signature'] = $this->_signParameters($parameters, $this->secretKey);
        return $parameters;
    }

    private function _signParameters(array $parameters, $key) {
        $stringToSign = $this->_calculateStringToSignV2($parameters);
        return $this->_sign($stringToSign, $key);
    }
	
    public function _calculateStringToSignV2(array $parameters) {
        $data = 'POST';
        $data .= "\n";
        $data .= 'sdb.amazonaws.com';
        $data .= "\n";
        $data .= '/';
        $data .= "\n";
        uksort($parameters, 'strcmp');
        $data .= $this->_getParametersAsString($parameters);
        return $data;
    }
    private function _urlencode($value) {
		return str_replace('%7E', '~', rawurlencode($value));
    }
	

    private function _getParametersAsString(array $parameters)
    {
        $queryParameters = array();
        foreach ($parameters as $key => $value)
        {
            $queryParameters[] = $key . '=' . $this->_urlencode($value);
        }
        return implode('&', $queryParameters);
    }

    
    private function _sign($data, $key)
    {
        return base64_encode(
            hash_hmac('sha256', $data, $key, true)
        );
    }

    private function _getFormattedTimestamp()
    {
        return gmdate("Y-m-d\TH:i:s.\\0\\0\\0\\Z", time());
    }
}


class SimpleDbResponse {
	
	public $responseCode;
	public $responseData;
	public $xml;
	public $errors;
	public function __construct($code, $xml)
	{
		$this->responseCode = $code;
		$this->xml = new SimpleXMLElement($xml);
		if ($this->responseCode != '200')
		{
			$this->errors = array();
			foreach($this->xml->Errors->children() as $err)
			{
				$ea = array(
					'Code'=>$err->Code,
					'Message'=>$err->Message,
					'BoxUsage'=>$err->BoxUsage
				);
				$this->errors[] = $ea;
			}
		}
		else
		{
			$this->responseData = $this->xml->SelectResult;
		}
	}
}

class SimpleDBAttribute {
	
	private $name;
	private $value;
	private $replace = false;
	
	public function __construct($name=null,$value=null, $replace=false)
	{
		if (isset($name))
		{
			$this->setName($name);
		}
		if (isset($value))
		{
			$this->setValue($value);
		}
		$this->setReplace($replace);
	}
	
	public function getName() {
		return $this->name;
	}
	
	public function getReplace() {
		return $this->replace;
	}
	
	public function getValue() {
		return $this->value;
	}
	
	public function setName($name) {
		$this->name = $name;
	}
	
	public function setReplace($replace) {
		$this->replace = $replace;
	}
	
	public function setValue($value) {
		$this->value = $value;
	}
}

class SimpleDBAttributeCollection implements Iterator {
	private $array = array();
	private $position = 0;
	
	
	public function rewind() {
        $this->position = 0;
    }

    public function current() {
        return $this->array[$this->position];
    }

    public function key() {
        return $this->position;
    }

    public function next() {
        ++$this->position;
    }

    public function valid() {
        return isset($this->array[$this->position]);
    }
    
    public function add(SimpleDBAttribute $attr)
    {
    	array_push($this->array, $attr);
    }
    
    public function getParams()
    {
    	$params = array();
    	$ct = 0;
    	foreach($this as $attr)
    	{
    		$prefix = 'Attribute.' . $ct . '.';
    		
    		$params[$prefix . 'Name'] = $attr->getName();
    		$params[$prefix . 'Value'] = $attr->getValue();
    		if ($attr->getReplace())
    		{
	    		$params[$prefix . 'Replace'] = 'true';	
    		}
    		$ct++;
    	}
    	return $params;
    }
}



?>