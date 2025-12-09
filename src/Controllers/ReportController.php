<?php
class ReportController {
    public function generate($type, $tenant_id) {
        return ["status" => "ok", "type" => $type];
    }
}
