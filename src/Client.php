<?php

namespace ThreadMeUp\Slack;

use \InvalidArgumentException;
use Guzzle\Http\Client as GuzzleClient;
use ThreadMeUp\Slack\Webhooks\Incoming as IncomingWebhook;

class Client {

    const CLIENT_NAME = 'Slack-SDK';
    const CLIENT_VERSION = '1.1.0';
    const CLIENT_URL = 'https://github.com/threadmeup/slack-sdk';
    const API_URL = 'https://slack.com/api';
    const DEFAULT_CHANNEL = '#random';
    public $config = array();
    public $client;
    /**
     * Debug should be turned off by default
     * And should not be available publicly
     * @var bool
     */
    protected $debug = false;

    /**
     * @param array $config
     * @throws InvalidArgumentException
     */
    public function __construct(array $config = array())
    {
        // set config
        $this->setConfig($config);

        // init client
        $this->client = new GuzzleClient(self::API_URL);
        $this->client->setUserAgent($this->setUserAgent());
    }

    /**
     * @return string
     */
    public function setUserAgent()
    {
        return self::CLIENT_NAME.'/'.self::CLIENT_VERSION.' (+'.self::CLIENT_URL.')';
    }

    /**
     * @param bool $debug
     * @return $this
     */
    public function setDebug($debug = false)
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * @param array $config
     * @throws InvalidArgumentException
     * @return $this
     */
    public function setConfig(array $config = array())
    {
        // can't proceed without token & username
        if (!isset($config['token']) || !isset($config['username'])) {
            throw new InvalidArgumentException("No token/user found.");
        }

        // icons passed?
        if (isset($config['icon']) && !empty($config['icon'])) {
            $startsWithHttp = strpos($config['icon'], 'http') === 0;
            $iconUrl = $startsWithHttp ? $config['icon'] : null;
            $iconEmoji = !$startsWithHttp ? $config['icon'] : null;
        }
        // set defaults
        else {
            $iconUrl = null;
            $iconEmoji = null;
        }

        // prepare config
        $this->config = array(
            'token' => $config['token'],
            'username' => $config['username'],
            // add prepared icons
            'icon_url' => $iconUrl,
            'icon_emoji' => $iconEmoji,
            // if not passed - disable
            'parse' => isset($config['parse']) ? $config['parse'] : false
        );

        return $this;
    }

    /**
     * @param mixed $keys
     * @return array
     */
    public function getConfig($keys = null)
    {
        if (!is_null($keys) && is_array($keys))
        {
            $config = array();
            foreach ($this->config as $key => $value)
            {
                if (in_array($key, $keys))
                {
                    $config[$key] = $value;
                }
            }
            return $config;
        }
        return $this->config;
    }

    /**
     * @param null $endpoint
     * @param array $query
     * @return \Guzzle\Http\Message\RequestInterface
     */
    public function request($endpoint = null, array $query = array())
    {
        return $this->client->get($endpoint, array(), array('query' => $query), array('debug' => $this->debug));
    }

    /**
     * @param bool $simulate
     * @return $this|bool|IncomingWebhook
     */
    public function listen($simulate = false)
    {
        if (empty($_POST) && !$simulate) return false;
        $hook = new IncomingWebhook($this);
        // single return statement is better then 2
        return (is_array($simulate)) ? $hook->simulatePayload($simulate) : $hook;
    }

    /**
     * @param string $channel
     * @return Chat
     */
    public function chat($channel = self::DEFAULT_CHANNEL)
    {
        return new Chat($this, $channel);
    }

    /**
     * @return array
     */
    public function users()
    {
        $query = $this->getConfig(['token']);
        $response = $this->request('users.list', $query)->send()->json();
        $users = array();
        foreach ($response['members'] as $member)
        {
            $users[] = new User($member);
        }
        return $users;
    }
}
