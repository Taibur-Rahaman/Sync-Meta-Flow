<?php
defined('ABSPATH') || exit;

class SMF_V3_Result {
    private $success;
    private $value;
    private $error;

    private function __construct($success, $value = null, $error = '') { $this->success = (bool) $success; $this->value = $value; $this->error = sanitize_text_field($error); }
    public static function ok($value = null) { return new self(true, $value); }
    public static function fail($error) { return new self(false, null, $error); }
    public function is_ok() { return $this->success; }
    public function value() { return $this->value; }
    public function error() { return $this->error; }
}

class SMF_V3_Service_Bootstrap {
    public static function build() {
        $container = new SMF_V3_Container();
        $container->set('config', new SMF_V3_WordPress_Configuration());
        $container->set('clock', new SMF_V3_WordPress_Clock());
        $container->set('events', new SMF_V3_Synchronous_Dispatcher());
        $container->set('observability', new SMF_V3_Observability_Adapter());
        $container->set('attribution', new SMF_V3_Attribution_Adapter());
        $container->set('courier', new SMF_V3_Courier_Adapter());
        if (class_exists('SMF_V3_Automation_Engine')) {
            $container->set('automation', new SMF_V3_Automation_Engine(new SMF_V3_Automation_Action_Registry(), $container->get('events')));
        }
        if (class_exists('SMF_V3_Attribution_Intelligence_Service')) {
            $container->set('attribution_intelligence', new SMF_V3_Attribution_Intelligence_Service());
        }
        if (class_exists('SMF_V3_Courier_Intelligence_Engine')) {
            $container->set('courier_intelligence', new SMF_V3_Courier_Intelligence_Engine());
        }
        if (class_exists('SMF_V3_Entitlement_Checker')) {
            $container->set('entitlements', new SMF_V3_Entitlement_Checker());
        }
        if (class_exists('SMF_V3_AI_Assistant')) {
            $container->set('ai', new SMF_V3_AI_Assistant());
        }
        return $container;
    }
}

class SMF_V3_Feature_Flag {
    public static function enabled() { return get_option('smf_v3_enabled', 'no') === 'yes'; }
    public static function automation_enabled() {
        return self::enabled() && get_option('smf_v3_automation_enabled', 'no') === 'yes' && self::entitled('automation');
    }
    public static function advanced_attribution_enabled() {
        return self::enabled() && get_option('smf_v3_advanced_attribution', 'no') === 'yes' && self::entitled('advanced_attribution');
    }
    public static function courier_intelligence_enabled() {
        return self::enabled() && get_option('smf_v3_courier_intelligence', 'no') === 'yes' && self::entitled('courier_intelligence');
    }
    public static function commercial_enabled() {
        return self::enabled() && get_option('smf_v3_commercial_enabled', 'no') === 'yes';
    }
    public static function ai_enabled() {
        return self::enabled() && get_option('smf_v3_ai_enabled', 'no') === 'yes' && self::entitled('ai_assistant');
    }
    private static function entitled($capability) {
        if (!self::commercial_enabled() || !class_exists('SMF_V3_Entitlement_Checker')) return true;
        return SMF_V3_Entitlement_Checker::check($capability);
    }
}

class SMF_V3_Bootstrap {
    private static $container;
    public static function init() {
        if (self::$container !== null) return self::$container;
        try {
            self::$container = SMF_V3_Service_Bootstrap::build();
            if (class_exists('SMF_V3_Automation_Service')) SMF_V3_Automation_Service::init();
            if (class_exists('SMF_V3_Attribution_Service')) SMF_V3_Attribution_Service::init();
            if (class_exists('SMF_V3_Courier_Intelligence_Service')) SMF_V3_Courier_Intelligence_Service::init();
            if (class_exists('SMF_V3_Commercial_Service')) SMF_V3_Commercial_Service::init();
            if (class_exists('SMF_V3_AI_Service')) SMF_V3_AI_Service::init();
        } catch (Throwable $exception) {
            self::$container = null;
        }
        return self::$container;
    }
    public static function container() { return self::$container; }
}
