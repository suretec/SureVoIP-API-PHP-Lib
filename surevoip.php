<?php


class SureVoIPAPI  {
  protected $user_agent = "SureVoIP API PHP Lib";

  private $username;
  private $password;
  private $url;

  /**
   * Initialize an instance of a SureVoIpAPI object
   *
   * @param $username
   *   SureVoIPApi username
   * @param $password
   *   SureVoIPApi password
   * @param $url (optional)
   *   SureVoIPApi URL - defaults to https://api.surevoip.co.uk
   */
  function __construct($username, $password, $url = 'https://api.surevoip.co.uk') {
    $this->username = $username;
    $this->password = $password;
    $this->url = $url;
  }


  /**
   * Performs a request to the SureVoIP API
   *
   * @param string $service
   *   The service to perform the request, this should be the URI path after
   *   the domain
   *
   * @param array $data
   *   An associative array with data to be sent as JSON
   *
   * @param array $success
   *   (optional) An array of HTTP codes that should be considered valid and that won't
   *   throw an exception. Defaults to 200 and 201
   *
   * @param string $action
   *   (optional) The type of request to do, defaults to 'POST'
   *
   * @return array
   *     An associative array of the following elements
   *       - 'numbers': array
   *         An associative array of the following elements
   *           - 'location': string The URI of the provisioned number
   *           - 'number': string The provisioned number
   *
   * @exception Exception
   *   In case of an error, and Exception object will be thrown with a
   *   descriptive error message. For HTTP related errors, the HTTP code will
   *   be included in the Exception's code element
   */
  function request($service, $data, $success_http_codes = array(200, 201), $action = 'POST') {

    $data_json = json_encode($data);

    $ch = curl_init($this->url . '/' . $service . '?hypermedia=no');
    curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->password);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $action);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json',
      'Content-Length: ' . strlen($data_json),
    ));

    $result = curl_exec($ch);
    if ($result === FALSE) {
      throw new Exception('Internal error doing the request');
    }
    $response = $this->http_parse_response($result);

    if (!empty($response['Content'])) {
      $response['JSON'] = json_decode($response['Content'], TRUE);
    }

    if (!in_array($response['HTTP']['code'], $success_http_codes)) {
      $error = $response['HTTP']['reason'];

      if (!empty($response['JSON']['error'])) {
        $error .= ' - ' . $response['JSON']['error'];
      }
      else {
        if (!empty($response['JSON']['errors'])) {
          foreach ($response['JSON']['errors'] as $e) {
            $e_str = $e['message'][0];
            if (!empty($e['field'])) {
              $e_str .= ' (field: ' . $e['field'] . ')';
            }
            $errors[] = $e_str;
          }
          $error .= ' - ' . implode(', ', $errors);
        }
      }

      throw new Exception($error, $response['HTTP']['code']);
    }


    return $response;
  }

  /**
   * Parses an HTTP response, extracts both HTTP return code and content as well
   * as each header as part of the returned associative array.
   *
   * @param  string $response The full HTTP response
   * @return array
   *   An associative array with its keys being one of each HTTP
   *   headers, as well as two special elements:
   *   - 'HTTP': An associative array from the HTTP Status-line
   *      of the following elements:
   *     - 'version': HTTP protocol version
   *     - 'code': HTTP return code
   *     - 'reason': HTTP reason phrase
   *   - 'Content': The content of the HTTP response (if any)
   */
  protected function http_parse_response($response) {
      $retVal = array();
      $fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $response));
      foreach ($fields as $field) {

        // Do not process empty cubrid_num_fields(result)
        if (empty($field)) {
          continue;
        }

        if (preg_match('/([^:]+): (.+)/m', $field, $match)) {
          $match[1] = preg_replace('/(?<=^|[\x09\x20\x2D])./e', 'strtoupper("\0")', strtolower(trim($match[1])));
          if( isset($retVal[$match[1]]) ) {
            if (!is_array($retVal[$match[1]])) {
              $retVal[$match[1]] = array($retVal[$match[1]]);
            }
            $retVal[$match[1]][] = $match[2];
          }
          else {
            $retVal[$match[1]] = trim($match[2]);
          }
        }
        else {
          if (preg_match('/HTTP\//', $field)) {
            // Following HTTP standards which are space-separated
            preg_match('/(.*?) (.*?) (.*)/', $field, $matches);
            $retVal['HTTP']['version'] = $matches[1];
            $retVal['HTTP']['code'] = $matches[2];
            $retVal['HTTP']['reason'] = $matches[3];
          }
          else {
            $retVal['Content'] = $field;
          }
        }
      }
      return $retVal;
  }

  /**
   * Validates required elements are present in an associative array
   *
   * @param  array $data
   *   The associative array to check
   * @param  array $args
   *   An array of required keys that should be present in the associative array
   * @param  array $missing_args
   *   An array of the missing keys
   * @return
   *   TRUE if all required elements are present, FALSE otherwise.
   */
  protected function validate_required_args($data, $args, &$missing_args) {
    $ret = TRUE;
    foreach ($args as $arg) {
      if (!isset($data[$arg]) || empty($data[$arg])) {
        $ret = FALSE;
        $missing_args[] = $arg;
      }
    }

    return $ret;
  }

  /**
   * Creates a customer
   *
   * @param array $data
   *   An associative array with the following elements:
   *   - (required) 'firstname'
   *   - (required) 'lastname'
   *   - (required) 'email'
   *   - (required) 'phone'
   *   - (required) 'address'
   *   - (required) 'state'
   *   - (required) 'city'
   *   - (required) 'postcode'
   *   - (required) 'country'
   *   - (required) 'company_website'
   *   - (optional) 'company_name'
   *   - (optional) 'fax'
   *
   * @return array
   *     An associative array of the following elements
   *       - 'uri': string
   *         The URI of the newly created customer
   *       - 'id': integer
   *         The ID of the newly created customer
   *
   * @exception Exception
   *   In case of an error, and Exception object will be thrown with a
   *   descriptive error message. For HTTP related errors, the HTTP code will
   *   be included in the Exception's code element
   */
  function customerCreate($data) {

    $required_args = array(
      'firstname',
      'lastname',
      'email',
      'phone',
      'address',
      'state',
      'city',
      'postcode',
      'country',
      'company_website'
    );

    if (!$this->validate_required_args($data, $required_args, $missing_args)) {
      throw new Exception('The following required data is missing: ' . implode(', ', $missing_args));
    }

    $response = $this->request('customers', $data);

    if (!isset($response['Location'])) {
      throw new Exception('Location header missing', $response['HTTP']['code']);
    }

    $ret['id'] = str_replace($this->url . '/customers/', '', $response['Location']);
    $ret['uri'] = $response['Location'];

    return $ret;
  }

  /**
   * Creates an invoice
   *
   * @param integer $customer
   *   Customer ID on the SureVoIP API obtained from createCustomer()
   *
   * @param array $data
   *   An associative array with the following elements:
   *   - (optional) 'invoice_number':
   *       integer for invoice number, used if present, auto-generated if not
   *   - (optional) 'amount':
   *       Float for invoice total, This marks the invoice as PAID using this amount
   *   - (required) 'line_items'
   *     An associative array of the following elements
   *       - (optional) 'vat_rate':
   *         Override the system default, Float - used if present
   *       - (required) 'quantity':
   *         Number of items in line item, Float
   *       - (required) 'price'
   *         Price of item, Float
   *       - (required) 'description'
   *         Full text, Always enter something
   *       - (optional) 'vat_amount'
   *         Override the system default, Float - used if present
   *
   * @return array
   *     An associative array of the following elements
   *       - 'uri': string
   *         The URI of the newly created customer
   *       - 'id': integer
   *         The ID of the newly created customer
   *
   * @exception Exception
   *   In case of an error, and Exception object will be thrown with a
   *   descriptive error message. For HTTP related errors, the HTTP code will
   *   be included in the Exception's code element
   */
  function invoiceCreate($customer, $data) {

    $required_args = array(
      'line_items',
    );
    $missing_args = array();
    if (!$this->validate_required_args($data, $required_args, $missing_args)) {
      throw new Exception('The following required data is missing: ' . implode(', ', $missing_args));
    }

    $required_args = array(
      'quantity',
      'price',
      'description',
    );

    foreach ($data['line_items'] as $k => $line_item) {
      $missing_args = array();
      if (!$this->validate_required_args($line_item, $required_args, $missing_args)) {
        throw new Exception('The following required data is missing for line item number ' . ($k + 1) . ': ' . implode(', ', $missing_args));
      }
    }

    $response = $this->request('customers/' . $customer . '/billing/invoices', $data);

    if (!isset($response['Location'])) {
      throw new Exception('Location header missing', $response['HTTP']['code']);
    }

    $ret['id'] = str_replace($this->url . '/customers/' . $customer . '/billing/invoices/', '', $response['Location']);
    $ret['uri'] = $response['Location'];

    return $ret;
  }

/**
   * Provisions a number
   *
   * @param integer $customer
   *   Customer ID on the SureVoIP API obtained from createCustomer()
   *
   * @param array $data
   *   An associative array with the following elements:
   *   - (required) 'numbers'
   *     An associative array of the following elements
   *       - (required) 'number':
   *         The number to provision in international format
   *         i.e. 44113xxxxxxx
   *       - (required) 'destination':
   *         The destination of the number, number@destination where number has
   *         only the area code and number and the destination will depend
   *         of the type of provisioning.
   *         i.e. 0113xxxxxxx@office.suretecsystems.com
   *       - (required) 'activated'
   *         Whether the number should be active or not
   *
   * @return array
   *     An associative array of the following elements
   *       - 'numbers': array
   *         An associative array of the following elements
   *           - 'location': string The URI of the provisioned number
   *           - 'number': string The provisioned number
   *
   * @exception Exception
   *   In case of an error, and Exception object will be thrown with a
   *   descriptive error message. For HTTP related errors, the HTTP code will
   *   be included in the Exception's code element
   */
  function numberProvision($customer, $data) {

    $required_args = array(
      'numbers',
    );
    $missing_args = array();
    if (!$this->validate_required_args($data, $required_args, $missing_args)) {
      throw new Exception('The following required data is missing: ' . implode(', ', $missing_args));
    }

    $required_args = array(
      'number',
      'destination',
      'activated',
    );

    foreach ($data['numbers'] as $k => $line_item) {
      $missing_args = array();
      if (!$this->validate_required_args($line_item, $required_args, $missing_args)) {
        throw new Exception('The following required data is missing for line item number ' . ($k + 1) . ': ' . implode(', ', $missing_args));
      }
    }

    $response = $this->request('customers/' . $customer . '/numbers', $data);

    $ret['numbers'] = $response['JSON']['numbers'];

    return $ret;
  }
}

