<?php
namespace Services;

class PythonClient {
    private $baseUrl;
    
    public function __construct() {
        $this->baseUrl = 'http://api.getjornix.com';
    }
    
    public function getHealth() {
        return $this->call('GET', '/health');
    }
    
    public function getPredictions($tenantId, $days = 30) {
        return $this->call('GET', "/predict/risk?tenant_id={$tenantId}&days={$days}");
    }
    
    public function trainModel($tenantId, $days = 90) {
        return $this->call('POST', "/train/model?tenant_id={$tenantId}&days={$days}");
    }
    
    public function getAttendanceData($tenantId, $days = 7) {
        return $this->call('GET', "/data/attendance?tenant_id={$tenantId}&days={$days}");
    }
    
    private function call($method, $endpoint) {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            return ['success' => false, 'error' => 'API connection failed'];
        }
        
        $data = json_decode($response, true);
        $data['http_code'] = $httpCode;
        
        return $data;
    }
}
