<?php

/**
 * A PHP5 class for invalidating Amazon CloudFront objects via its API.
 */

require_once 'HTTP/Request2.php';  // grab with "pear install --onlyreqdeps HTTP_Request2"


class CloudFront {

	private $serviceUrl;
	private $accessKeyId;
	private $responseCode;
	private $distributionId;
    private $responseMessage;


	/**
	 * Constructs a CloudFront object and assigns required account values
	 * @param $accessKeyId		{String} AWS access key id
	 * @param $secretKey		{String} AWS secret key
	 * @param $distributionId	{String} CloudFront distribution id
	 * @param $serviceUrl 		{String} Optional parameter for overriding cloudfront api URL
	 */
	function __construct($accessKeyId, $secretKey, $distributionId, $serviceUrl="https://cloudfront.amazonaws.com/"){
		$this->accessKeyId    = $accessKeyId;
		$this->secretKey      = $secretKey;
		$this->distributionId = $distributionId;
		$this->serviceUrl     = $serviceUrl;
	}


    /**
     * Invalidates object with passed key on CloudFront
     * @param $keys
     * @return bool|string
     * @internal param $key {String|Array} Key of object to be invalidated, or set of such keys Key of object to be invalidated, or set of such keys
     */
	function invalidate($keys){
        if (!is_array($keys)) {
            $keys = array($keys);
        }

        $requestUrl = $this->serviceUrl . "2012-07-01/distribution/" . $this->distributionId . "/invalidation";
        // assemble request body
        $body = $this->makeRequestBody($keys);

        // make and send request
        $req = new HTTP_Request2($requestUrl, HTTP_Request2::METHOD_POST, array('ssl_verify_peer' => false));
        $this->setRequestHeaders($req);
        $req->setBody($body);

        try {
            $response = $req->send();
            $this->responseCode = $response->getStatus();

            switch ($this->responseCode) {
                case 201:
                    $this->responseMessage = '201: Request accepted';
                    return true;
                case 400:
                    $this->responseMessage = '400: Too many invalidations in progress. Retry in some time';
                    return false;
                case 403:
                    $this->responseMessage = '403: Forbidden. Please check your security settings.';
                    return false;
                default:
                    $this->responseMessage = $response->getStatus() . ': ' . $response->getReasonPhrase();
                    return false;
            }
        } catch (HTTP_Request2_Exception $e) {
            $this->responseMessage = 'Error: ' . $e->getMessage();
            return false;
        }
	}


    /**
     * Sets the common headers required by CloudFront API
     * @param HTTP_Request2 $req
     */
    private function setRequestHeaders(HTTP_Request2 $req)
    {
        $date = gmdate("D, d M Y G:i:s T");
        $req->setHeader("Host", 'cloudfront.amazonaws.com');
        $req->setHeader("Date", $date);
        $req->setHeader("Authorization", $this->generateAuthKey($date));
        $req->setHeader("Content-Type", "text/xml");
    }

    /**
     * Makes the request body as expected by CloudFront API
     * @param $objects objects to Invalidate
     * @return string
     */
    private function makeRequestBody($objects)
    {
        $body = '<?xml version="1.0" encoding="UTF-8"?>';
        $body .= '<InvalidationBatch xmlns="http://cloudfront.amazonaws.com/doc/2012-07-01/">';
        $body .= '<Paths>';
        $body .= '<Quantity>' . count($objects) . '</Quantity>';
        $body .= '<Items>';
        foreach ($objects as $object) {
            $object = (preg_match("/^\//", $object)) ? $object : "/" . $object;
            $body .= "<Path>" . $object . "</Path>";
        }
        $body .= '</Items>';
        $body .= '</Paths>';
        $body .= "<CallerReference>" . time() . "</CallerReference>";
        $body .= "</InvalidationBatch>";
        return $body;
    }


    /**
     * Returns header string containing encoded authentication key
     * @param $date
     * @return string
     */
    private function generateAuthKey($date)
    {
        $signature = base64_encode(hash_hmac('sha1', $date, $this->secretKey, true));
        return "AWS " . $this->accessKeyId . ":" . $signature;
    }

    /**
     * Getter method for API response codes
     * @return mixed
     */
    public function getResponseCode()
    {
        return $this->responseCode;
    }

    /**
     * Getter method to get the response messages generated by our class.
     * @return mixed
     */
    public function getResponseMessage()
    {
        return $this->responseMessage;
    }

}
?>
