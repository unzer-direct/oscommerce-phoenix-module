<?php
/**
 * osCommerce Phoenix, Open Source E-Commerce Solutions
 * http://www.oscommerce.com
 *
 * Copyright (c) 2020 osCommerce
 *
 * Released under the GNU General Public License
 *
 * author: Genuineq office@genuineq.com
 */

/**
 * Unzer v10+ php library
 *
 * This class implements the Unzer connector interface, by using curl.
 * If curl is not present in the environment an execption is thrown on instantiation.
 */
class UnzerConnectorCurl implements UnzerConnectorInterface {
    protected $connTimeout = 10;
    protected $apiUrl = "https://api.unzerdirect.com";
    protected $apiVersion = 'v10';
    protected $apiKey = "";
    protected $format = "application/json";

    public function __constructor() {
        if (!function_exists('curl_init')){
            throw Exception('CURL is not installed, please install curl or change connection method');
        }
    }

    public function setOptions($apiKey, $connTimeout=10, $apiVersion="v10") {
       $this->connTimeout = $connTimeout;
       $this->apiKey = $apiKey;
       $this->apiVersion = $apiVersion;
    }

    public function request($resource, $requestData = null, $sendmode='GET-POST') {
        $curl =  curl_init();
        $url = $this->apiUrl . "/" . $resource;
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->connTimeout);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Authorization: Basic ' . base64_encode(":" . $this->apiKey),
            'Accept-Version: ' . $this->apiVersion,
            'Accept: ' . $this->format
        ));

        if (!is_null($requestData)) {
            if($sendmode == 'GET-POST'){
                curl_setopt($curl, CURLOPT_POST, 1);
            }else{
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
            }
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($requestData));
            // Build a string query based on the form array values
            $requestFormData = preg_replace('/%5B[0-9]+%5D/simU', '%5B%5D', http_build_query($requestData, '', '&'));

            // Prepare to post the data string
            curl_setopt($curl, CURLOPT_POSTFIELDS, $requestFormData);
        }

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode!=200 && $httpCode!=202) {
        //  throw new Exception($response, $httpCode);
        }

        return $response;
    }
}
?>
