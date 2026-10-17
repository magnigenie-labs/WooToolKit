<?php
class WTMailChimp
{
    private $api_key;
    private $api_endpoint = 'https://<dc>.api.mailchimp.com/3.0';
    public $verify_ssl = true;

    private $request_successful = false;
    private $last_error         = '';
    private $last_response      = array();
    private $last_request       = array();

    /**
     * Create a new instance
     * @param string $api_key Your MailChimp API key
     * @throws \Exception
     */
    public function __construct($api_key)
    {
        $this->api_key = $api_key;

        if (strpos($this->api_key, '-') === false) {
            throw new \Exception( "Invalid MailChimp API key supplied (expected the key-datacenter format, e.g. ...-us6)." );
        }

        list(, $data_center) = explode('-', $this->api_key);
        $this->api_endpoint  = str_replace('<dc>', $data_center, $this->api_endpoint);

        $this->last_response = array('headers' => null, 'body' => null);
    }

    /**
     * Create a new instance of a Batch request. Optionally with the ID of an existing batch.
     * @param string $batch_id Optional ID of an existing batch, if you need to check its status for example.
     * @return Batch            New Batch object.
     */
    public function new_batch($batch_id = null)
    {
        return new Batch($this, $batch_id);
    }

    /**
     * Convert an email address into a 'subscriber hash' for identifying the subscriber in a method URL
     * @param   string $email The subscriber's email address
     * @return  string          Hashed version of the input
     */
    public function subscriberHash($email)
    {
        return md5(strtolower($email));
    }

    /**
     * Was the last request successful?
     * @return bool  True for success, false for failure
     */
    public function success()
    {
        return $this->request_successful;
    }

    /**
     * Get the last error returned by either the network transport, or by the API.
     * If something didn't work, this should contain the string describing the problem.
     * @return  array|false  describing the error
     */
    public function getLastError()
    {
        return $this->last_error ?: false;
    }

    /**
     * Get an array containing the HTTP headers and the body of the API response.
     * @return array  Assoc array with keys 'headers' and 'body'
     */
    public function getLastResponse()
    {
        return $this->last_response;
    }

    /**
     * Get an array containing the HTTP headers and the body of the API request.
     * @return array  Assoc array
     */
    public function getLastRequest()
    {
        return $this->last_request;
    }

    /**
     * Make an HTTP DELETE request - for deleting data
     * @param   string $method URL of the API request method
     * @param   array $args Assoc array of arguments (if any)
     * @param   int $timeout Timeout limit for request in seconds
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
    public function delete($method, $args = array(), $timeout = 10)
    {
        return $this->makeRequest('delete', $method, $args, $timeout);
    }

    /**
     * Make an HTTP GET request - for retrieving data
     * @param   string $method URL of the API request method
     * @param   array $args Assoc array of arguments (usually your data)
     * @param   int $timeout Timeout limit for request in seconds
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
    public function get($method, $args = array(), $timeout = 10)
    {
        return $this->makeRequest('get', $method, $args, $timeout);
    }

    /**
     * Make an HTTP PATCH request - for performing partial updates
     * @param   string $method URL of the API request method
     * @param   array $args Assoc array of arguments (usually your data)
     * @param   int $timeout Timeout limit for request in seconds
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
    public function patch($method, $args = array(), $timeout = 10)
    {
        return $this->makeRequest('patch', $method, $args, $timeout);
    }

    /**
     * Make an HTTP POST request - for creating and updating items
     * @param   string $method URL of the API request method
     * @param   array $args Assoc array of arguments (usually your data)
     * @param   int $timeout Timeout limit for request in seconds
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
    public function post($method, $args = array(), $timeout = 10)
    {
        return $this->makeRequest('post', $method, $args, $timeout);
    }

    /**
     * Make an HTTP PUT request - for creating new items
     * @param   string $method URL of the API request method
     * @param   array $args Assoc array of arguments (usually your data)
     * @param   int $timeout Timeout limit for request in seconds
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
    public function put($method, $args = array(), $timeout = 10)
    {
        return $this->makeRequest('put', $method, $args, $timeout);
    }

    /**
     * Performs the underlying HTTP request. Not very exciting.
     * @param  string $http_verb The HTTP verb to use: get, post, put, patch, delete
     * @param  string $method The API method to be called
     * @param  array $args Assoc array of parameters to be passed
     * @param int $timeout
     * @return array|false Assoc array of decoded result
     */
    private function makeRequest($http_verb, $method, $args = array(), $timeout = 10)
    {
        $url = $this->api_endpoint . '/' . $method;

        $this->last_error         = '';
        $this->request_successful = false;
        $response                 = array('headers' => null, 'body' => null);
        $this->last_response      = $response;

        $this->last_request = array(
            'method'  => $http_verb,
            'path'    => $method,
            'url'     => $url,
            'body'    => '',
            'timeout' => $timeout,
        );

        // WordPress HTTP API rather than cURL (WordPress.org plugin guidelines).
        $request = array(
            'method'    => strtoupper($http_verb),
            'timeout'   => $timeout,
            'sslverify' => $this->verify_ssl,
            'headers'   => array(
                'Accept'        => 'application/vnd.api+json',
                'Content-Type'  => 'application/vnd.api+json',
                'Authorization' => 'apikey ' . $this->api_key,
            ),
        );

        if ('get' === $http_verb) {
            if ($args) {
                $url .= '?' . http_build_query($args, '', '&');
            }
        } elseif ('delete' !== $http_verb) {
            $request['body'] = $this->attachRequestPayload($args);
        }

        $result = wp_remote_request($url, $request);

        if (is_wp_error($result)) {
            $this->last_error    = $result->get_error_message();
            $response['body']    = false;
            $response['headers'] = array();
        } else {
            $response['body']    = wp_remote_retrieve_body($result);
            $response['headers'] = array('http_code' => (int) wp_remote_retrieve_response_code($result));
        }

        $formattedResponse = $this->formatResponse($response);

        $this->determineSuccess($response, $formattedResponse);

        return $formattedResponse;
    }

    /**
     * @return string The url to the API endpoint
     */
    public function getApiEndpoint()
    {
        return $this->api_endpoint;
    }

    /**
     * Encode the data for the request body
     * @param   array $data Assoc array of data to attach
     * @return  string JSON body
     */
    private function attachRequestPayload($data)
    {
        $encoded = wp_json_encode($data);
        $this->last_request['body'] = $encoded;
        return $encoded;
    }

    /**
     * Decode the response and format any error messages for debugging
     * @param array $response The response from the curl request
     * @return array|false    The JSON decoded into an array
     */
    private function formatResponse($response)
    {
        $this->last_response = $response;

        if (!empty($response['body'])) {
            return json_decode($response['body'], true);
        }

        return false;
    }

    /**
     * Check if the response was successful or a failure. If it failed, store the error.
     * @param array $response The response from the curl request
     * @param array|false $formattedResponse The response body payload from the curl request
     * @return bool     If the request was successful
     */
    private function determineSuccess($response, $formattedResponse)
    {
        $status = $this->findHTTPStatus($response, $formattedResponse);

        if ($status >= 200 && $status <= 299) {
            $this->request_successful = true;
            return true;
        }

        if (isset($formattedResponse['detail'])) {
            $this->last_error = sprintf('%d: %s', $formattedResponse['status'], $formattedResponse['detail']);
            return false;
        }

        $this->last_error = 'Unknown error, call getLastResponse() to find out what happened.';
        return false;
    }

    /**
     * Find the HTTP status code from the headers or API response body
     * @param array $response The response from the curl request
     * @param array|false $formattedResponse The response body payload from the curl request
     * @return int  HTTP status code
     */
    private function findHTTPStatus($response, $formattedResponse)
    {
        if (!empty($response['headers']) && isset($response['headers']['http_code'])) {
            return (int) $response['headers']['http_code'];
        }

        if (!empty($response['body']) && isset($formattedResponse['status'])) {
            return (int) $formattedResponse['status'];
        }

        return 418;
    }
}