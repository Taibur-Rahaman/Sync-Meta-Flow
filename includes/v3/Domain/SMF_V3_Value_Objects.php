<?php
defined('ABSPATH') || exit;

final class SMF_V3_Order_Context {
    private $id;
    private $status;
    private $total;
    private $currency;

    public function __construct($id, $status, $total, $currency) {
        $this->id = absint($id);
        $this->status = sanitize_key(strtolower((string) $status));
        $this->total = (float) $total;
        $this->currency = strtoupper(sanitize_text_field($currency));
    }

    public function id() { return $this->id; }
    public function status() { return $this->status; }
    public function total() { return $this->total; }
    public function currency() { return $this->currency; }
}

final class SMF_V3_Attribution_Context {
    private $session_key;
    private $first;
    private $last;

    public function __construct($session_key, array $first = array(), array $last = array()) {
        $this->session_key = preg_match('/^[a-f0-9-]{36}$/', (string) $session_key) ? (string) $session_key : '';
        $this->first = self::normalize($first);
        $this->last = self::normalize($last);
    }

    private static function normalize(array $touch) {
        $allowed = array('campaign_id', 'campaign_name', 'adset_id', 'ad_id', 'utm_campaign');
        $out = array();
        foreach ($allowed as $key) if (isset($touch[$key]) && is_scalar($touch[$key])) $out[$key] = sanitize_text_field($touch[$key]);
        return $out;
    }

    public function session_key() { return $this->session_key; }
    public function first() { return $this->first; }
    public function last() { return $this->last; }
}

class SMF_V3_Event_Envelope implements SMF_V3_Event_Interface {
    private $name;
    private $version;
    private $id;
    private $occurred_at;
    private $payload;

    public function __construct($name, $version, $id, $occurred_at, array $payload = array()) {
        $this->name = sanitize_key($name);
        $this->version = sanitize_text_field($version);
        $this->id = sanitize_text_field($id);
        $this->occurred_at = sanitize_text_field($occurred_at);
        $this->payload = self::safe_payload($payload);
    }

    private static function safe_payload(array $payload) {
        $blocked = array('token', 'access_token', 'api_key', 'secret', 'password', 'authorization', 'payload');
        foreach ($blocked as $key) unset($payload[$key]);
        return $payload;
    }

    public function name() { return $this->name; }
    public function version() { return $this->version; }
    public function id() { return $this->id; }
    public function occurred_at() { return $this->occurred_at; }
    public function payload() { return $this->payload; }
}
