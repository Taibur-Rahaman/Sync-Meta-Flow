<?php
if(!defined('ABSPATH'))exit;
class SMF_Attribution_Model{
 public static function normalize_model($m){$a=array('last_touch','first_touch','first_last','assisted');$m=sanitize_key((string)$m);return in_array($m,$a,true)?$m:'last_touch';}
 public static function label($m){$m=self::normalize_model($m);$a=array('last_touch'=>'Last Touch','first_touch'=>'First Touch','first_last'=>'First + Last (50/50)','assisted'=>'Assisted First-Touch Influence');return $a[$m];}
 public static function get_selected_campaign($first,$last,$model){$model=self::normalize_model($model);return $model==='first_touch'?$first:($last?$last:$first);}
 public static function is_different_touch($first,$last){$a=self::touch_id($first);$b=self::touch_id($last);return $a!==''&&$b!==''&&$a!==$b;}
 public static function touch_id($t){if(!is_array($t))return '';foreach(array('ad_id','adset_id','campaign_id','utm_campaign') as $k)if(!empty($t[$k]))return sanitize_text_field((string)$t[$k]);return '';}
 public static function display_name($t){if(!is_array($t))return '';foreach(array('campaign_name','utm_campaign','campaign_id') as $k)if(!empty($t[$k]))return sanitize_text_field((string)$t[$k]);return '';}
 public static function allocation($first,$last,$value,$model){$m=self::normalize_model($model);$v=(float)$value;$f=self::display_name($first);$l=self::display_name($last);if($f==='')$f='Direct / Unattributed';if($l==='')$l=$f;if($m==='first_touch')return array($f=>$v);if($m==='last_touch')return array($l=>$v);if($m==='assisted')return $f!==$l&&$f!=='Direct / Unattributed'?array($f=>$v):array();if($f!==$l)return array($f=>$v/2,$l=>$v/2);return array($f=>$v);}
}
