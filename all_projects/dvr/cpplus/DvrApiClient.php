<?php
class DvrApiClient {
    private $ip;
    private $port;
    private $user;
    private $pass;

    public function __construct($ip, $port, $user, $pass) {
        $this->ip = $ip;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
    }

    public function call($endpoint, $query = []) {
        $url = $this->buildUrl($endpoint, $query);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        curl_setopt($ch, CURLOPT_USERPWD, $this->user . ':' . $this->pass);
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http_code == 200) {
            $data = [];
            $lines = explode("\n", trim($result));
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $data[trim($key)] = trim($value);
                }
            }
            return $data;
        }
        return null;
    }

    public function getCurlHandle($endpoint, $query = []) {
        $url = $this->buildUrl($endpoint, $query);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        curl_setopt($ch, CURLOPT_USERPWD, $this->user . ':' . $this->pass);
        return $ch;
    }

    private function buildUrl($endpoint, $query) {
        $url = "http://{$this->ip}:{$this->port}/$endpoint";
        if (!empty($query)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
        }
        return $url;
    }
}
