<?php

namespace Buffer\App;


  class BufferApp {

    private $client_id;
    private $client_secret;
    private $code;
    private $access_token;

    private $callback_url;
    private $authorize_url = 'https://bufferapp.com/oauth2/authorize';
    private $access_token_url = 'https://api.bufferapp.com/1/oauth2/token.json';
    private $buffer_url = 'https://api.bufferapp.com/1';

    public $ok = false;

    /**
     * All of the endpoints available in the API
     * @var [type]
     */
    private $endpoints = [
      '/user' => 'get',

      '/profiles' => 'get',
      '/profiles/:id/schedules/update' => 'post', // Array schedules [0][days][]=mon, [0][times][]=12:00
      '/profiles/:id/updates/reorder' => 'post',  // Array order, int offset, bool utc
      '/profiles/:id/updates/pending' => 'get',
      '/profiles/:id/updates/sent' => 'get',
      '/profiles/:id/schedules' => 'get',
      '/profiles/:id' => 'get',

      '/updates/:id/update' => 'post',   // String text, Bool now, Array media ['link'], ['description'], ['picture'], Bool utc
      '/updates/create' => 'post',       // String text, Array profile_ids, Aool shorten, Bool now, Array media ['link'], ['description'], ['picture']
      '/updates/:id/destroy' => 'post',
      '/updates/:id' => 'get',

      '/links/shares' => 'get',
    ];

    /**
     * All of the possible error types
     * @var array
     */
    public $errors = [
      'invalid-endpoint' => 'The endpoint you supplied does not appear to be valid.',
      '400' => 'Required parameter missing.',
      '403' => 'Permission denied.',
      '404' => 'Endpoint not found.',
      '405' => 'Method not allowed.',
      '1000' => 'An unknown error occurred.',
      '1001' => 'Access token required.',
      '1002' => 'Not within application scope.',
      '1003' => 'Parameter not recognized.',
      '1004' => 'Required parameter missing.',
      '1005' => 'Unsupported response format.',
      '1010' => 'Profile could not be found.',
      '1011' => 'No authorization to access profile.',
      '1012' => 'Profile did not save successfully.',
      '1013' => 'Profile schedule limit reached.',
      '1014' => 'Profile limit for user has been reached.',
      '1020' => 'Update could not be found.',
      '1021' => 'No authorization to access update.',
      '1022' => 'Update did not save successfully.',
      '1023' => 'Update limit for profile has been reached.',
      '1024' => 'Update limit for team profile has been reached.',
      '1028' => 'Update soft limit for profile reached.',
      '1030' => 'Media filetype not supported.',
      '1031' => 'Media filesize out of acceptable range.',
    ];

    /**
     * All of the possible response types
     * @var array
     */
    public $responses = [
      '403' => 'Permission denied.',
      '404' => 'Endpoint not found.',
      '405' => 'Method not allowed.',
      '500' => 'An unknown error occurred.',
      '403' => 'Access token required.',
      '403' => 'Not within application scope.',
      '400' => 'Parameter not recognized.',
      '400' => 'Required parameter missing.',
      '406' => 'Unsupported response format.',
      '404' => 'Profile could not be found.',
      '403' => 'No authorization to access profile.',
      '400' => 'Profile did not save successfully.',
      '403' => 'Profile schedule limit reached.',
      '403' => 'Profile limit for user has been reached.',
      '404' => 'Update could not be found.',
      '403' => 'No authorization to access update.',
      '400' => 'Update did not save successfully.',
      '403' => 'Update limit for profile has been reached.',
      '403' => 'Update limit for team profile has been reached.',
      '403' => 'Update soft limit for profile reached.',
      '400' => 'Media filetype not supported.',
      '400' => 'Media filesize out of acceptable range.',
    ];

    /**
     * Construct the BufferApp Instace
     * @param string $client_id     Client ID
     * @param string $client_secret Client Secret
     * @param string $callback_url  Callback URL
     */
    public function __construct($client_id = '', $client_secret = '', $callback_url = '')
    {
      if (isset($client_id)) {
        $this->set_client_id($client_id);
      }

      if (isset($client_secret)) {
        $this->set_client_secret($client_secret);
      }

      if (isset($callback_url)) {
        $this->set_callback_url($callback_url);
      }

      if (isset($_GET['code']) && !empty($_GET['code'])) {
        $code = $_GET['code'];
      }

      if (isset($code)) {
        $this->code = $code;
        $this->create_access_token_url();
      }

      $this->retrieve_access_token();
    }

    /**
     * Make sure the API call is available
     * @param  string $endpoint Endpoint to target
     * @param  string $data     The data to be passed
     * @return [type]           TODO: Fill in
     */
    public function go($endpoint = '', $data = '')
    {
      if (in_array($endpoint, array_keys($this->endpoints))) {
        $done_endpoint = $endpoint;
      } else {
        $ok = false;

        foreach (array_keys($this->endpoints) as $done_endpoint) {
          if (preg_match('/' . preg_replace('/(\:\w+)/i', '(\w+)', str_replace('/', '\/', $done_endpoint)) . '/i', $endpoint, $match)) {
            $ok = true;
            break;
          }
        }

        if (!$ok) return $this->error('invalid-endpoint');
      }

      if (!$data || !is_array($data)) $data = [];
      $data['access_token'] = $this->access_token;

      $method = $this->endpoints[$done_endpoint]; //get() or post()
      return $this->$method($this->buffer_url . $endpoint . '.json', $data);
    }

    /**
     * Save the access token
     * @return void
     */
    public function store_access_token()
    {
      $_SESSION['oauth']['buffer']['access_token'] = $this->access_token;
    }

    /**
     * Retreive the access token
     * @return void
     */
    public function retrieve_access_token()
    {
      if (isset($_SESSION['oauth']['buffer']['access_token'])) {
                $this->access_token = $_SESSION['oauth']['buffer']['access_token'];
      }

      if ($this->access_token) {
        $this->ok = true;
      }
    }

    /**
     * Return an error object
     * @param  error $error Error entry
     * @return object       Error entry
     */
    public function error($error)
    {
      return (object) ['error' => $this->errors[$error]];
    }

    /**
     * Create the access token URL
     * @return void
     */
    public function create_access_token_url()
    {
      $data = [
        'client_id' => $this->client_id,
        'client_secret' => $this->client_secret,
        'redirect_uri' => $this->callback_url,
        'code' => $this->code,
        'grant_type' => 'authorization_code',
      ];

      $obj = $this->post($this->access_token_url, $data);
      $this->access_token = $obj->access_token;

      $this->store_access_token();
    }

    /**
     * Create the request
     * @param  string  $url  URL to make an API call to
     * @param  string  $data Data to past
     * @param  boolean $post Whether this is a POST request
     * @return object        Response data
     */
    public function req($url = '', $data = '', $post = true)
    {
      if (!$url) return false;
      if (!$data || !is_array($data)) $data = [];

      $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => false];

      if ($post) {
        $options += [
          CURLOPT_POST => $post,
          CURLOPT_POSTFIELDS => $data
        ];
      } else {
        $url .= '?' . http_build_query($data);
      }

      $ch = curl_init($url);

      curl_setopt_array($ch, $options);
      $rs = curl_exec($ch);

      $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      if ($code >= 400) {
        return $this->error($code);
      }

      return json_decode($rs);
    }

    /**
     * Make a GET request
     * @param  string $url  Target URL
     * @param  string $data Data
     * @return request
     */
    public function get($url = '', $data = '')
    {
      return $this->req($url, $data, false);
    }

    /**
     * Make a POST request
     * @param  string $url  Target URL
     * @param  string $data Data
     * @return request
     */
    public function post($url = '', $data = '')
    {
      return $this->req($url, $data, true);
    }

    /**
     * Get the login URL
     * @return string login URL
     */
    public function get_login_url()
    {
      return $this->authorize_url . '?'
        . 'client_id=' . $this->client_id
        . '&redirect_uri=' . urlencode($this->callback_url)
        . '&response_type=code';
    }

    /**
     * Set the Client ID
     * @param string $client_id Client ID
     */
    public function set_client_id($client_id)
    {
      $this->client_id = $client_id;
    }

    /**
     * Set the Client Secret
     * @param string $client_secret Client Secret
     */
    public function set_client_secret($client_secret)
    {
      $this->client_secret = $client_secret;
    }

    /**
     * Set the callback URL
     * @param string $callback_url Callback URL
     */
    public function set_callback_url($callback_url) {
      $this->callback_url = $callback_url;
    }
  }
