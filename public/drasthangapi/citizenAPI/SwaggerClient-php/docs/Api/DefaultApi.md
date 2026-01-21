# Swagger\Client\DefaultApi

All URIs are relative to *https://localhost*

Method | HTTP request | Description
------------- | ------------- | -------------
[**childrendetailsCidGet**](DefaultApi.md#childrendetailsCidGet) | **GET** /childrendetails/{cid} | 
[**cidstatusCidGet**](DefaultApi.md#cidstatusCidGet) | **GET** /cidstatus/{cid} | 
[**citizendetailsCidGet**](DefaultApi.md#citizendetailsCidGet) | **GET** /citizendetails/{cid} | 
[**contactdetailsCidGet**](DefaultApi.md#contactdetailsCidGet) | **GET** /contactdetails/{cid} | 
[**healthcheckGet**](DefaultApi.md#healthcheckGet) | **GET** /healthcheck | 
[**householdingdetailsHouseholdnoGet**](DefaultApi.md#householdingdetailsHouseholdnoGet) | **GET** /householdingdetails/{householdno} | 
[**parentdetailsCidGet**](DefaultApi.md#parentdetailsCidGet) | **GET** /parentdetails/{cid} | 
[**presentaddressCidGet**](DefaultApi.md#presentaddressCidGet) | **GET** /presentaddress/{cid} | 
[**spousedetailsCidGet**](DefaultApi.md#spousedetailsCidGet) | **GET** /spousedetails/{cid} | 


# **childrendetailsCidGet**
> \Swagger\Client\Model\ChildrenDetailsResponse childrendetailsCidGet($cid)



### Example
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

// Configure OAuth2 access token for authorization: OAuth2
$config = Swagger\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');

$apiInstance = new Swagger\Client\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$cid = "cid_example"; // string | Citizen ID

try {
    $result = $apiInstance->childrendetailsCidGet($cid);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->childrendetailsCidGet: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **cid** | **string**| Citizen ID |

### Return type

[**\Swagger\Client\Model\ChildrenDetailsResponse**](../Model/ChildrenDetailsResponse.md)

### Authorization

[OAuth2](../../README.md#OAuth2)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

# **cidstatusCidGet**
> \Swagger\Client\Model\CidstatusResponse cidstatusCidGet($cid)



### Example
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

// Configure OAuth2 access token for authorization: OAuth2
$config = Swagger\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');

$apiInstance = new Swagger\Client\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$cid = "cid_example"; // string | Citizen ID

try {
    $result = $apiInstance->cidstatusCidGet($cid);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->cidstatusCidGet: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **cid** | **string**| Citizen ID |

### Return type

[**\Swagger\Client\Model\CidstatusResponse**](../Model/CidstatusResponse.md)

### Authorization

[OAuth2](../../README.md#OAuth2)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

# **citizendetailsCidGet**
> \Swagger\Client\Model\CitizenDetailsResponse citizendetailsCidGet($cid)



### Example
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

// Configure OAuth2 access token for authorization: OAuth2
$config = Swagger\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');

$apiInstance = new Swagger\Client\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$cid = "cid_example"; // string | Citizen ID

try {
    $result = $apiInstance->citizendetailsCidGet($cid);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->citizendetailsCidGet: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **cid** | **string**| Citizen ID |

### Return type

[**\Swagger\Client\Model\CitizenDetailsResponse**](../Model/CitizenDetailsResponse.md)

### Authorization

[OAuth2](../../README.md#OAuth2)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

# **contactdetailsCidGet**
> \Swagger\Client\Model\ContactdetailsResponse contactdetailsCidGet($cid)



### Example
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

// Configure OAuth2 access token for authorization: OAuth2
$config = Swagger\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');

$apiInstance = new Swagger\Client\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$cid = "cid_example"; // string | Citizen ID

try {
    $result = $apiInstance->contactdetailsCidGet($cid);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->contactdetailsCidGet: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **cid** | **string**| Citizen ID |

### Return type

[**\Swagger\Client\Model\ContactdetailsResponse**](../Model/ContactdetailsResponse.md)

### Authorization

[OAuth2](../../README.md#OAuth2)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

# **healthcheckGet**
> healthcheckGet()



### Example
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

$apiInstance = new Swagger\Client\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client()
);

try {
    $apiInstance->healthcheckGet();
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->healthcheckGet: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters
This endpoint does not need any parameter.

### Return type

void (empty response body)

### Authorization

No authorization required

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: Not defined

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

# **householdingdetailsHouseholdnoGet**
> \Swagger\Client\Model\HouseholddetailsResponse householdingdetailsHouseholdnoGet($householdno)



### Example
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

// Configure OAuth2 access token for authorization: OAuth2
$config = Swagger\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');

$apiInstance = new Swagger\Client\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$householdno = "householdno_example"; // string | Household No

try {
    $result = $apiInstance->householdingdetailsHouseholdnoGet($householdno);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->householdingdetailsHouseholdnoGet: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **householdno** | **string**| Household No |

### Return type

[**\Swagger\Client\Model\HouseholddetailsResponse**](../Model/HouseholddetailsResponse.md)

### Authorization

[OAuth2](../../README.md#OAuth2)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

# **parentdetailsCidGet**
> \Swagger\Client\Model\ParentdetailResponse parentdetailsCidGet($cid)



### Example
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

// Configure OAuth2 access token for authorization: OAuth2
$config = Swagger\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');

$apiInstance = new Swagger\Client\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$cid = "cid_example"; // string | Citizen ID

try {
    $result = $apiInstance->parentdetailsCidGet($cid);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->parentdetailsCidGet: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **cid** | **string**| Citizen ID |

### Return type

[**\Swagger\Client\Model\ParentdetailResponse**](../Model/ParentdetailResponse.md)

### Authorization

[OAuth2](../../README.md#OAuth2)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

# **presentaddressCidGet**
> \Swagger\Client\Model\PresentaddressResponse presentaddressCidGet($cid)



### Example
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

// Configure OAuth2 access token for authorization: OAuth2
$config = Swagger\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');

$apiInstance = new Swagger\Client\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$cid = "cid_example"; // string | Citizen ID

try {
    $result = $apiInstance->presentaddressCidGet($cid);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->presentaddressCidGet: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **cid** | **string**| Citizen ID |

### Return type

[**\Swagger\Client\Model\PresentaddressResponse**](../Model/PresentaddressResponse.md)

### Authorization

[OAuth2](../../README.md#OAuth2)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

# **spousedetailsCidGet**
> \Swagger\Client\Model\SpousedetailsResponse spousedetailsCidGet($cid)



### Example
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

// Configure OAuth2 access token for authorization: OAuth2
$config = Swagger\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');

$apiInstance = new Swagger\Client\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$cid = "cid_example"; // string | Citizen ID

try {
    $result = $apiInstance->spousedetailsCidGet($cid);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->spousedetailsCidGet: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **cid** | **string**| Citizen ID |

### Return type

[**\Swagger\Client\Model\SpousedetailsResponse**](../Model/SpousedetailsResponse.md)

### Authorization

[OAuth2](../../README.md#OAuth2)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

