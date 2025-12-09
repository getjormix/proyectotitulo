<?php
class PythonClient {
    private $baseUrl;

    public function __construct() {
        $this->baseUrl = PYTHON_API_BASE_URL;
    }

    public function getHealth() {
        return $this->call('GET', '/health');
    }

    public function getPredictions($tenant_id, $date = null) {
        $payload = [
            'tenant_id' => $tenant_id,
            'date' => $date ?: date('Y-m-d')
        ];
        return $this->call('POST', '/predict', $payload);
    }

    public function trainModel($tenant_id, $from_date, $to_date) {
        $payload = [
            'tenant_id' => $tenant_id,
            'from' => $from_date,
            'to' => $to_date
        ];
        return $this->call('POST', '/train', $payload);
    }

    private function call($method, $endpoint, $data = null) {
        $url = $this->baseUrl . $endpoint;
        $curl = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode !== 200) {
            error_log("Python API Error: HTTP $httpCode - $response");
            return null;
        }

        return json_decode($response, true);
    }
}
