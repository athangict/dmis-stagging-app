<?php

	if (isset($_SERVER['HTTP_ORIGIN'])) {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');    // cache for 1 day
    }

	require_once('SwaggerClient-php/vendor/autoload.php');

	$token_url = "https://stg-sso.dit.gov.bt/oauth2/token";

	$test_api_url = "https://staging-datahub-apim.dit.gov.bt/dcrc_citizen_details_api/1.0.0/citizendetails/{cid}";
	


	//	client (application) credentials on stg-api.gov.bt
	$client_id = "eFNTXeORINYvAbJiNm_b0byGfiIa";
	$client_secret = "0Rmf6PyI_PaUn05d4NYaUrf2Y00a";

	$access_token = getAccessToken();
	//$access_token = "86988f14-d26f-3dbf-9dc0-2a67561f8bb9";

	//$resource = getResource($access_token);
	//echo $resource
	//echo $access_token." Dynamically generated";
	//11:49 AM (19-05-2018) -> 662684a4-9724-3e2b-a0de-1b0c7c9224f3
	//12:06 AM (20-05-2018) -> 47ceb94a-3963-3aa9-bf8c-295394d05aed

	//echo "<br /><br />";

	//	step A, B - single call with client credentials as the basic auth header will return access_token
	function getAccessToken() {
		global $token_url, $client_id, $client_secret;

		$content = "grant_type=client_credentials";
		$authorization = base64_encode("$client_id:$client_secret");
		$header = array("Authorization: Basic {$authorization}","Content-Type: application/x-www-form-urlencoded");

		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => $token_url,
			CURLOPT_HTTPHEADER => $header,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $content
		));
		$response = curl_exec($curl);
		curl_close($curl);

		return json_decode($response)->access_token;
	}

	$config = Swagger\Client\Configuration::getDefaultConfiguration()->setAccessToken($access_token);
	$config->setHost($test_api_url);

	$apiInstance = new Swagger\Client\Api\DefaultApi(
		new GuzzleHttp\Client(['verify' => false]),
		$config
	);
	$cid = $_GET['cid'];//"10906000331"; 


	try {
		$result1 = $apiInstance->citizendetailsCidGet($cid);

		$data1 = json_decode($result1, true);

		foreach ($data1['citizenDetailsResponse'] as $cDetails1){
			 $data = $cDetails1;
		}

//		echo "CID = ".$cDetail1[0]['cid'];
//		echo "DOb = ".$cDetail1[0]['dob'];

      // Return data as JSON
      echo json_encode($data);


	} catch (Exception $e) {
		echo 'Exception when calling DefaultApi->citizendetailsCidGet: ', $e->getMessage(), PHP_EOL;
   }

?>

