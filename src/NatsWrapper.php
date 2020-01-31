<?php

namespace WebDevil\NatsWrapper;

use Exception;
use Log;
use Closure;
use Nats\Connection;
use Nats\ConnectionOptions;

class NatsWrapper
{

    /**
     * @const NATS_DEFAULT_SERVER string
     */
    const NATS_DEFAULT_SERVER = '127.0.0.1';

    /**
     * @const NATS_DEFAULT_POST string
     */
    const NATS_DEFAULT_POST = '4222';

    /**
     * @var null
     */
    private $connect = null;

    /**
     * @var string
     */
    private $natsServer = null;

    /**
     * @var string
     */
    private $natsPort = null;

    /**
     * @var string
     */
    private $natsUser = null;

    /**
     * @var string
     */
    private $natsPass = null;

    /**
     * @var null
     */
    private $response = null;

    /**
     * @param string $natsServer
     * @return NatsWrapper
     */
    public function setNatsServer(string $natsServer): NatsWrapper
    {
        $this->natsServer = $natsServer;

        return $this;
    }

    /**
     * @param string $natsPort
     * @return NatsWrapper
     */
    public function setNatsPort(string $natsPort): NatsWrapper
    {
        $this->natsPort = $natsPort;

        return $this;
    }

    /**
     * @param string $natsUser
     * @return NatsWrapper
     */
    public function setNatsUser(string $natsUser): NatsWrapper
    {
        $this->natsUser = $natsUser;

        return $this;
    }

    /**
     * @param string $natsPass
     * @return NatsWrapper
     */
    public function setNatsPass(string $natsPass): NatsWrapper
    {
        $this->natsPass = $natsPass;

        return $this;
    }

    /**
     * @return Connection|null
     */
    public function ensureConnect()
    {
        if (env('NATS_ENABLED', false) === true && is_null($this->connect)) {
            // Config NATS connection
            $connectionOptions = new ConnectionOptions();
            $connectionOptions
                // Set NATS server host
                ->setHost(
                    $this->natsServer !== null ?
                        $this->natsServer :
                        env('NATS_HOST', self::NATS_DEFAULT_SERVER)
                )
                // Set NATS server post
                ->setPort(
                    $this->natsPort !== null ?
                        $this->natsPort :
                        env('NATS_PORT', self::NATS_DEFAULT_POST)
                );
            // Set NATS username for auth
            if (($this->natsUser = env('NATS_USER', $this->natsUser)) !== null) {
                $connectionOptions->setUser($this->natsUser);
            }
            // Set NATS pass for auth
            if (($this->natsPass = env('NATS_PASS', $this->natsPass)) !== null) {
                $connectionOptions->setPass($this->natsPass);
            }

            // Connection
            $this->connect = new Connection();
            $this->connect->connect();
        } else {
            $this->connect = null;
        }

        return $this->connect;
    }

    /**
     * @param string $channel
     * @param $data mixed
     * @return string
     * @throws Exception
     */
    public function request(string $channel, $data): string
    {
        if (($connect = $this->ensureConnect()) === null) {
            throw new Exception("No connection with a NATS server");
        }

        $msg = '';
        $connect->request($channel, $data, function ($message) use (&$msg) {
            $this->response = $message;
            $msg = $message->getBody();
        });

        return $msg;
    }

    /**
     * @param string $channel
     * @param $data mixed
     * @param Closure $callback
     * @return null
     * @throws Exception
     */
    public function requestWithCallback(string $channel, $data, Closure $callback)
    {
        if (($connect = $this->ensureConnect()) === null) {
            throw new Exception("No connection with a NATS server");
        }

        $msg = null;
        $connect->request($channel, $data, $callback);

        return $msg;
    }

    /**
     * @param string $channel
     * @param null $data
     * @return mixed
     * @throws Exception
     */
    public function publish(string $channel, $data = null)
    {
        if (($connect = $this->ensureConnect()) === null) {
            throw new Exception("No connection with a NATS server");
        }

        try {
            return $this->client->publish($channel, $this->encode($data));
        } catch (Exception $e) {
            Log::error("Something went wrong submitting data to NATS", $e);
            abort(500, "errors.nats_wrapper_publish_failed");
        }
    }

    /**
     * @param string $channel
     * @param Closure $callback
     * @return string|null
     * @throws Exception
     */
    public function subscribe(string $channel, Closure $callback): ?string
    {
        if (($connect = $this->ensureConnect()) === null) {
            throw new Exception("No connection with a NATS server");
        }

        return $connect->subscribe($channel, $callback);
    }

    /**
     * @param $data
     * @return string
     */
    private function encode($data): string
    {
        if (is_string($data)) {
            return $data;
        }

        return json_encode($data, JSON_NUMERIC_CHECK);
    }

}