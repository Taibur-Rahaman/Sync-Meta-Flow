<?php
defined('ABSPATH') || exit;

interface SMF_V3_Repository_Interface {
    public function find($id);
}

interface SMF_V3_Event_Interface {
    public function name();
    public function version();
    public function id();
    public function occurred_at();
    public function payload();
}

interface SMF_V3_Event_Dispatcher_Interface {
    public function dispatch(SMF_V3_Event_Interface $event);
}

interface SMF_V3_Attribution_Provider_Interface {
    public function first($session_key);
    public function last($session_key);
}

interface SMF_V3_Attribution_Allocator_Interface {
    public function allocate(array $touchpoints, $value, $model, array $weights = array());
}

interface SMF_V3_Attribution_Quality_Interface {
    public function score(array $touchpoints, $conversion = null, array $stats = array());
}

interface SMF_V3_Courier_Provider_Interface {
    public function name();
    public function normalize_status($status);
}

interface SMF_V3_Courier_Intelligence_Interface {
    public function provider_report($days = 30);
    public function journey(array $shipment, array $events, array $customer_stats = array(), array $provider = array());
}

interface SMF_V3_Marketing_Provider_Interface {
    public function configured();
}

interface SMF_V3_Tracking_Provider_Interface {
    public function record($event_name, array $payload = array());
}

interface SMF_V3_Recommendation_Provider_Interface {
    public function recommendations($days = 30, $model = 'last_touch');
}

interface SMF_V3_Automation_Action_Interface {
    public function key();
    public function execute(array $context = array());
}

interface SMF_V3_Observability_Interface {
    public function report();
}

interface SMF_V3_Integration_Interface {
    public function key();
    public function status();
}

interface SMF_V3_Configuration_Interface {
    public function get($key, $default = null);
}

interface SMF_V3_Clock_Interface {
    public function now();
}

interface SMF_V3_Entitlement_Checker_Interface {
    public function can($capability);
}

interface SMF_V3_AI_Provider_Interface_Contract {
    public function complete(array $context, $question);
}
