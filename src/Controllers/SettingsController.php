<?php
class SettingsController {
    public function getSettings($tenant_id) {
        return ["tenant_id" => $tenant_id];
    }
}
