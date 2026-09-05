<?php
defined('ABSPATH') || exit;

class SMF_V3_Order_Created extends SMF_V3_Event_Envelope {
    public function __construct($id, $occurred_at, array $payload = array()) { parent::__construct('order_created', '1.0', $id, $occurred_at, $payload); }
}

class SMF_V3_Order_Status_Changed extends SMF_V3_Event_Envelope {
    public function __construct($id, $occurred_at, array $payload = array()) { parent::__construct('order_status_changed', '1.0', $id, $occurred_at, $payload); }
}

class SMF_V3_Courier_Status_Received extends SMF_V3_Event_Envelope {
    public function __construct($id, $occurred_at, array $payload = array()) { parent::__construct('courier_status_received', '1.0', $id, $occurred_at, $payload); }
}

class SMF_V3_Meta_Event_Queued extends SMF_V3_Event_Envelope {
    public function __construct($id, $occurred_at, array $payload = array()) { parent::__construct('meta_event_queued', '1.0', $id, $occurred_at, $payload); }
}

class SMF_V3_Recommendation_Created extends SMF_V3_Event_Envelope {
    public function __construct($id, $occurred_at, array $payload = array()) { parent::__construct('recommendation_created', '1.0', $id, $occurred_at, $payload); }
}
class SMF_V3_Recommendation_Approved extends SMF_V3_Event_Envelope {
    public function __construct($id, $occurred_at, array $payload = array()) { parent::__construct('recommendation_approved', '1.0', $id, $occurred_at, $payload); }
}
class SMF_V3_Recommendation_Rejected extends SMF_V3_Event_Envelope {
    public function __construct($id, $occurred_at, array $payload = array()) { parent::__construct('recommendation_rejected', '1.0', $id, $occurred_at, $payload); }
}
class SMF_V3_Automation_Requested extends SMF_V3_Event_Envelope {
    public function __construct($id, $occurred_at, array $payload = array()) { parent::__construct('automation_requested', '1.0', $id, $occurred_at, $payload); }
}
class SMF_V3_Automation_Started extends SMF_V3_Event_Envelope {
    public function __construct($id, $occurred_at, array $payload = array()) { parent::__construct('automation_started', '1.0', $id, $occurred_at, $payload); }
}
class SMF_V3_Automation_Succeeded extends SMF_V3_Event_Envelope {
    public function __construct($id, $occurred_at, array $payload = array()) { parent::__construct('automation_succeeded', '1.0', $id, $occurred_at, $payload); }
}
class SMF_V3_Automation_Failed extends SMF_V3_Event_Envelope {
    public function __construct($id, $occurred_at, array $payload = array()) { parent::__construct('automation_failed', '1.0', $id, $occurred_at, $payload); }
}
class SMF_V3_Automation_Verified extends SMF_V3_Event_Envelope {
    public function __construct($id, $occurred_at, array $payload = array()) { parent::__construct('automation_verified', '1.0', $id, $occurred_at, $payload); }
}
class SMF_V3_Automation_Expired extends SMF_V3_Event_Envelope {
    public function __construct($id, $occurred_at, array $payload = array()) { parent::__construct('automation_expired', '1.0', $id, $occurred_at, $payload); }
}
