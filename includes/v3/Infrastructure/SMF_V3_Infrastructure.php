<?php
defined('ABSPATH') || exit;

class SMF_V3_Container {
    private $services = array();

    public function set($key, $service) {
        if (isset($this->services[$key])) throw new InvalidArgumentException('V3 service already registered.');
        $this->services[$key] = $service;
        return $this;
    }

    public function has($key) { return isset($this->services[$key]); }

    public function get($key) {
        if (!$this->has($key)) throw new RuntimeException('V3 service is not registered.');
        return $this->services[$key];
    }
}

class SMF_V3_WordPress_Configuration implements SMF_V3_Configuration_Interface {
    public function get($key, $default = null) {
        return get_option('smf_v3_' . sanitize_key($key), $default);
    }
}

class SMF_V3_WordPress_Clock implements SMF_V3_Clock_Interface {
    public function now() { return current_time('mysql'); }
}

class SMF_V3_Synchronous_Dispatcher implements SMF_V3_Event_Dispatcher_Interface {
    public function dispatch(SMF_V3_Event_Interface $event) {
        do_action('smf_v3_event_' . $event->name(), $event);
        return true;
    }
}

class SMF_V3_Observability_Adapter implements SMF_V3_Observability_Interface {
    public function report() {
        return class_exists('SMF_Observability') ? SMF_Observability::report() : array('overall' => 'blocking');
    }
}

class SMF_V3_Attribution_Adapter implements SMF_V3_Attribution_Provider_Interface {
    public function first($session_key) { return class_exists('SMF_Attribution') ? SMF_Attribution::first($session_key) : array(); }
    public function last($session_key) { return class_exists('SMF_Attribution') ? SMF_Attribution::last($session_key) : array(); }
}

class SMF_V3_Courier_Adapter implements SMF_V3_Courier_Provider_Interface {
    public function name() { return sanitize_key((string) get_option('smf_courier_provider', 'generic')); }
    public function normalize_status($status) {
        $status = strtolower(sanitize_key($status));
        $map = array('confirmed' => 'confirmed', 'pending' => 'confirmed', 'in_review' => 'confirmed', 'shipped' => 'shipped', 'in_transit' => 'shipped', 'picked_up' => 'shipped', 'delivered' => 'delivered', 'completed' => 'delivered', 'returned' => 'returned', 'refunded' => 'returned', 'cancelled' => 'cancelled', 'canceled' => 'cancelled', 'failed' => 'cancelled');
        return isset($map[$status]) ? $map[$status] : '';
    }
}

class SMF_V3_Legacy_Repository_Adapter implements SMF_V3_Repository_Interface {
    private $table;
    public function __construct($table) { $this->table = sanitize_key($table); }
    public function find($id) {
        global $wpdb;
        $id = absint($id);
        if (!$id || $this->table === '') return null;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . $this->table . ' WHERE id=%d LIMIT 1', $id));
    }
}
